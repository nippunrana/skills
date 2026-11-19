# OWASP Security Checklist for PHP/JS Code Audits

This reference provides detection patterns for common security issues found during
dead code audits. Each item includes a grep pattern, severity, and remediation action.

Categories follow the OWASP Top 10:2025. Each item also carries its CWE number, which
does not change between OWASP editions. Tag findings with both.

---

## Table of Contents
1. [A01:2025 Broken Access Control](#a012025-broken-access-control)
2. [A02:2025 Security Misconfiguration](#a022025-security-misconfiguration)
3. [A04:2025 Cryptographic Failures](#a042025-cryptographic-failures)
4. [A05:2025 Injection](#a052025-injection)
5. [A07:2025 Authentication Failures](#a072025-authentication-failures)
6. [A08:2025 Software or Data Integrity Failures](#a082025-software-or-data-integrity-failures)
7. [A09:2025 Security Logging and Alerting Failures](#a092025-security-logging-and-alerting-failures)
8. [Checks With No OWASP Category](#checks-with-no-owasp-category)
9. [Usage Notes](#usage-notes)

---

## A01:2025 Broken Access Control

### Missing Authentication on Endpoints
**Severity**: High
**CWE**: CWE-862 (missing authorization). A missing login check is CWE-306, which OWASP files under A07.
**Detection patterns**:
```
# PHP: AJAX handlers without auth checks
wp_ajax_nopriv_
\$_GET\[|\$_POST\[|\$_REQUEST\[  (without preceding capability/nonce check)
```
**Action**: If the endpoint is dead, remove it. If live, add authentication.

### Missing Nonce/CSRF Verification
**Severity**: Medium
**CWE**: CWE-352
**Detection patterns**:
```
# PHP: Form handlers without nonce
admin_post_  (without wp_verify_nonce nearby)
wp_ajax_     (without check_ajax_referer nearby)

# General: Form submission without token
<form.*method="post"  (without hidden csrf/nonce input)
```
**Action**: Dead handlers should be removed entirely — they are exploitable attack surface. A nonce only stops forged requests. It does not prove the user is allowed to act, so pair it with `current_user_can()`.

### Capability Checks Missing
**Severity**: High
**CWE**: CWE-862
**Detection patterns**:
```
# PHP WordPress: Admin actions without capability check
current_user_can  (should appear before any privileged action)
# Look for admin-panel code that lacks:
if.*current_user_can|if.*is_admin
```
**Action**: Remove dead admin code. For live code, add `current_user_can()` checks.

### Unvalidated Redirects
**Severity**: Medium
**CWE**: CWE-601
**Detection patterns**:
```
header\(.*Location.*\$_   (redirect using user input)
wp_redirect\(.*\$_        (WordPress redirect with user input)
window\.location.*=.*(params|query|search|hash)
```
**Action**: Remove dead redirects. Live ones need URL validation via allowlist. In WordPress use `wp_safe_redirect()` followed by `exit`.

---

## A02:2025 Security Misconfiguration

### Debug Mode in Production
**Severity**: Critical for a reachable `phpinfo()` or debug endpoint. High for debug flags hardcoded on. Low for leftover `console.log` or `debugger` statements.
**CWE**: CWE-489
**Detection patterns**:
```
# PHP
define.*WP_DEBUG.*true
define.*APP_DEBUG.*true
ini_set.*display_errors.*1
phpinfo\(\)

# JS
console\.log\(|console\.debug\(|console\.trace\(
debugger;
sourceMap.*true          (in production webpack config)
```
**Action**: Remove debug statements. Ensure debug flags are driven by environment, not hardcoded. Full error reporting (`E_ALL`) is correct in production as long as errors are logged and not displayed.

### Exposed Configuration
**Severity**: High
**CWE**: CWE-260
**Detection patterns**:
```
# Files that should not be web-accessible
\.env$|\.env\.local$|\.env\.production$
wp-config\.php.*define.*DB_PASSWORD
config\.php.*\$password
```
**Action**: Verify `.htaccess` or server config blocks access to sensitive files.

### Missing HTTP Security Headers
**Severity**: Medium
**Detection patterns**:
Identify where HTTP response headers are set (in PHP, JS backends, or server configs). Check if essential security headers are absent:
```
# PHP: header settings
header\(

# JS: Express/Node header settings
res\.setHeader\(|res\.header\(|res\.set\(
```
Verify the implementation or lack of:
- `Strict-Transport-Security` (HTTPS sites)
- `X-Content-Type-Options: nosniff`
- `Content-Security-Policy` (CSP). Its `frame-ancestors` directive replaces `X-Frame-Options`; keep `X-Frame-Options: SAMEORIGIN` or `DENY` only as a fallback for old browsers
- `Referrer-Policy`
Check if the server exposes runtime signatures:
- `X-Powered-By` (should be explicitly disabled or unset)
**Action**: If dead code, remove. If live, implement the missing HTTP headers (via middleware, code, or server config) and disable runtime headers. Headers are often set by the web server or CDN outside the repository; if so, report "confirm at the server" instead of a finding. Introduce a new CSP in report-only mode first, because a strict policy can break a working site.

---

## A04:2025 Cryptographic Failures

### Weak Hashing
**Severity**: High
**CWE**: CWE-328, CWE-916
**Detection patterns**:
```
md5\(|sha1\(          (used for passwords or security tokens)
base64_encode\(       (used as "encryption" — it's not)
```
**Action**: If dead code, remove. If live, store passwords with `password_hash()` and check them with `password_verify()`. Never use a fast hash such as SHA-256 for passwords. Generate tokens with `random_bytes()` and compare them with `hash_equals()`.

---

## A05:2025 Injection

### SQL Injection
**Severity**: Critical
**CWE**: CWE-89
**Detection patterns**:
```
# PHP: Direct variable interpolation in SQL
\$wpdb->query\(.*\$_    (direct user input in query)
"SELECT.*\$              (variable in SQL string without prepare)
'INSERT.*\$
"UPDATE.*\$
"DELETE.*\$

# Should use instead:
\$wpdb->prepare\(
```
**Action**: Dead code with SQL injection must be removed — it can be reached via direct URL if the handler exists.

### Command Injection
**Severity**: Critical
**CWE**: CWE-78
**Detection patterns**:
```
exec\(.*\$|system\(.*\$|passthru\(.*\$|shell_exec\(.*\$|popen\(.*\$
`.*\$.*`                 (backtick execution with variables)
```
**Action**: Remove immediately if dead. If live, avoid the shell: pass the command to `proc_open()` as an array. Where a shell string cannot be avoided, wrap each argument in `escapeshellarg()`. Do not rely on `escapeshellcmd()`; it still lets an attacker add arguments.

### Unbounded Input Lengths
**Severity**: Low
**CWE**: CWE-20
**Detection patterns**:
Check for untrusted user inputs (from `$_GET`, `$_POST`, `$_REQUEST`, HTTP headers, or request payloads in PHP or JS) that are processed, compared, or stored without character length limits or truncation.
```
# PHP: assignment or direct use of superglobals without length validation/truncation
\$_GET\[|\$_POST\[|\$_REQUEST\[

# JS/Node: request parameters or body used without length validation
req\.body|req\.query
```
Look for absence of character clamping/truncation functions like `mb_substr()`, `substr()`, or validator checks (`strlen()`, `mb_strlen()`, schema validation).
**Action**: If the handler is dead, remove it. If live, enforce a maximum length at the API or schema level and reject over-long input. Do not silently truncate: cutting a value can corrupt data or make two different inputs collide.

### Cross-Site Scripting: Unescaped Output (PHP)
**Severity**: High
**CWE**: CWE-79
**Detection patterns**:
```
echo \$_|echo \$         (echo without escaping — check context)
print \$_|print \$
<\?=\s*\$                (short echo without escaping)

# Should use instead:
esc_html\(|esc_attr\(|esc_url\(|wp_kses\(|htmlspecialchars\(
```
**Action**: Dead templates with XSS should be removed. Live code must use proper escaping.

### Cross-Site Scripting: DOM-based (JavaScript)
**Severity**: High
**CWE**: CWE-79
**Detection patterns**:
```
\.innerHTML\s*=          (setting innerHTML with dynamic content)
document\.write\(
\$\(.*\)\.html\(        (jQuery .html() with dynamic content)
eval\(|Function\(        (executing dynamic strings)
```
**Action**: Remove if dead. If live, use `textContent` or sanitize with DOMPurify.

---

## A07:2025 Authentication Failures

### Hardcoded Secrets
**Severity**: Critical
**CWE**: CWE-798
**Detection patterns**:
```
# API keys and tokens
['\"](sk|pk|api[_-]?key|token|secret|password|auth)['\"].*[:=].*['\"][A-Za-z0-9+/=]{16,}

# Common patterns
AKIA[0-9A-Z]{16}               (AWS access key)
sk_live_[A-Za-z0-9]{24,}       (Stripe secret key)
ghp_[A-Za-z0-9]{36}            (GitHub personal token)
-----BEGIN.*PRIVATE KEY-----    (Private key)
```
**Action**: Never print the value in the report, the chat or a commit message. Tell the user to revoke or rotate the credential first: removing it from the files leaves it in git history. Then move it to an environment variable or a secret store. `.gitignore` does not untrack a file that is already committed.

### Default/Test Credentials
**Severity**: Critical
**CWE**: CWE-1392
**Detection patterns**:
```
password.*=.*['\"](admin|test|123|password|root)
user.*=.*['\"](admin|test|root)
```
**Action**: Remove immediately. Never commit test credentials.

---

## A08:2025 Software or Data Integrity Failures

### Unsafe Deserialization (PHP Object Injection)
**Severity**: High
**CWE**: CWE-502
**Detection patterns**:
```
unserialize\(.*\$_       (unserialize user input)
unserialize\(.*\$        (unserialize any variable — check source)
yaml_parse\(             (on data from an untrusted source)
```
**Action**: Remove if dead. If live, use `json_decode()` instead (it never creates objects from class names), or pass `['allowed_classes' => false]` to `unserialize()`. Validate the source before deserialization.

---

## A09:2025 Security Logging and Alerting Failures

### Sensitive Data in Logs
**Severity**: Medium
**CWE**: CWE-532
**Detection patterns**:
```
error_log\(.*password|error_log\(.*token|error_log\(.*secret
console\.log\(.*token|console\.log\(.*password|console\.log\(.*key
```
**Action**: Remove logging of sensitive data. Use redaction if logging is necessary.

### Missing Audit Trail
**Severity**: Low
**CWE**: CWE-778
**Detection patterns**:
```
# Privileged actions without logging
delete_user|delete_post|update_option  (without corresponding audit log call)
```
**Action**: Note in report as a recommendation, not a removal target.

---

## Checks With No OWASP Category

### Loose Comparison of Secrets (PHP)
**Severity**: Medium
**CWE**: CWE-1024
**Detection patterns**:
```
# Tokens, hashes or keys compared with == or !=
(token|secret|hash|signature|api_key|password).*[^=!<>]==[^=]
(token|secret|hash|signature|api_key|password).*!=[^=]

# in_array without its strict flag on untrusted input
in_array\(
```
**Action**: Compare secrets with `hash_equals()` and other values with `===`, and pass `true` as the third argument of `in_array()`. `declare(strict_types=1)` does not change how `==` works and is not a fix. Never add it to existing files as a security measure: it can turn working calls into fatal type errors.

---

## Usage Notes

- Run these patterns against the FULL first-party project scope, not just dead code. Leave out `vendor/`, `node_modules/` and build output
- The patterns are extended regular expressions: run them with `grep -E` or an equivalent search tool
- Security issues in dead code get PRIORITY in the report and, once approved, in removal (attack surface reduction)
- A registered endpoint or handler with no caller in the project is still reachable from outside. Report its issue at full severity, and remove it only when the user or access logs confirm nothing calls it (Category B until then)
- Security issues in live code go into the Risk Matrix as high-priority recommendations
- When in doubt about severity, escalate — assume the worst case
- After removing dead code with security issues, verify the endpoint is truly unreachable
