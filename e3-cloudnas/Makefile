.PHONY: build build-windows test

build:
	mkdir -p bin
	go build -trimpath -o bin/e3-cloudnas ./cmd/e3-cloudnas

test:
	go test ./... -count=1

# Run this target on the Windows build host with WinFsp Developer headers and a
# MinGW gcc on PATH. Agent Builds use the windows_build_cloudnas step (remote
# CGO) instead of invoking this from Linux — do not cross-compile from Linux.
# Example (matches the AgentBuild host defaults):
#   set PATH=C:\Go\bin;C:\Tools\mingw64\bin;%PATH%
#   set CGO_ENABLED=1
#   set CPATH=C:\WinFspInc
#   set CGO_CFLAGS=-IC:\WinFspInc
build-windows:
	mkdir -p bin
	go build -trimpath -ldflags="-s -w" -o bin/e3-cloudnas.exe ./cmd/e3-cloudnas
