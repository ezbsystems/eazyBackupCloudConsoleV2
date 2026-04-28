//go:build windows
// +build windows

package hyperv

import (
	"fmt"
	"runtime"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/go-ole/go-ole"
	"github.com/go-ole/go-ole/oleutil"
)

// hypervNamespace is the WMI namespace exposing the Hyper-V v2 (Windows
// Server 2012R2+) management classes used for reference points and RCT.
const hypervNamespace = `root\virtualization\v2`

// wmiSession holds a connected SWbemServices for the Hyper-V WMI namespace
// plus the COM lifecycle bookkeeping required by go-ole. Every wmiSession
// must be created and used from a single OS thread.
type wmiSession struct {
	locator *ole.IDispatch
	service *ole.IDispatch
	thread  bool // true once runtime.LockOSThread was issued
	co      bool // true once CoInitializeEx succeeded
}

// newWMISession initialises COM on the current goroutine's OS thread,
// connects to the Hyper-V virtualization namespace, and returns a session
// the caller MUST close with sess.Close().
//
// The session pins itself to the calling goroutine's OS thread for as
// long as it lives.
func newWMISession() (*wmiSession, error) {
	runtime.LockOSThread()
	sess := &wmiSession{thread: true}

	if err := ole.CoInitializeEx(0, ole.COINIT_MULTITHREADED); err != nil {
		// S_FALSE (already initialised) is reported as an error by go-ole;
		// treat it as success so nested call sites do not unwind unexpectedly.
		oleErr, ok := err.(*ole.OleError)
		if !ok || oleErr.Code() != 0x00000001 {
			runtime.UnlockOSThread()
			return nil, fmt.Errorf("CoInitializeEx: %w", err)
		}
	}
	sess.co = true

	unknown, err := oleutil.CreateObject("WbemScripting.SWbemLocator")
	if err != nil {
		sess.Close()
		return nil, fmt.Errorf("CreateObject(SWbemLocator): %w", err)
	}
	defer unknown.Release()

	locator, err := unknown.QueryInterface(ole.IID_IDispatch)
	if err != nil {
		sess.Close()
		return nil, fmt.Errorf("QueryInterface(IDispatch): %w", err)
	}
	sess.locator = locator

	serviceVar, err := oleutil.CallMethod(locator, "ConnectServer", nil, hypervNamespace)
	if err != nil {
		sess.Close()
		return nil, fmt.Errorf("ConnectServer(%s): %w", hypervNamespace, err)
	}
	sess.service = serviceVar.ToIDispatch()
	if sess.service == nil {
		sess.Close()
		return nil, fmt.Errorf("ConnectServer(%s): nil dispatch", hypervNamespace)
	}

	return sess, nil
}

// Close releases all COM handles and unwinds COM/thread state.
func (s *wmiSession) Close() {
	if s == nil {
		return
	}
	if s.service != nil {
		s.service.Release()
		s.service = nil
	}
	if s.locator != nil {
		s.locator.Release()
		s.locator = nil
	}
	if s.co {
		ole.CoUninitialize()
		s.co = false
	}
	if s.thread {
		runtime.UnlockOSThread()
		s.thread = false
	}
}

// classExists returns true when the named WMI class is present in the
// connected namespace. Used to probe Hyper-V capabilities (e.g. whether the
// host exposes Msvm_VirtualSystemReferencePointService at all).
func (s *wmiSession) classExists(className string) bool {
	clsVar, err := oleutil.CallMethod(s.service, "Get", className)
	if err != nil {
		return false
	}
	cls := clsVar.ToIDispatch()
	if cls == nil {
		return false
	}
	cls.Release()
	return true
}

// execQuery runs a WQL query and returns the result enumeration as an
// iterable IDispatch (SWbemObjectSet). The caller MUST Release() the result.
func (s *wmiSession) execQuery(wql string) (*ole.IDispatch, error) {
	resultVar, err := oleutil.CallMethod(s.service, "ExecQuery", wql)
	if err != nil {
		return nil, fmt.Errorf("ExecQuery(%s): %w", wql, err)
	}
	d := resultVar.ToIDispatch()
	if d == nil {
		return nil, fmt.Errorf("ExecQuery(%s): nil dispatch", wql)
	}
	return d, nil
}

