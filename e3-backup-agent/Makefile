.PHONY: build build-windows build-agent-windows build-tray-windows install test clean deps lint

# Build the agent binary (linux/amd64 by default)
build:
	@echo "Building e3-backup-agent..."
	@mkdir -p bin
	@go build -o bin/e3-backup-agent ./cmd/agent
	@echo "Build complete: bin/e3-backup-agent"

# Cross-compile Windows agent binary (service only)
build-agent-windows:
	@echo "Building e3-backup-agent (windows/amd64)..."
	@mkdir -p bin
	@CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o bin/e3-backup-agent.exe ./cmd/agent
	@echo "Build complete: bin/e3-backup-agent.exe"

# Cross-compile Windows tray binary (GUI app)
build-tray-windows:
	@echo "Building e3-backup-tray (windows/amd64)..."
	@mkdir -p bin
	@CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w -H=windowsgui" -o bin/e3-backup-tray.exe ./cmd/tray
	@echo "Build complete: bin/e3-backup-tray.exe"

# Cross-compile ALL Windows binaries (agent + tray) for installer
build-windows: build-agent-windows build-tray-windows
	@echo ""
	@echo "=== Windows build complete ==="
	@echo "  bin/e3-backup-agent.exe  (Windows service)"
	@echo "  bin/e3-backup-tray.exe   (Tray helper + enrollment UI)"
	@echo ""
	@echo "Next: Copy to Windows and compile installer with Inno Setup"
	@echo "See: docs/LOCAL_AGENT_BUILD.md"

# Install to system paths (requires sudo)
install: build
	@echo "Installing e3-backup-agent..."
	@sudo cp bin/e3-backup-agent /usr/local/bin/e3-backup-agent
	@sudo chown root:root /usr/local/bin/e3-backup-agent
	@sudo chmod 755 /usr/local/bin/e3-backup-agent
	@echo "Installation complete"

# Run tests
test:
	@echo "Running tests..."
	@go test ./...

# Clean build artifacts
clean:
	@echo "Cleaning build artifacts..."
	@rm -rf bin/
	@go clean
	@echo "Clean complete"

# Download dependencies
deps:
	@echo "Downloading dependencies..."
	@go mod download
	@go mod tidy
	@echo "Dependencies updated"

# Run linter (if golangci-lint is installed)
lint:
	@if command -v golangci-lint >/dev/null 2>&1; then \
		golangci-lint run; \
	else \
		echo "golangci-lint not installed, skipping..."; \
	fi

