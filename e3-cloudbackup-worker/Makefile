.PHONY: build install test clean

# Build the worker binary
build:
	@echo "Building e3-cloudbackup-worker..."
	@go build -o bin/e3-cloudbackup-worker cmd/worker/main.go
	@echo "Build complete: bin/e3-cloudbackup-worker"

# Install to system paths (requires sudo)
install: build
	@echo "Installing e3-cloudbackup-worker..."
	@sudo cp bin/e3-cloudbackup-worker /usr/local/bin/e3-cloudbackup-worker
	@sudo chown root:root /usr/local/bin/e3-cloudbackup-worker
	@sudo chmod 755 /usr/local/bin/e3-cloudbackup-worker
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