// forEachInstance walks an SWbemObjectSet via its NewEnum/IEnumVARIANT
// surface, invoking fn for each contained SWbemObject. fn must NOT release
// its argument; the loop releases each instance after fn returns.
//
// IEnumVARIANT::Next returns S_FALSE (HRESULT 0x00000001, surfaced by
// go-ole's NewError as the Win32 "Incorrect function" message) when it
// reaches the end of the enumeration. We MUST therefore branch on length
// before consulting err; treating S_FALSE as fatal would silently break
// every WMI enumeration in the package.
func forEachInstance(set *ole.IDispatch, fn func(item *ole.IDispatch) error) error {
	enumVar, err := set.GetProperty("_NewEnum")
	if err != nil {
		return fmt.Errorf("get _NewEnum: %w", err)
	}
	defer enumVar.Clear()

	unk := enumVar.ToIUnknown()
	if unk == nil {
		return fmt.Errorf("_NewEnum: nil IUnknown")
	}
	enum, err := unk.IEnumVARIANT(ole.IID_IEnumVariant)
	if err != nil {
		return fmt.Errorf("IEnumVARIANT QI: %w", err)
	}
	if enum == nil {
		return fmt.Errorf("IEnumVARIANT: nil")
	}
	defer enum.Release()

	for itemVar, length, nextErr := enum.Next(1); length > 0; itemVar, length, nextErr = enum.Next(1) {
		if nextErr != nil {
			return fmt.Errorf("enum.Next: %w", nextErr)
		}
		item := itemVar.ToIDispatch()
		if item == nil {
			itemVar.Clear()
			continue
		}
		if err := fn(item); err != nil {
			item.Release()
			return err
		}
		item.Release()
	}
	return nil
}

// getProp fetches a string property from an SWbemObject. Returns "" if the
// property is missing or VT_NULL.
//
// Special-cases the WMI system property "__PATH" (and its variants), which
// SWbem does not expose via Invoke; it lives behind SWbemObject.Path_.Path.
func getProp(obj *ole.IDispatch, name string) string {
	if name == "__PATH" || name == "__Path" || name == "Path_" {
		pathVar, err := oleutil.GetProperty(obj, "Path_")
		if err != nil {
			return ""
		}
		defer pathVar.Clear()
		path := pathVar.ToIDispatch()
		if path == nil {
			return ""
		}
		defer path.Release()
		// "RelPath" gives Class.Key="..."; "Path" includes the host prefix.
		// Hyper-V's WMI methods accept either, but RelPath is what the
		// SWbem ExecMethod_ in-parameter expects when the REF target lives
		// in the same namespace as the service.
		v, err := oleutil.GetProperty(path, "RelPath")
		if err != nil {
			return ""
		}
		defer v.Clear()
		if v.VT == ole.VT_NULL || v.VT == ole.VT_EMPTY {
			return ""
		}
		return v.ToString()
	}
	v, err := oleutil.GetProperty(obj, name)
	if err != nil {
		return ""
	}
	defer v.Clear()
	return variantToString(v)
}

// variantToString converts a VARIANT to its string representation, handling
// the integer / boolean types WMI uses for method return codes and enums in
// addition to the BSTR strings handled by VARIANT.ToString.
func variantToString(v *ole.VARIANT) string {
	if v == nil {
		return ""
	}
	switch v.VT {
	case ole.VT_NULL, ole.VT_EMPTY:
		return ""
	case ole.VT_BSTR:
		return v.ToString()
	case ole.VT_BOOL:
		if v.Val != 0 {
			return "True"
		}
		return "False"
	case ole.VT_I1, ole.VT_I2, ole.VT_I4, ole.VT_INT,
		ole.VT_UI1, ole.VT_UI2, ole.VT_UI4, ole.VT_UINT,
		ole.VT_I8, ole.VT_UI8:
		return strconv.FormatInt(v.Val, 10)
	}
	if s := v.ToString(); s != "" {
		return s
	}
	if iv, ok := v.Value().(string); ok {
		return iv
	}
	return ""
}

