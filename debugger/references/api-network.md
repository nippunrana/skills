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

---

## 2. Probe patterns

### Reproducible curl (`client-request`)

Right-click the request in DevTools → Network tab → "Copy as cURL". Strip auth tokens before sharing. Ask the user to run it from their terminal. If the curl reproduces the bug, the bug is server-side. If the curl works but the in-app call fails, the bug is in how the client builds the request (headers, body serialization, credentials).

```bash
# Trimmed example
curl -X POST 'https://api.example.com/v1/checkout' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <TOKEN>' \
  -d '{"items":[{"id":42,"qty":2}],"coupon":"SUMMER"}' \
  -i  # include response headers
```

If the in-app call ≠ this curl, diff the headers and body. The in-app call is what DevTools "Copy as cURL" actually captures from the real request.

### Client-side request snippet (no source edits)

```javascript
// Paste in console — wraps fetch to log every request and response
(() => {
  const orig = window.fetch;
  window.fetch = async (...args) => {
    const [url, opts = {}] = args;
    const id = Math.random().toString(36).slice(2, 6);
    console.log(`[DEBUG-${id}] →`, opts.method || 'GET', url, opts.body || '');
    const t0 = performance.now();
    try {
      const r = await orig(...args);
      const clone = r.clone();
      const body = await clone.text();
      console.log(`[DEBUG-${id}] ← ${r.status} ${(performance.now()-t0).toFixed(0)}ms`, body.slice(0, 500));
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

Inject `[DEBUG-<id>]` logs at: handler entry, every branch decision, every external call (DB, third-party API, queue), handler exit.

```javascript
// Express / Node — [DEBUG-<id>]
app.post('/checkout', async (req, res) => {
  console.log('[DEBUG-<id>] /checkout in', { body: req.body, userId: req.user?.id }); // [DEBUG-<id>]
  if (!req.body.items?.length) {
    console.log('[DEBUG-<id>] short-circuit: no items'); // [DEBUG-<id>]
    return res.status(400).json({ error: 'no items' });
  }
  const order = await db.orders.create(/*...*/);
  console.log('[DEBUG-<id>] order created', order.id); // [DEBUG-<id>]
  // ...
});
```

```php
// WordPress / PHP — # [DEBUG-<id>]
function my_handler($request) {
  error_log('[DEBUG-<id>] my_handler in: ' . wp_json_encode($request->get_params())); # [DEBUG-<id>]
  // ...
}
```

```python
# Django / FastAPI — # [DEBUG-<id>]
def checkout(request):
    import logging; log = logging.getLogger(__name__)
    log.error(f'[DEBUG-<id>] checkout in body={request.body!r} user={request.user.id}')  # [DEBUG-<id>]
    # ...
```

Use `error_log` / `log.error` (not `console.log` / `print`) on servers so the message lands in the actual server log file, not buried in stdout.

### DB-query logging (`db-query`)

Every framework has a built-in hook for query logging. Use it — it captures both the bound SQL and the timing, and you don't need to instrument every call site.

| Stack | How |
|---|---|
| **WordPress** | `define('SAVEQUERIES', true);` in wp-config.php, then read `$wpdb->queries` |
| **Laravel** | `DB::listen(fn($q) => Log::info('[DEBUG-<id>]', ['sql' => $q->sql, 'bindings' => $q->bindings, 'time_ms' => $q->time]));` |
| **Rails** | `ActiveSupport::Notifications.subscribe('sql.active_record') { \|*, p\| puts "[DEBUG-<id>] #{p[:sql]}" }` |
| **Django** | `from django.db import connection; print(connection.queries)` (DEBUG=True required) |
| **Node + Prisma** | `new PrismaClient({ log: ['query'] })` |
| **Node + Knex** | `knex.on('query', q => console.log('[DEBUG-<id>]', q.sql, q.bindings))` |

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

---

## 4. Fix discipline

- If the bug is server-side, fix the handler. Don't paper over it with client-side retries or fallback data.
- If the bug is in the request the client builds, fix the request builder. Don't make the server tolerate the malformed input.
- After fixing, write a regression test (HTTP-level if integration tests exist, or unit-level for the request builder / handler logic).
- Run Phase 7 cleanup. Server-side `[DEBUG-<id>]` logs in particular have a habit of getting deployed accidentally — `grep -rn "\[DEBUG-" .` is non-negotiable.
