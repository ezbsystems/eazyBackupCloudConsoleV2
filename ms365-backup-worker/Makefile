BINARY=ms365-backup-worker
VERSION?=0.4.45
LDFLAGS=-ldflags "-X github.com/eazybackup/ms365-backup-worker/internal/version.Version=$(VERSION)"
KOPIA_PREPARE=./scripts/prepare-kopia.sh

.PHONY: build test tidy licenses prepare-kopia

prepare-kopia:
	$(KOPIA_PREPARE)

build: prepare-kopia
	go build $(LDFLAGS) -o bin/$(BINARY) ./cmd/worker

test: prepare-kopia
	go test ./...

tidy:
	go mod tidy

licenses:
	cd ../legal && go run ./cmd/generate-licenses --project ms365-backup-worker

install: build
	install -m 0755 bin/$(BINARY) /usr/local/bin/$(BINARY)
