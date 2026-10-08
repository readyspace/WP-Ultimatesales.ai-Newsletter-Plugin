# WP UltimateSales.AI Newsletter Plugin

By **ReadySpace**. A direct WordPress → UltimateSales.AI newsletter bridge for UltimateSales.AI (https://ultimatesales.ai) accounts. Any editor can write the post and its Excerpt; no ChatGPT, AI summariser, Next.js email code or WordPress SMTP is required.

**Initial developer preview: 0.3.0-alpha.3. Not a one-click production release.** This public branch removes deployment-specific settings from the earlier single-site pilot. It requires private configuration and maintainer-led provider verification. It has not been deployed over the working pilot. Multisite is not supported.

## What it does

- Queues an excerpt email five minutes after a post's first publication.
- Excludes the existing archive on installation; edits and republishes do not resend.
- Uses the explicit WordPress Excerpt (10–150 words; 60–90 recommended).
- Checks the public page's HTTP status, canonical URL, single H1 and indexing directives.
- Reads confirmed, email-eligible subscribers from UltimateSales.AI; never creates contacts or grants consent.
- Creates and checks a draft before sending; uncertain create/send outcomes stop for reconciliation.
- Includes a privacy link, legal footer and the provider's unsubscribe merge token.
- Stores the API token encrypted; allows administrators to enter it in WordPress.
- Offers Off, Draft-only and Live modes. Configuration changes invalidate verification.

The plugin does **not** create signup forms, confirmation workflows, authenticated sending domains or newsletters from unconfirmed contacts. Those must already exist in the chosen account.

## Start here

1. Read [installation and launch](docs/INSTALLATION.md).
2. Configure a test WordPress site and a controlled recipient before production use.
3. Read [operations and recovery](docs/OPERATIONS.md) before enabling delivery.
4. See [development](docs/DEVELOPMENT.md) and [contributing](CONTRIBUTING.md) to collaborate.

The source directory remains `cleverspeed-newsletter`. Directory candidate packages use `readyspace-newsletter-for-ultimatesales-ai`, matching the proposed public name **ReadySpace Newsletter for UltimateSales.AI**. Legacy database options, queue records, hooks and duplicate-send history retain their identifiers. Existing pilot installations need an explicit folder migration; never activate both copies. See [WordPress.org readiness](docs/WORDPRESS-ORG-READINESS.md).

## Requirements

- Single-site WordPress 6.9+; PHP 8.1+ with OpenSSL, DOM, JSON and libxml.
- HTTPS administration, secure WordPress authentication keys and a reliable cron runner.
- MySQL/MariaDB advisory locks; verify compatibility with the actual host.
- A UltimateSales.AI location-level private integration, authenticated email delivery and confirmed consent records.
- Existing staff access with permission to manage campaigns.

This candidate has isolated PHP 8.1/8.3/8.4 regression coverage and WordPress 6.9/7.1.3 activation/default-Off checks in WordPress Playground (SQLite). This does not establish MySQL/MariaDB advisory locking, live provider delivery, unsubscribe suppression or all email-client layouts. See the release readiness checklist before use.

## Downloads

Use GitHub's **Code → Download ZIP** to download the source. For an isolated-test ZIP, run `bash scripts/package.sh --candidate`; its filename is marked `REVIEW-ONLY`. After owner licensing approval and all readiness blockers are resolved, `bash scripts/package.sh --submission` checks the licence and compatibility metadata before creating a submission package. Upload only a reviewed build through WordPress → Plugins → Add New → Upload Plugin. Do not upload the whole repository ZIP as the plugin.

## Licence

Public source publication does not itself grant an open-source licence. ReadySpace's licence selection is pending; no additional reuse or redistribution licence is granted in this initial snapshot. Do not submit contributed code until the project licence is settled.

## Safety

No API credentials, customer account settings, subscriber data or live acceptance scripts belong in this repository. Do not post them in issues. See [security reporting](SECURITY.md). API acceptance is not proof of email delivery; inspect delivery and suppression in the provider dashboard.
