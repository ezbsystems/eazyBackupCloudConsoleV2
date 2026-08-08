#!/usr/bin/env bash
# Sync github.com/kopia/kopia@v0.23.1 from the module cache and apply eazybackup patches.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="v0.23.1"
MODCACHE="$(go env GOMODCACHE)"
SRC="${MODCACHE}/github.com/kopia/kopia@${VERSION}"
DEST="${ROOT}/third_party/kopia"
PATCH_DIR="${ROOT}/third_party/kopia-patches"

if [[ ! -d "$SRC" ]]; then
  echo "fetching kopia ${VERSION} into module cache..." >&2
  (cd "$ROOT" && go mod download "github.com/kopia/kopia@${VERSION}")
fi

if [[ ! -d "$SRC" ]]; then
  echo "kopia module not found at ${SRC}" >&2
  exit 1
fi

rm -rf "$DEST"
cp -a "$SRC" "$DEST"

shopt -s nullglob
patches=("$PATCH_DIR"/*.patch)
if ((${#patches[@]} == 0)); then
  echo "no patches in ${PATCH_DIR}" >&2
  exit 1
fi

for patch in "${patches[@]}"; do
  echo "applying $(basename "$patch")..." >&2
  patch -p1 -d "$DEST" < "$patch"
done

find "$DEST" -name '*.orig' -delete 2>/dev/null || true

echo "prepared ${DEST}" >&2
