package main

import (
	"errors"
	"flag"
	"log"
	"os"
	"os/signal"
	"path/filepath"
	"runtime"
	"syscall"
	"time"

	"github.com/kardianos/service"
	"github.com/your-org/e3-backup-agent/internal/agent"
	"github.com/your-org/e3-backup-agent/internal/applog"
)

// version and commit are injected at link time via
// `-X main.version`/`-X main.commit` (see Makefile LDFLAGS). They are forwarded
// into the agent package so the runtime has a single authoritative version for
// server reporting and post-update verification.
var (
	version string
	commit  string
)

func main() {
	agent.SetBuildInfo(version, commit)

	configPath := flag.String("config", "agent.conf", "Path to agent configuration file")
	svcCmd := flag.String("service", "", "Service control action: install, uninstall, start, stop")
	flag.Parse()

	// On Windows, log to a file so service start failures are diagnosable (Event Viewer 7034 is too generic).
	if runtime.GOOS == "windows" {
		setupWindowsFileLogging()
	}

	if *svcCmd != "" {
		runServiceCommand(*svcCmd, *configPath)
		return
	}

	// If running as a service on Windows, let kardianos/service manage lifecycle
	if runtime.GOOS == "windows" {
		svcConfig := &service.Config{
			Name:        "e3-backup-agent",
			DisplayName: "E3 Backup Agent",
			Description: "E3 backup agent service",
			Arguments:   []string{"-config", *configPath},
		}
		prg := &program{configPath: *configPath}
		s, err := service.New(prg, svcConfig)
		if err == nil && service.Interactive() == false {
			if err := s.Run(); err != nil {
				log.Fatalf("service run error: %v", err)
			}
			return
		}
	}

	// Foreground mode
	cfg, err := agent.LoadConfigAllowUnenrolled(*configPath)
	if err != nil && !errors.Is(err, agent.ErrMissingEnrollment) {
		log.Fatalf("failed to load config: %v", err)
	}
	if errors.Is(err, agent.ErrMissingEnrollment) {
		log.Printf("config missing enrollment credentials; waiting for enrollment")
	}

	if cfg != nil && cfg.LogLevel != "" {
		applog.SetLevel(applog.ParseLevel(cfg.LogLevel))
	}

	log.Printf("e3-backup-agent starting (client_id=%s, agent_uuid=%s, api=%s)", cfg.ClientID, cfg.AgentUUID, cfg.APIBaseURL)

	r := agent.NewRunner(cfg, *configPath)
	stop := make(chan struct{})
	signals := make(chan os.Signal, 1)
	signal.Notify(signals, syscall.SIGINT, syscall.SIGTERM)
	go func() {
		<-signals
		close(stop)
	}()
	r.Start(stop)
}

// program implements service.Interface
type program struct {
	configPath string
	stop       chan struct{}
}

func (p *program) Start(s service.Service) error {
	p.stop = make(chan struct{})
	go p.run()
	return nil
}

func (p *program) run() {
	cfg, err := agent.LoadConfigAllowUnenrolled(p.configPath)
	if err != nil && !errors.Is(err, agent.ErrMissingEnrollment) {
		log.Printf("service: failed to load config: %v", err)
		// Return so SCM reports a start failure; log file will contain the reason.
		return
	}
	if errors.Is(err, agent.ErrMissingEnrollment) {
		log.Printf("service: config missing enrollment credentials; waiting for enrollment")
	}
	if cfg != nil && cfg.LogLevel != "" {
		applog.SetLevel(applog.ParseLevel(cfg.LogLevel))
	}
	log.Printf("service: e3-backup-agent starting (client_id=%s, agent_uuid=%s, api=%s)", cfg.ClientID, cfg.AgentUUID, cfg.APIBaseURL)
	r := agent.NewRunner(cfg, p.configPath)
	r.Start(p.stop)
}

func (p *program) Stop(s service.Service) error {
	if p.stop != nil {
		close(p.stop)
	}
	return nil
}

func runServiceCommand(cmd string, configPath string) {
	svcConfig := &service.Config{
		Name:        "e3-backup-agent",
		DisplayName: "E3 Backup Agent",
		Description: "E3 backup agent service",
		Arguments:   []string{"-config", configPath},
	}
	prg := &program{configPath: configPath}
	s, err := service.New(prg, svcConfig)
	if err != nil {
		log.Fatalf("service create error: %v", err)
	}
	switch cmd {
	case "install":
		// On upgrades the service already exists, which makes Control return an
		// error. Treat that as non-fatal so a subsequent start/restart can still
		// bring the service up; a genuinely broken install surfaces when start
		// fails below.
		if ierr := service.Control(s, "install"); ierr != nil {
			log.Printf("service install: %v (continuing)", ierr)
		}
	case "uninstall":
		err = service.Control(s, "uninstall")
	case "start":
		err = service.Control(s, "start")
	case "stop":
		err = service.Control(s, "stop")
	case "restart":
		// Stop is best-effort: the service may already be stopped (the installer's
		// PrepareToInstall stops it, or this is a first run). We deliberately
		// ignore the stop error and always attempt start so the freshly installed
		// binary and any updated agent.conf are loaded. A bare "start" would be a
		// no-op when the service is already running, leaving it on stale config.
		if serr := service.Control(s, "stop"); serr != nil {
			log.Printf("service restart: stop returned (continuing): %v", serr)
		}
		waitForServiceStopped(s, 15*time.Second)
		err = service.Control(s, "start")
	default:
		log.Fatalf("unknown service command: %s", cmd)
	}
	if err != nil {
		log.Fatalf("service command failed: %v", err)
	}
	log.Printf("service command %s: ok", cmd)
}

// waitForServiceStopped polls the SCM until the service reports Stopped or the
// timeout elapses. This avoids the race where "start" is issued while the
// service is still in STOP_PENDING (which the SCM rejects).
func waitForServiceStopped(s service.Service, timeout time.Duration) {
	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		if st, err := s.Status(); err == nil && st == service.StatusStopped {
			return
		}
		time.Sleep(500 * time.Millisecond)
	}
}

func setupWindowsFileLogging() {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	logDir := filepath.Join(pd, "E3Backup", "logs")
	_ = os.MkdirAll(logDir, 0o755)
	logPath := filepath.Join(logDir, "agent.log")

	// Default level is warn (production-safe). The agent runner reads
	// AgentConfig.LogLevel and may relax this with applog.SetLevel later.
	level := applog.LevelWarn
	if v := os.Getenv("E3_AGENT_LOG_LEVEL"); v != "" {
		level = applog.ParseLevel(v)
	}

	if err := applog.Init(applog.Options{
		Path:    logPath,
		MaxSize: 5 * 1024 * 1024,
		Keep:    3,
		Level:   level,
		Mode:    0o600,
	}); err != nil {
		// Fall back to a plain file if rotation init fails.
		if f, ferr := os.OpenFile(logPath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o600); ferr == nil {
			log.SetOutput(f)
			log.SetFlags(log.Ldate | log.Ltime | log.Lmicroseconds)
		}
	}
}
