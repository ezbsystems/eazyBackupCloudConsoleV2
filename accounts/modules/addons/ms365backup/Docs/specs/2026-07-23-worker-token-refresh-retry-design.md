# Worker token refresh retry design

## Problem

Production child `1780312f-80f8-423e-9154-2f3caa2fe90d` received a Graph 401 and requested a fresh token from `ms365_worker_graph_token.php`. WHMCS reached Microsoft’s OAuth token endpoint but the TCP connection failed with cURL error 7, so WHMCS returned HTTP 500. The worker made only one token-refresh request and converted that transient control-plane failure into a terminal `graph 401 after token refresh` child error.

Four subsequent token calls succeeded, and the same child completed automatically on retry in 765 ms. This rules out invalid credentials, permanent authorization failure, Graph throttling, Kopia, worker resource exhaustion, and worker crash.

## Selected design

Change only `api.Client.RefreshGraphToken` to call the existing context-aware `postWithRetry` helper with three attempts instead of the single-attempt `post` helper.

This retries the complete token-refresh transaction when the worker sees a transient control-plane HTTP 500 or transport error. Existing delays are bounded and context-aware. If all attempts fail, the original error is returned and current terminal handling remains unchanged.

The change does not alter:

- token endpoint authentication or authorization;
- PHP `TokenProvider` behavior for other callers;
- Graph 401 handling after a successful refresh;
- batch retry limits or lease ownership;
- customer-facing error sanitization.

## Diagnostics and tests

Temporary session `6f5f7c` diagnostics will record refresh attempt number, success/failure, HTTP status when available, and elapsed time. They must not record run IDs, tenant IDs, URLs, tokens, request bodies, or response bodies.

A Go regression test will serve HTTP 500 for the first token-refresh call and a valid token on the second. Before the fix it must fail after one call; after the fix it must return the token with exactly two calls. Existing API and worker tests plus `go build ./...` must pass.

## Deployment and verification

Publish the next unique worker patch release and roll it across production. Verify all nodes reach the release, the active batch continues progressing, and the recovered child remains `success`/`done`. No manual child restart is performed because the requested workload already restarted automatically and succeeded.

Keep diagnostics until a production token refresh is observed or the operator confirms the active run is healthy. Then remove diagnostics and publish a cleanup patch if they reached production.
