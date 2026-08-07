.PHONY: build build-windows test

build:
	mkdir -p bin
	go build -trimpath -o bin/e3-cloudnas ./cmd/e3-cloudnas

test:
	go test ./... -count=1

# Run this target on the Windows build host with the WinFsp Core and Developer
# packages installed. The AgentBuild windows_build step runs on Linux and must
# not invoke this target; copy the resulting bin/e3-cloudnas.exe back into this
# tree before WindowsStage runs so it can be bundled by Inno Setup.
# Before invoking make from cmd.exe:
#   set CGO_ENABLED=1
# A working CGO C compiler must be available on PATH.
build-windows:
	mkdir -p bin
	go build -trimpath -ldflags="-s -w" -o bin/e3-cloudnas.exe ./cmd/e3-cloudnas
