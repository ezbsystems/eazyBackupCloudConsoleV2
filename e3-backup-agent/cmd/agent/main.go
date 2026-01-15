package main

import (
	"flag"
	"log"
	"os"
	"os/signal"
	"path/filepath"
	"runtime"
	"syscall"

	"github.com/kardianos/service"
	"github.com/your-org/e3-backup-agent/internal/agent"
)

func main() {
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
	cfg, err := agent.LoadConfig(*configPath)
	if err != nil {
		log.Fatalf("failed to load config: %v", err)
	}

	log.Printf("e3-backup-agent starting (client_id=%s, agent_id=%s, api=%s)", cfg.ClientID, cfg.AgentID, cfg.APIBaseURL)

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
	cfg, err := agent.LoadConfig(p.configPath)
	if err != nil {
		log.Printf("service: failed to load config: %v", err)
		// Return so SCM reports a start failure; log file will contain the reason.
		return
	}
	log.Printf("service: e3-backup-agent starting (client_id=%s, agent_id=%s, api=%s)", cfg.ClientID, cfg.AgentID, cfg.APIBaseURL)
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
		err = service.Control(s, "install")
	case "uninstall":
		err = service.Control(s, "uninstall")
	case "start":
		err = service.Control(s, "start")
	case "stop":
		err = service.Control(s, "stop")
	default:
		log.Fatalf("unknown service command: %s", cmd)
	}
	if err != nil {
		log.Fatalf("service command failed: %v", err)
	}
	log.Printf("service command %s: ok", cmd)
}

func setupWindowsFileLogging() {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	logDir := filepath.Join(pd, "E3Backup", "logs")
	_ = os.MkdirAll(logDir, 0o755)
	logPath := filepath.Join(logDir, "agent.log")

	f, err := os.OpenFile(logPath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o600)
	if err != nil {
		return
	}
	log.SetOutput(f)
	log.SetFlags(log.Ldate | log.Ltime | log.Lmicroseconds)
}
