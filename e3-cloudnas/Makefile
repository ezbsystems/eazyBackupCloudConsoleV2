.PHONY: build build-windows test

build:
	mkdir -p bin
	go build -trimpath -o bin/e3-cloudnas ./cmd/e3-cloudnas

test:
	go test ./... -count=1

# Run this target from a Windows shell with the WinFsp Core and Developer
# packages installed:
#   set CGO_ENABLED=1
# A working CGO C compiler must be available on PATH.
build-windows:
	mkdir -p bin
	go build -trimpath -ldflags="-s -w" -o bin/e3-cloudnas.exe ./cmd/e3-cloudnas
