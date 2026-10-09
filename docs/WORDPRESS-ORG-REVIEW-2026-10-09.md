# WordPress.org review correction — 9 October 2026

The first review of version `0.3.0` reported an HTTP 404 for the fictional privacy URL in the private configuration example. The actual UltimateSales.AI service policy listing was already separate from that example.

Version `0.3.1` replaces the example's privacy and CMS/public-origin URL literals with explicit placeholders. Both the directory readme and repository installation guide instruct operators to supply their publication's live privacy notice under its configured public origin. The real Terms, Privacy and Messaging Policy URLs remain unchanged; unauthenticated checks on 9 October returned HTTP 200 for all three. API host roots remain documented as actual service hosts, regardless of whether their root path serves a website.

The packaged runtime differs from the accepted `0.3.0` ZIP only in the plugin Version header and administrator version display text. The four PHP includes and licence are byte-identical. No consent, unsubscribe, provider payload, credential, ledger, scheduling or migration behavior changes.

## Exact-package validation

File: `readyspace-newsletter-for-ultimatesales-ai-0.3.1.zip` — 27,033 bytes; seven allowlisted files; all files match source.

SHA-256: `08ccf52fd7aecf49e517b7004bd9ee8d8ec6a24f7983d4f6a1199f6a2e738ef4`.

- Clean WordPress 6.9/PHP 8.1.34 and WordPress 7.1.3/PHP 8.3.33: 14 checks each, including enabled `WP_DEBUG`, logging/display, activation, archive exclusions, default Off/unverified, administrator nonce, worker scheduling and deactivation. Zero plugin HTTP calls.
- Both runs exited successfully with empty stderr and clean JSON output, without displayed PHP fatal/warning/notice text. The in-memory debug log was not exported; no claim of an inspected empty log is made.
- Official Plugin Check 2.1.0: 29 static and 34 including runtime checks in both environments, zero errors and the same 25 documented warnings (ten DirectQuery, ten NoCaching, five legacy namespace). See the existing readiness appendix for their rationale.
- Fresh syntax checks on all five packaged PHP files passed on PHP 8.1.34, 8.3.33 and 8.4.25.
- Prior 293 behavioral assertions per PHP version are reused after proving the two version-text replacements are the only PHP differences and tests are unchanged.

These local WordPress checks use official Playground PHP-WASM and SQLite. Existing provider/native MySQL acceptance remains historical evidence for preserved behavior. No live credential, subscriber request, newsletter send or production deployment was performed for this documentation correction.

## Review workflow

WordPress.org accepted version `0.3.1` as an update to the existing submission on 9 October 2026. The assigned slug remains `readyspace-newsletter-for-ultimatesales-ai`. The page now shows **Being Reviewed — We've got your email. This plugin is currently waiting on action from our plugins team.** This is a page acknowledgement of receipt; this task did not send or inspect the email reply.

The next action is with the Plugins Team. Manual approval and the first public SVN release remain pending. Do not create another submission or send a duplicate reply solely for this correction. The original accepted ZIP/receipt and the fresh URL audit, package-diff proof, validation reports and update screenshots are preserved in the private handoff.
