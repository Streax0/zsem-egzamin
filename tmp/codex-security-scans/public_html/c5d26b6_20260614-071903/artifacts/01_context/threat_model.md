# Repository Threat Model

## Overview

This repository is a PHP/MySQL educational web application used by students, teachers, administrators, directors, and unauthenticated or guest exam participants. Primary runtime surfaces include authentication and account recovery, quizzes and hosted exams, teacher and administrator panels, social features, file/PDF delivery, AJAX endpoints, notifications, and Apache/PHP security configuration. Sensitive assets include credentials, session state, student identity and results, exam questions and answer keys, role privileges, audit data, teacher-owned exams, and administrative controls.

## Threat Model, Trust Boundaries, and Assumptions

- The public browser-to-Apache/PHP boundary accepts attacker-controlled query strings, form bodies, JSON, headers, uploaded content, filenames, and navigation targets.
- Authentication separates guests, students, teachers, administrators, and directors. Every privileged action must enforce server-side role and object ownership checks; UI visibility is not an authorization control.
- PHP sessions and CSRF tokens are security boundaries. Session fixation, weak cookie attributes, guest/session confusion, and missing CSRF checks can cross those boundaries.
- The PDO/MySQL boundary must use parameterized queries and constrained dynamic SQL. Database data is not automatically trusted when rendered back into HTML.
- Filesystem and document-delivery code must confine paths to intended roots and reject traversal, remote schemes, and unsafe content types.
- Browser rendering is an XSS boundary. User-controlled strings must be escaped for their HTML, attribute, JavaScript, URL, or JSON context.
- Reverse-proxy headers are trusted only from configured proxy addresses. Canonical public URLs must not derive from spoofable Host or forwarded headers.
- Teacher-authored exam configuration is operator-controlled, while participant names, answers, browser events, and guest state remain attacker-controlled.
- Developer configuration and deployment secrets are out of public reach; exposed .env/config files or debug tooling invalidate this assumption.

## Attack Surface, Mitigations, and Attacker Stories

- Authentication/account recovery: brute force, credential stuffing, reset-token theft, role escalation, MFA bypass. Existing controls include password hashing, rate limits, session-version validation, MFA helpers, CSRF, and secure-session setup.
- Hosted exams: participant impersonation, changing another participant's answers, replaying submissions, leaking answer keys, forged anti-cheat events, and unauthorized teacher access. Controls must bind participant, session, exam status, ownership, question, and CSRF state on the server.
- Admin/teacher actions: IDOR, mass assignment, unsafe role changes, destructive GET actions, and insufficient audit trails. All actions require explicit roles, ownership where applicable, CSRF, validation, and bounded queries.
- AJAX/JSON endpoints: auth bypass, guest confusion, rate-limit bypass, content-type confusion, and verbose error leakage. Existing response helpers and JSON guards should fail closed.
- Social and notification features: stored XSS, unsafe action URLs, spam, forged relationships, and notification fan-out abuse. URLs must remain same-origin/local and rendered text escaped.
- File/PDF/image paths: traversal, local file disclosure, SSRF, malicious uploads, and content sniffing. Realpath confinement, extension/MIME checks, authentication, and nosniff/CSP/CORP headers are relevant controls.
- Frontend dependencies and browser policy: XSS impact is reduced by enforced nonce CSP, SRI for CDN assets, frame restrictions, referrer policy, HSTS, and content-type protections. Inline-script additions must remain compatible with nonce injection.
- Availability: unbounded list queries, synchronous notification fan-out, repeated schema checks, aggressive polling, and expensive per-user loops can cause resource exhaustion. Pagination, limits, rate limits, batching, and deployment-time migrations are expected.
- Out of scope: compromise of the host OS, database administrator, trusted deployment pipeline, or a user's separate physical device. Browser code cannot reliably detect screenshots taken by an operating system or external phone.

## Severity Calibration (Critical, High, Medium, Low)

- Critical: unauthenticated remote code execution; arbitrary admin/director takeover; unrestricted database or filesystem compromise; public disclosure of password hashes or secrets.
- High: horizontal or vertical authorization bypass exposing/modifying student results or teacher exams; SQL injection; stored XSS in privileged views; reset/MFA bypass; arbitrary local file read; answer-key disclosure before an exam ends.
- Medium: CSRF on meaningful state changes; reflected XSS with user interaction; bounded IDOR affecting low-sensitivity data; notification spam; bypass of anti-cheat telemetry without result manipulation; significant query amplification.
- Low: limited information disclosure without personal data, cosmetic CSP/reporting gaps with no executable path, low-impact UI spoofing, or performance inefficiency requiring authenticated use and substantial repetition.

