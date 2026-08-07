package main

import (
	"context"
	"flag"
	"fmt"
	"log"
	"net"
	"os"
	"os/signal"
	"syscall"

	"github.com/ezbsystems/e3-cloudnas/internal/api"
	"github.com/ezbsystems/e3-cloudnas/internal/discovery"
	"github.com/ezbsystems/e3-cloudnas/internal/mount"
)

const version = "0.1.0-dev"

func main() {
	programData := flag.String("program-data", os.Getenv("ProgramData"), "ProgramData root (defaults to ProgramData env)")
	flag.Parse()

	if *programData == "" {
		log.Fatal("program data directory is required: set ProgramData or pass -program-data")
	}

	manager := mount.NewManager()

	listener, err := api.NewServer(manager, "", version).Listen()
	if err != nil {
		log.Fatalf("listen: %v", err)
	}

	addr, ok := listener.Addr().(*net.TCPAddr)
	if !ok {
		log.Fatal("unexpected listener address type")
	}

	token, err := discovery.Write(*programData, addr.Port, version)
	if err != nil {
		log.Fatalf("write discovery: %v", err)
	}

	server := api.NewServer(manager, token, version)

	errCh := make(chan error, 1)
	go func() {
		errCh <- server.Serve(listener)
	}()

	log.Printf("e3-cloudnas %s listening on http://127.0.0.1:%d", version, addr.Port)

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	select {
	case <-ctx.Done():
		log.Printf("shutting down")
	case err := <-errCh:
		if err != nil {
			log.Fatalf("serve: %v", err)
		}
	}
}

func init() {
	flag.Usage = func() {
		fmt.Fprintf(os.Stderr, "Usage: %s [-program-data DIR]\n", os.Args[0])
		flag.PrintDefaults()
	}
}
