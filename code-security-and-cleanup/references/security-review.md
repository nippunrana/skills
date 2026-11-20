# Security Review

## Contents
1. Threat model
2. Finding candidates
3. Verifying a candidate
4. Verdict, severity and confidence
5. Labels: CWE and OWASP Top 10:2025
6. What is not a finding
7. Secrets
8. Dependencies
9. Hardening
10. Diff reviews

## 1. Threat model

A few lines in the report, so the user can correct them:
- what the application does, and who uses it: anonymous visitors, logged-in users, each role, other services;
- where outside input enters: routes and handlers, forms, APIs, webhooks, file uploads, imported files, command-line arguments, queues, and data that users wrote earlier and the app reads back;
- what is worth stealing or breaking: accounts, personal data, payments, secrets, the server itself;
- protections already in place: the framework's escaping, query builders, authentication middleware, CSRF tokens, permission helpers.

## 2. Finding candidates

- **List every entry point first,** from where routes and handlers are registered, not from file names. The list goes in the coverage section.
- **For each entry point ask three questions:** who can reach it, what it checks (login, role or permission, and whether the object belongs to the caller), and what it does with its input.
- **Follow each input to where it is used:** queries, shell and process calls, file paths and includes, outbound requests, HTML and other output, redirects, deserialisation, code evaluation, comparisons of tokens or hashes, and logs.
- **Look for missing checks as well as bad calls.** A state-changing action with no permission or ownership check is a finding even if every call in it is safe. A CSRF token stops forged requests; it is not a permission check.
- **Keep low-confidence candidates** at this stage. Verification removes the noise.
- **A scanner hit is a candidate, and a clean scan proves nothing.** Most scanners do not follow data across functions or files.

## 3. Verifying a candidate

Try to disprove each candidate. Record:
- **Source:** the exact value an attacker controls (parameter, header, body field, file name or content).
- **Reach:** who can send it. Read the route registration and its permission check; do not infer from names.
- **Protections on the path:** validation, type casts, allow-lists, escaping, bound parameters, permission checks. Read each helper's code. Check that it applies to this value, in this output context, on every path.
- **Sink:** the dangerous operation, with file and line.
- **Impact:** what the attacker concretely gains.

If one link cannot be settled from the code, for example it depends on server configuration, database content or a service outside the repository, the verdict is NEEDS VALIDATION and the blocker is named. If a protection holds, the verdict is REJECTED.

Running code to confirm a finding is allowed only where it is safe: in the apply stage, or with the user's permission, and never against production or outside systems.

## 4. Verdict, severity and confidence

- **CONFIRMED:** every link in section 3 traced, with file and line.
- **NEEDS VALIDATION:** plausible, with one named blocker. No severity yet; say what would settle it.
- **REJECTED:** a protection holds, or the input is not attacker-controlled. One line under "checked and found clean".

**Severity** comes from preconditions and impact, not from the category:

| Severity | Typical shape |
| :--- | :--- |
| Critical | Anyone, without logging in, can run code, read or change other people's data, or take over accounts; or a live credential is exposed |
| High | Any logged-in user can do one of those to other users or escalate privileges; or anonymous access to sensitive data |
| Medium | Needs a particular role, the victim's interaction (clicking a link, visiting a page), or exposes limited data |
| Low | Needs admin or local access already, or leaks minor information |

**Confidence** (high, medium, low) is separate: how sure you are that the trace is right.

## 5. Labels: CWE and OWASP Top 10:2025

Give the CWE number first, then the OWASP 2025 category: `CWE-89 · A05:2025 Injection`. CWE numbers do not move between OWASP editions. If a CWE is not in this table and you are not sure of its category, give the CWE alone.

| Category | CWEs |
| :--- | :--- |
| A01:2025 Broken Access Control | CWE-22 path traversal, CWE-352 CSRF, CWE-601 open redirect, CWE-862 missing authorization, CWE-918 SSRF |
| A02:2025 Security Misconfiguration | CWE-489 debug code left active, CWE-260 password in a config file; missing security headers |
| A03:2025 Software Supply Chain Failures | CWE-1104 unmaintained third-party component, CWE-1395 dependency on a vulnerable third-party component |
| A04:2025 Cryptographic Failures | CWE-328 weak hash, CWE-916 weak password hashing |
| A05:2025 Injection | CWE-20 improper input validation, CWE-78 OS command, CWE-79 XSS, CWE-89 SQL, CWE-94 code, CWE-98 file inclusion |
| A07:2025 Authentication Failures | CWE-306 missing authentication, CWE-798 hard-coded credentials, CWE-1392 default credentials |
| A08:2025 Software or Data Integrity Failures | CWE-502 unsafe deserialisation |
| A09:2025 Security Logging and Alerting Failures | CWE-532 secrets in logs, CWE-778 missing audit logging |

Loose comparison of secrets (CWE-1024) has no OWASP category.

## 6. What is not a finding

- **A pattern without a path.** A risky-looking call is a finding only when attacker-controlled data reaches it and the result matters. Judge the data and who can reach it, not the function name.
- **A theory that needs the attacker to own the server already,** unless the application treats that role as untrusted (for example site admins on a shared network).
- **A missing hardening item with no exploit path.** It goes in the hardening list (section 9).
- **A security-sounding name.** Check the code, both ways: a helper called `sanitize` may not, and a plain cast may be all the protection needed.

## 7. Secrets

- **Search the current files and the git history.** To locate history hits without printing them, list matching commits and files only, for example `git log --all --oneline --name-only -G '<pattern>'`. Then read just enough to confirm the kind of secret.
- **Report:** the kind of secret, where it is (file and line, or commit and file), whether it is still in the current files, and at most its first four characters. Never the value.
- **Advise in this order:** revoke or rotate it, then remove it from the code and load it from the environment or a secret store, then decide with the owner whether to rewrite history.

## 8. Dependencies

- **Advisories come only from a tool's output** (the package manager's audit command, or a scanner that reads the lockfile). Most need the network; ask before running them. Never name a CVE, advisory or "known vulnerable" version from memory.
- **If no audit can run:** say "dependency audit not run" and why. You may list pinned versions that look old as "check with a dependency audit", without claiming they are vulnerable.
- Note a missing lockfile, or a lockfile that disagrees with the manifest.

## 9. Hardening

Items with no exploit path today, in their own list:
- security headers (`Strict-Transport-Security`, `Content-Security-Policy` with `frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`), cookie flags, debug settings, permissive CORS, missing rate limits on login or costly endpoints;
- headers are often set by the web server or CDN outside the repository. Then the item is "confirm at the server", not a finding;
- a new Content-Security-Policy starts in report-only mode.

## 10. Diff reviews

- **Scope:** the change (`git diff HEAD` plus untracked new files, or `git diff <base>...HEAD` for a branch), and the code it calls and that calls it.
- **Report what the change introduces first.** Problems in code the change did not touch go in a separate, short "already present before this change" section.
- **A change that makes an old weakness reachable** is part of the change.
- **Still report an old Critical or High,** even outside the scope, in that separate section.
