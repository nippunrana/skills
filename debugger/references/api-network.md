# Domain: API, Network & Backend Data Flow

Use this reference when the bug crosses the network boundary or lives on the server: failing fetch/AJAX, wrong payload shape, CORS, 4xx/5xx, request times out, server returns 200 but the side effect never happened (no DB row, no email, no queued job), a query returns the wrong rows.

API/network bugs almost always involve **two sides** — the client view and the server view of the same request. Diagnose both. The client says "I sent X and got Y"; the server says "I received A and did B". The gap between X→A or B→Y is where the bug lives.

---

## 1. Sub-categories

- `client-request` — does the client actually send what you think it sends?
- `server-receipt` — does the server receive what the client sent, and does it route to the right handler?
- `handler-flow` — inside the handler, what happens? Which branches run? What does the handler decide to do?
- `db-query` — is the query (or ORM call) returning what you expect?
- `response-shape` — does the response payload match what the client parser expects?
- `auth-cors` — is the request being blocked or rejected before it hits the handler?
- `trace-correlation` — bridge client and server logs by following an existing trace/correlation ID across the network boundary

---

## 2. Probe patterns

### Reproducible curl (`client-request`)

Right-click the request in DevTools → Network tab → "Copy as cURL" (this step is inherently user-side — DevTools only runs in their browser). Once you have the command: if you have shell access, run it yourself per Phase 4's Agentic Execution path — don't ask the user to do it. Only ask the user to run it themselves if you're in a read-only environment with no shell access. Strip auth tokens before *sharing* the command in chat (not before running it — the token is needed to reproduce the request). If the curl reproduces the bug, the bug is server-side. If the curl works but the in-app call fails, the bug is in how the client builds the request (headers, body serialization, credentials).

```bash
# Trimmed example
curl -X POST 'https://api.example.com/v1/checkout' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <TOKEN>' \
  -d '{"items":[{"id":42,"qty":2}],"coupon":"SUMMER"}' \
  -i  # include response headers
```

If the in-app call ≠ this curl, diff the headers and body. The in-app call is what DevTools "Copy as cURL" actually captures from the real request.

### Trace correlation (`trace-correlation`)

Modern distributed systems propagate a request ID across the network boundary so the same logical request can be tracked from browser → gateway → service → worker. **Check for these headers first** — if they're present, you can skip injecting your own correlation tag and pivot straight to the server logs for the exact failing invocation.

**Where to look (Network tab → Headers, both Request and Response):**

| Header | Source | Notes |
|---|---|---|
| `traceparent`, `tracestate` | W3C Trace Context | Format: `00-<trace-id>-<span-id>-<flags>`. Used by OpenTelemetry, Datadog, Honeycomb, Sentry, Jaeger. |
| `X-Request-ID`, `X-Correlation-ID`, `Request-Id` | Custom / framework default | Often added by gateways (Cloudflare, Nginx, Heroku, AWS ALB) or frameworks (Rails `ActionDispatch::RequestId`, ASP.NET `TraceIdentifier`, Express `express-request-id`). |
| `X-Amzn-Trace-Id` | AWS X-Ray | Set by ALB / API Gateway. |
| `X-Cloud-Trace-Context` | GCP Cloud Trace | Set by GCP load balancers. |
| `X-B3-TraceId`, `X-B3-SpanId` | B3 / Zipkin | Common in service meshes (Istio, Linkerd). |

**How to use:**

1. In DevTools → Network → click the failing request → Headers. Scan both request and response headers for any of the above.
2. Copy the trace/correlation value. For W3C `traceparent`, extract the **middle segment** — that's the searchable trace-id:
   ```
   traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
                   └──────── trace-id (search this) ────────┘
   ```
3. Search wherever your server logs land — exact match on the ID:
   ```bash
    # Search log files (using shell search tools, log viewer, or your native tools)
    grep '4bf92f3577b34da6a3ce929d0e0e4736' /var/log/app/*.log
    grep -r '4bf92f3577b34da6a3ce929d0e0e4736' wp-content/debug.log

   # Aggregators (query syntax varies)
   # Datadog:    @trace_id:4bf92f3577b34da6a3ce929d0e0e4736
   # Sentry:     trace:4bf92f3577b34da6a3ce929d0e0e4736
   # Honeycomb:  trace.trace_id = "4bf92f3577b34da6a3ce929d0e0e4736"
   # Loki:       {app="api"} |= "4bf92f3577b34da6a3ce929d0e0e4736"
   # CloudWatch: fields @timestamp, @message | filter @message like /4bf92f35.../
   ```
4. The matching lines pin the exact handler invocation, with surrounding context (timing, errors, downstream DB queries, queued jobs, third-party calls) — without injecting a single probe.