// execMethod invokes a WMI method that takes typed in-parameters and may
// return out-parameters. It works against an instance dispatch (e.g. an
// Msvm_VirtualSystemReferencePointService singleton).
//
// Internally this uses the SWbemObject.Methods_/InParameters/SpawnInstance_
// dance because go-ole cannot synthesize WMI in-parameter VARIANTs from a
// flat positional argument list for methods that take embedded objects or
// reference parameters.
//
// inParams is a list of (paramName, value) pairs. Strings, ints, and bools
// pass through to oleutil.PutProperty unchanged.
//
// The returned outParams dispatch belongs to the caller; the caller MUST
// Release() it. ReturnValue can be read via getProp(outParams, "ReturnValue").
func (s *wmiSession) execMethod(instance *ole.IDispatch, className, methodName string, inParams [][2]any) (outParams *ole.IDispatch, err error) {
	// Build the method's InParameters template via the class definition.
	clsVar, err := oleutil.CallMethod(s.service, "Get", className)
	if err != nil {
		return nil, fmt.Errorf("Get class %s: %w", className, err)
	}
	cls := clsVar.ToIDispatch()
	defer cls.Release()

	methodsVar, err := oleutil.GetProperty(cls, "Methods_")
	if err != nil {
		return nil, fmt.Errorf("class %s Methods_: %w", className, err)
	}
	methods := methodsVar.ToIDispatch()
	defer methods.Release()

	methodVar, err := oleutil.CallMethod(methods, "Item", methodName)
	if err != nil {
		return nil, fmt.Errorf("Methods_.Item(%s): %w", methodName, err)
	}
	method := methodVar.ToIDispatch()
	defer method.Release()

	var inInstance *ole.IDispatch
	if len(inParams) > 0 {
		inParamsTplVar, err := oleutil.GetProperty(method, "InParameters")
		if err != nil {
			return nil, fmt.Errorf("method %s InParameters: %w", methodName, err)
		}
		inParamsTpl := inParamsTplVar.ToIDispatch()
		defer inParamsTpl.Release()

		spawnVar, err := oleutil.CallMethod(inParamsTpl, "SpawnInstance_")
		if err != nil {
			return nil, fmt.Errorf("SpawnInstance_ for %s: %w", methodName, err)
		}
		inInstance = spawnVar.ToIDispatch()
		defer inInstance.Release()

		for _, kv := range inParams {
			name, ok := kv[0].(string)
			if !ok {
				return nil, fmt.Errorf("execMethod %s: param name not string", methodName)
			}
			if _, err := oleutil.PutProperty(inInstance, name, kv[1]); err != nil {
				return nil, fmt.Errorf("PutProperty(%s) on %s: %w", name, methodName, err)
			}
		}
	}

	var outVar *ole.VARIANT
	if inInstance != nil {
		outVar, err = oleutil.CallMethod(instance, "ExecMethod_", methodName, inInstance)
	} else {
		outVar, err = oleutil.CallMethod(instance, "ExecMethod_", methodName)
	}
	if err != nil {
		return nil, fmt.Errorf("ExecMethod_(%s): %w", methodName, err)
	}
	out := outVar.ToIDispatch()
	if out == nil {
		return nil, fmt.Errorf("ExecMethod_(%s): nil out dispatch", methodName)
	}
	return out, nil
}

