BINARY=ms365-backup-worker
VERSION?=0.1.0
LDFLAGS=-ldflags "-X github.com/eazybackup/ms365-backup-worker/internal/version.Version=$(VERSION)"

.PHONY: build test tidy

build:
	go build $(LDFLAGS) -o bin/$(BINARY) ./cmd/worker

test:
	go test ./...

tidy:
	go mod tidy

install: build
	install -m 0755 bin/$(BINARY) /usr/local/bin/$(BINARY)
