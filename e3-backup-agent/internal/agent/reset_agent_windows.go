//go:build windows

package agent

import (
	"encoding/base64"
	"fmt"
	"os/exec"
	"syscall"
	"time"
	"unicode/utf16"
)

// triggerAgentServiceRestart schedules a Windows service restart for the agent.
// The command intentionally keeps the existing name for compatibility with callers.
func triggerAgentServiceRestart() error {
	serviceName := "e3-backup-agent"
	psScript := buildServiceRestartScript(serviceName)
	encoded := encodePowerShellCommand(psScript)

	var lastErr error
	if err := scheduleServiceRestartTask(encoded); err == nil {
		return nil
	} else {
		lastErr = err
	}

	if err := runDetached(
		"powershell.exe",
		"-NoProfile",
		"-NonInteractive",
		"-ExecutionPolicy", "Bypass",
		"-WindowStyle", "Hidden",
		"-EncodedCommand", encoded,
	); err == nil {
		return nil
	} else {
		lastErr = err
	}

	if lastErr == nil {
		lastErr = fmt.Errorf("unable to schedule service restart")
	}
	return lastErr
}

func scheduleServiceRestartTask(encodedCommand string) error {
	taskName := fmt.Sprintf("E3BackupAgentRestart_%d", time.Now().UTC().UnixNano())
	start := time.Now().Add(1 * time.Minute).Format("15:04")
	taskCmd := "powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -EncodedCommand " + encodedCommand

	if err := runCommand(
		"schtasks.exe",
		"/Create", "/F",
		"/TN", taskName,
		"/SC", "ONCE",
		"/ST", start,
		"/RL", "HIGHEST",
		"/RU", "SYSTEM",
		"/TR", taskCmd,
	); err != nil {
		return fmt.Errorf("create restart task failed: %w", err)
	}
	if err := runCommand("schtasks.exe", "/Run", "/TN", taskName); err != nil {
		_ = runCommand("schtasks.exe", "/Delete", "/F", "/TN", taskName)
		return fmt.Errorf("run restart task failed: %w", err)
	}
	_ = runCommand("schtasks.exe", "/Delete", "/F", "/TN", taskName)
	return nil
}

func buildServiceRestartScript(serviceName string) string {
	return fmt.Sprintf(
		`$svcName='%s';`+
			`$deadline=(Get-Date).AddMinutes(3);`+
			`$forceAfter=(Get-Date).AddSeconds(20);`+
			`while((Get-Date)-lt $deadline){`+
			`try{`+
			`$svc=Get-Service -Name $svcName -ErrorAction Stop;`+
			`if($svc.Status -eq 'Stopped'){`+
			`try{Start-Service -Name $svcName -ErrorAction SilentlyContinue}catch{};`+
			`$svc.Refresh();`+
			`if($svc.Status -eq 'Running'){ exit 0 }`+
			`}elseif($svc.Status -eq 'Running' -and (Get-Date)-gt $forceAfter){`+
			`try{Stop-Service -Name $svcName -Force -ErrorAction SilentlyContinue}catch{};`+
			`}`+
			`}catch{}`+
			`Start-Sleep -Seconds 1;`+
			`}; exit 1`,
		serviceName,
	)
}

func encodePowerShellCommand(command string) string {
	utf16Cmd := utf16.Encode([]rune(command))
	buf := make([]byte, len(utf16Cmd)*2)
	for i, v := range utf16Cmd {
		buf[i*2] = byte(v)
		buf[i*2+1] = byte(v >> 8)
	}
	return base64.StdEncoding.EncodeToString(buf)
}

func runCommand(name string, args ...string) error {
	cmd := exec.Command(name, args...)
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("%s failed: %w (output: %s)", name, err, string(out))
	}
	return nil
}

func runDetached(name string, args ...string) error {
	cmd := exec.Command(name, args...)
	const detachedProcess = 0x00000008
	const breakawayFromJob = 0x01000000
	const createNoWindow = 0x08000000
	cmd.SysProcAttr = &syscall.SysProcAttr{
		CreationFlags: detachedProcess | breakawayFromJob | createNoWindow | syscall.CREATE_NEW_PROCESS_GROUP,
		HideWindow:    true,
	}
	return cmd.Start()
}
