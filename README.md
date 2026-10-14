# WP UltimateSales.AI Newsletter Plugin

By **ReadySpace**. Let your UltimateSales.AI email subscribers know when you publish a new WordPress blog post. After setup, the plugin can automatically prepare an email with the post title, a short summary and a link to the full article on your website. It supports standard WordPress and headless sites whose public article paths match WordPress's post links and pass the required page checks. An UltimateSales.AI account and developer or administrator setup are required.

**Published release: 0.4.0 (15 October 2026, SVN revision `3747310`). Operator setup required.** Integrates newsletter request validation and saved-email verification into the regular plugin for both standard and headless WordPress. Scheduling requires the same verified draft and a fresh, frozen recipient list. Existing settings, encrypted credentials, history and archive exclusions retain their identifiers. Sites with custom outbound restrictions can explicitly opt into a narrowly scoped host integration; ordinary sites need no new configuration. The official WordPress.org ZIP was verified file-for-file against the tested package. See [release validation](docs/RELEASE-0.4.0.md).

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

## Future plans

ReadySpace plans to add new-post sharing to WhatsApp groups, Telegram channels, Facebook Pages, LinkedIn Pages and Google Business Profile (formerly Google My Business). The aim is to share a link so readers across different channels can find the new article. **Version 0.4.0 supports email only.**

## Start here

1. Read [installation and launch](docs/INSTALLATION.md).
2. Configure a test WordPress site and a controlled recipient before production use.
3. Read [operations and recovery](docs/OPERATIONS.md) before enabling delivery.
4. See [development](docs/DEVELOPMENT.md) and [contributing](CONTRIBUTING.md) to collaborate.

The source directory remains `cleverspeed-newsletter`. Directory packages retain the approved slug `readyspace-newsletter-for-ultimatesales-ai` and existing [WordPress.org listing](https://wordpress.org/plugins/readyspace-newsletter-for-ultimatesales-ai/), with the display name **Newsletter for UltimateSales.AI**. Legacy database options, queue records, hooks and duplicate-send history retain their identifiers. Existing pilot installations need an explicit folder migration; never activate both copies. See [WordPress.org readiness](docs/WORDPRESS-ORG-READINESS.md).

## Requirements

- Single-site WordPress 6.9+; PHP 8.1+ with OpenSSL, DOM, JSON and libxml.
- HTTPS administration, secure WordPress authentication keys and a reliable cron runner.
- MySQL/MariaDB advisory locks; verify compatibility with the actual host.
- A UltimateSales.AI location-level private integration, authenticated email delivery and confirmed consent records.
- Existing staff access with permission to manage campaigns.

The release workflow runs isolated PHP 8.1/8.3/8.4 regression checks and a real WordPress 6.9/7.1.3 and MySQL 8 upgrade matrix. It tests both standard and headless layouts, with and without an optional host isolation policy. All newsletter HTTP in these tests is intercepted with fictional data; no campaigns or emails are created in a real account. Passing these checks does not establish a new account's delivery, unsubscribe behaviour or email-client rendering. New installations still require controlled acceptance.

## Downloads

Install the current release from [WordPress.org](https://wordpress.org/plugins/readyspace-newsletter-for-ultimatesales-ai/) through WordPress → Plugins → Add New, or download its versioned install ZIP from the listing. Use GitHub's **Code → Download ZIP** to download the source. For an isolated-test ZIP, run `bash scripts/package.sh --candidate`; its filename is marked `REVIEW-ONLY`. For a future release, `bash scripts/package.sh --submission` checks the licence and compatibility metadata before creating a submission package; review and validate that build before publishing. Upload only a reviewed build through WordPress → Plugins → Add New → Upload Plugin. Do not upload the whole repository ZIP as the plugin.

## Licence

Copyright (C) 2026 ReadySpace. All original source code, documentation and directory artwork are licensed under the GNU General Public License, version 2 or, at your option, any later version (`GPL-2.0-or-later`). ReadySpace authorized this licence and confirmed the necessary rights on 8 October 2026. See [LICENSE](LICENSE) for the full terms and [COPYRIGHT.txt](COPYRIGHT.txt) for the grant and scope. The UltimateSales.AI service is governed by its separate service terms. The review label records incomplete release validation; GPL rights apply to this candidate.

## Safety

No API credentials, customer account settings, subscriber data or live acceptance scripts belong in this repository. Do not post them in issues. See [security reporting](SECURITY.md). API acceptance is not proof of email delivery; inspect delivery and suppression in the provider dashboard.
