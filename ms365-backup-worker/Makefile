BINARY=ms365-backup-worker
VERSION?=0.3.26
LDFLAGS=-ldflags "-X github.com/eazybackup/ms365-backup-worker/internal/version.Version=$(VERSION)"

.PHONY: build test tidy licenses

build:
	go build $(LDFLAGS) -o bin/$(BINARY) ./cmd/worker

test:
	go test ./...

tidy:
	go mod tidy

licenses:
	cd ../legal && go run ./cmd/generate-licenses --project ms365-backup-worker

install: build
	install -m 0755 bin/$(BINARY) /usr/local/bin/$(BINARY)
