# Kopia patches (v0.23.1)

Patches applied to `github.com/kopia/kopia` before worker builds. Regenerate the patched tree with:

```bash
./scripts/prepare-kopia.sh
```

| Patch | Purpose |
|-------|---------|
| `0001-index-fetch-parallel-32.patch` | Raise `parallelFetches` from 5 → 32 for cold index blob downloads |
| `0002-s3-transport-idle-conns.patch` | Raise MinIO HTTP `MaxIdleConnsPerHost` to match fetch parallelism |

`go.mod` uses `replace github.com/kopia/kopia => ./third_party/kopia`.
