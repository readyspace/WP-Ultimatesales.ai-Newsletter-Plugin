# Contributing

Please discuss a proposed change in an issue before a large implementation. The original project source, documentation and artwork are licensed under GPLv2 or later. Contributions must be compatible with that licence and you must have the rights to submit them. See LICENSE and COPYRIGHT.txt. Use a focused branch/PR, explain the problem and risk, add regression tests and update operator documentation.

Never include tokens, WordPress configuration, database exports, real subscriber fixtures, private campaign IDs or screenshots of customer accounts. Use example.org/example.test and synthetic values. Tests must never call a live provider.

Changes to consent, scopes, automatic sending, ledger identifiers, credential formats or retry behaviour need explicit design review and migration/rollback notes. Preserve the no-archive-catch-up and no-blind-resend guarantees. Do not add telemetry, AI dependencies, SMTP fallback or contact-write permissions incidentally.

Run `bash scripts/test.sh` and inspect `bash scripts/package.sh` output. Describe what was actually tested and what still requires staging/provider access. See SECURITY.md for vulnerability handling.