**If no trace ID is present** (system isn't propagating context), in order of preference:

1. **Enable the framework's built-in instrumentation.** Most backend frameworks ship dormant request-ID tagging (a config flag or a first-party middleware package) — check current docs for the framework in use. Configure once, get correlation forever.
2. **Inject a one-off debug trace header** for this investigation only. Tag both client and server sides per `[DEBUG-<id>]` protocol; remove both in Phase 7.

> **Caution — this probe is not side-effect-free for cross-origin requests.** Adding a custom header turns a "simple" cross-origin request into one that requires a CORS preflight (OPTIONS). If the server's CORS config doesn't already allow-list `X-Debug-Trace`, the probe *creates* a CORS failure that wasn't there before, masking the bug you're chasing. It can also break requests signed with HMAC/SigV4, since the signature was computed without this header. Use this only for same-origin requests, or first confirm the server's `Access-Control-Allow-Headers` will accept the new header.

```javascript
// Client — paste in console to attach a per-session debug header to every fetch
const DEBUG_TRACE = 'dbg-' + Math.random().toString(36).slice(2, 10);
const orig = window.fetch;
window.fetch = (input, init = {}) => {
  const request = new Request(input, init); // merges init even when input is already a Request
  request.headers.set('X-Debug-Trace', DEBUG_TRACE);
  return orig(request);
};
console.log('[DEBUG-<id>] session trace:', DEBUG_TRACE);
```

Server side: at the earliest point every request passes through (the framework's middleware/interceptor layer, not each individual handler), read the `X-Debug-Trace` header and log it alongside the method/path — one line, tagged `[DEBUG-<id>]`. Registering it once at the middleware layer means you don't have to touch every handler.

**Worker / queue boundaries** (BullMQ, Celery, Sidekiq, SQS, RabbitMQ) are where trace context usually gets dropped — the producer enqueues a job without attaching the trace, so the consumer's logs are orphaned. If the bug spans a job, verify the producer writes the trace into the job payload and the consumer reads it back into its logging context. This handoff is a common defect site, not just an observability gap.

### Client-side request snippet (no source edits)

```javascript
// Paste in console — wraps fetch to log every request and response.
// This snippet is console-only and discarded on refresh (see below), so it's
// exempt from the "one id per hypothesis round" rule — a fresh id per request
// just makes the log easier to read; it doesn't affect cleanup.
(() => {
  const orig = window.fetch;
  window.fetch = async (input, init) => {
    const id = Math.random().toString(36).slice(2, 6);
    const method = (input instanceof Request ? input.method : (init?.method || 'GET'));
    const url = (input instanceof Request ? input.url : input);
    
    let reqBody = '';
    if (init?.body) {
      reqBody = typeof init.body === 'string' ? init.body : '[Payload Body]';
    } else if (input instanceof Request && input.body) {
      reqBody = '[Request Body]';
    }
    
    console.log(`[DEBUG-${id}] → ${method} ${url}`, reqBody);
    const t0 = performance.now();
    try {
      const r = await orig(input, init);
      const clone = r.clone();
      // Read response body asynchronously so we don't block the caller from consuming the stream
      clone.text().then(body => {
        console.log(`[DEBUG-${id}] ← ${r.status} ${(performance.now()-t0).toFixed(0)}ms`, body.slice(0, 500));
      }).catch(() => {
        console.log(`[DEBUG-${id}] ← ${r.status} ${(performance.now()-t0).toFixed(0)}ms [unreadable body]`);
      });
      return r;
    } catch (e) {
      console.log(`[DEBUG-${id}] ✗ ${(performance.now()-t0).toFixed(0)}ms`, e);
      throw e;
    }
  };
  console.log('[Debug] fetch wrapper installed. Refresh to remove.');
})();
```

Refresh removes it — no cleanup needed for this probe.

### Server-handler instrumentation (`server-receipt`, `handler-flow`)

Inject `[DEBUG-<id>]` logs at: handler entry, every branch decision, every external call (DB, third-party API, queue), handler exit. Log the inbound payload's *keys/shape*, not the raw body (see Probe Rule §4 — don't log secrets), and the resolved user/session identity if one exists.

```javascript
// Generic shape — [DEBUG-<id>]
function handler(request) {
  log('[DEBUG-<id>] handler in', { bodyKeys: Object.keys(request.body || {}), user: request.user?.id }); // [DEBUG-<id>]
  if (!isValid(request.body)) {
    log('[DEBUG-<id>] short-circuit: invalid body'); // [DEBUG-<id>]
    return respond(400, { error: 'invalid body' });
  }
  const result = doTheWork(request.body);
  log('[DEBUG-<id>] handler done', { resultId: result.id }); // [DEBUG-<id>]
  return respond(200, result);
}
```

Use the runtime's server-side logging channel rather than an unguaranteed stdout print, so the message reliably lands in the log instead of being silently dropped when stdout is discarded (e.g. behind a FastCGI/PHP-FPM process, or a Python WSGI worker with stdout unbuffered/redirected). Most server runtimes distinguish a "write to stdout" call (`print`, bare `puts`) from a "write to the configured log" call (a logger object, `error_log`-style function) — prefer the latter unless you've confirmed the process manager captures stdout as the server log (true for Node under pm2/Docker/systemd, often true for containerized services, not reliably true for classic PHP/CGI or WSGI setups).

### DB-query logging (`db-query`)

Every ORM/database layer ships a built-in hook or flag for query logging — enable it rather than instrumenting each call site by hand; it captures both the bound SQL and the timing in one place. Two shapes exist, and mixing them up produces empty or stale-looking output:

- **Live/streaming hooks** (a callback or listener fired on each query as it runs) — the common form for backend-framework ORMs. If you paste the registration code inside a namespaced/scoped class, the framework's facade or service import may need to be fully qualified — an unqualified reference can resolve to the wrong namespace and fail with a "class/name not found" style error.
- **Read-after-execution buffers** (a list/array the runtime fills in as queries execute, gated behind a debug flag) — common in CMS and dev-mode ORM configs. Read the buffer *after* the suspect code has run (end of request, a shutdown/teardown hook) — reading it at handler entry returns nothing, because nothing has executed yet.

Either way: enabling the hook is a config change, not a source edit — record the original flag value in the ledger (protocol §3) so Phase 7 can revert it or, for a persistent debug-log flag, ask the user whether to keep it enabled (protocol §5).

For slow queries, prepend `EXPLAIN ANALYZE` (Postgres/MySQL) to the suspect query to get the execution plan.

### Response shape diff (`response-shape`)

When the API "works" but the client crashes parsing the response:

```javascript
// In console — diff expected keys against actual
const expected = ['id', 'total', 'items', 'customer'];
fetch('/api/order/123').then(r => r.json()).then(o => {
  const actual = Object.keys(o);
  console.log('missing:', expected.filter(k => !actual.includes(k)));
  console.log('extra:',   actual.filter(k => !expected.includes(k)));
  console.log('actual payload:', o);
});
```

Common causes: serializer changed, field renamed, nested key flattened, null where an array was expected.

### Auth / CORS / preflight (`auth-cors`)

Check the **Network tab** in DevTools for the *preflight* (OPTIONS) request, not just the actual request. CORS failures show up as the OPTIONS being missing or returning the wrong `Access-Control-Allow-*` headers. The actual request never fires when the preflight fails — DevTools shows it as "CORS error" in the console.

For auth failures, check whether `Authorization` / `Cookie` headers are actually being sent (Network → Request Headers). Common causes: `credentials: 'omit'` in fetch options, cross-origin cookie blocked, token expired.

---

## 3. Signals to look for in the returned data

- **Client sent X, server received A ≠ X** → bug is in client request building (serializer, headers, URL params).
- **Server received correct payload, returns 200, but side effect missing** → handler short-circuited silently. Add a probe at every `return` and every conditional. Look for empty catch blocks swallowing errors.
- **Handler logs reach a `return` early** → the gate above it is wrong. Don't add a workaround in the gate; fix what makes the condition true.
- **DB query log shows wrong WHERE/JOIN** → the bug is in the query builder, not the data.
- **DB query log is missing entirely** → the code path never reached the DB call. Trace upstream.
- **Status 200 but response body is `null` / `{}`** → handler returned before populating the response. The probe at the handler exit will show what was about to be returned.
- **CORS preflight 4xx** → server-side CORS config, not client code.
- **`Authorization` header missing** → fetch credentials mode or token-injection middleware.
- **Trace ID found in server logs** → you have the exact handler invocation; read the surrounding lines for branch decisions, downstream calls, and the final response.
- **Trace ID present on the request but absent from server logs** → the request never reached the logging code path. Either an upstream layer (gateway, proxy, auth middleware) rejected it, or the trace isn't being propagated into the logger's context — check middleware order.
- **Trace propagates browser → API but disappears at a worker/job boundary** → the producer isn't attaching trace context to the job payload. The bug may *be* the missing propagation, not a downstream defect.

---

## 4. Fix discipline

- If the bug is server-side, fix the handler. Don't paper over it with client-side retries or fallback data.
- If the bug is in the request the client builds, fix the request builder. Don't make the server tolerate the malformed input.
- After fixing, write a regression test (HTTP-level if integration tests exist, or unit-level for the request builder / handler logic).
- Run Phase 7 cleanup. Server-side `[DEBUG-<id>]` logs in particular have a habit of getting deployed accidentally — verifying that searching the codebase for "[DEBUG-" returns zero matches using your native search tool is non-negotiable.
