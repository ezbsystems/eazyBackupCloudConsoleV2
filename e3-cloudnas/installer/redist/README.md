# WinFsp redistribution input

Place the approved x64-capable WinFsp MSI here as `winfsp.msi`. The MSI is a
build input and is intentionally excluded from Git.

Current candidate fetch URL:

<https://github.com/winfsp/winfsp/releases/download/v2.2B3/winfsp-2.2.26194.msi>

Expected SHA-256: `7b41020618cdcc33d699d0e15c1df660f0762a09b57080049c565857ac00bd9d`

Before use, confirm the URL and checksum against the selected release at
<https://github.com/winfsp/winfsp/releases>. Store the verified MSI in a
trusted build-host cache or Git LFS; do not commit the multi-megabyte binary.