// spawnSettingsMOF spawns a fresh instance of a Hyper-V embedded-instance
// settings class, populates the named scalar properties, and serialises it
// as a MOF string suitable for passing as the
// HyperVEmbeddedInstance-qualified parameter of an Msvm_* method.
func (s *wmiSession) spawnSettingsMOF(className string, props map[string]any) (string, error) {
	clsVar, err := oleutil.CallMethod(s.service, "Get", className)
	if err != nil {
		return "", fmt.Errorf("Get class %s: %w", className, err)
	}
	cls := clsVar.ToIDispatch()
	defer cls.Release()

	instVar, err := oleutil.CallMethod(cls, "SpawnInstance_")
	if err != nil {
		return "", fmt.Errorf("SpawnInstance_(%s): %w", className, err)
	}
	inst := instVar.ToIDispatch()
	defer inst.Release()

	for k, v := range props {
		if _, err := oleutil.PutProperty(inst, k, v); err != nil {
			return "", fmt.Errorf("PutProperty(%s.%s): %w", className, k, err)
		}
	}

	// GetText_(2) → wmiObjectTextFormatMof
	textVar, err := oleutil.CallMethod(inst, "GetText_", 2)
	if err != nil {
		return "", fmt.Errorf("GetText_(MOF) for %s: %w", className, err)
	}
	defer textVar.Clear()
	mof := variantToString(textVar)
	if mof == "" {
		return "", fmt.Errorf("GetText_(MOF) for %s returned empty string", className)
	}
	return mof, nil
}

// getSingletonInstance returns the (single) instance of a WMI class, or an
// error if the class is missing or has no instances. Caller MUST Release().
func (s *wmiSession) getSingletonInstance(className string) (*ole.IDispatch, error) {
	set, err := s.execQuery(fmt.Sprintf("SELECT * FROM %s", className))
	if err != nil {
		return nil, err
	}
	defer set.Release()

	var found *ole.IDispatch
	err = forEachInstance(set, func(item *ole.IDispatch) error {
		if found != nil {
			return nil
		}
		// AddRef so the loop's Release() does not free the object we keep.
		item.AddRef()
		found = item
		return nil
	})
	if err != nil {
		if found != nil {
			found.Release()
		}
		return nil, err
	}
	if found == nil {
		return nil, fmt.Errorf("class %s: no instances", className)
	}
	return found, nil
}

// waitForJob blocks until the referenced CIM_ConcreteJob completes. jobRef
// is the WMI path returned in a method's "Job" out-parameter (may be empty
// when the operation completed synchronously).
//
// Returns nil for synchronous completion or terminal success states; an
// error containing the job's ErrorDescription/StatusDescription otherwise.
func (s *wmiSession) waitForJob(jobRef string) error {
	jobRef = strings.TrimSpace(jobRef)
	if jobRef == "" {
		return nil
	}
	for i := 0; i < 600; i++ { // up to ~5 minutes (500ms * 600)
		jobVar, err := oleutil.CallMethod(s.service, "Get", jobRef)
		if err != nil {
			return fmt.Errorf("get job %s: %w", jobRef, err)
		}
		job := jobVar.ToIDispatch()
		if job == nil {
			return fmt.Errorf("get job %s: nil dispatch", jobRef)
		}
		state := getProp(job, "JobState")
		errDesc := getProp(job, "ErrorDescription")
		statusDesc := getProp(job, "StatusDescription")
		job.Release()

		// CIM_ConcreteJob.JobState: 7=Completed, 8=Killed, 9=Exception,
		// 10=Service, 32768+=DMTF reserved. Anything < 7 means still running.
		switch state {
		case "7":
			return nil
		case "8":
			return fmt.Errorf("hyperv job %s killed: %s", jobRef, statusDesc)
		case "9", "10":
			if errDesc == "" {
				errDesc = statusDesc
			}
			return fmt.Errorf("hyperv job %s failed (state=%s): %s", jobRef, state, errDesc)
		}
		time.Sleep(500 * time.Millisecond)
	}
	return fmt.Errorf("hyperv job %s did not complete within timeout", jobRef)
}

// onceCache is a tiny generic-ish wrapper so capability probes only run once
// per process per session class. It's intentionally simple (no eviction).
type onceCache struct {
	once sync.Once
	val  bool
}

func (c *onceCache) get(probe func() bool) bool {
	c.once.Do(func() { c.val = probe() })
	return c.val
}
