#!/usr/bin/env bash
# Verify patched Kopia index-fetch parallelism is present and worker tests pass.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "== prepare kopia =="
./scripts/prepare-kopia.sh

echo "== patch constants =="
grep -q 'parallelFetches          = 32' third_party/kopia/repo/content/content_manager.go
grep -q 'indexFetchParallelism = 32' third_party/kopia/repo/blob/s3/s3_storage.go
echo "parallelFetches=32 and S3 idle conns=32 confirmed"

echo "== unit tests =="
go test ./internal/kopia/ -run 'TestKopiaPatch|TestPool' -count=1

echo "verify-kopia-parallel: ok"
