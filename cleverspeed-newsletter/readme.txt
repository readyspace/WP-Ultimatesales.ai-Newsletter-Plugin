=== Newsletter for UltimateSales.AI ===
Contributors: readyspace
Tags: newsletter, email, headless, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 0.4.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Email your UltimateSales.AI newsletter subscribers when you publish a new WordPress post, with a link to read it.

== Description ==

Let your subscribers know when you publish a new blog post.

Newsletter for UltimateSales.AI connects your WordPress blog to the email subscribers in your UltimateSales.AI account. After setup, it can automatically send an email about each new post. The email includes the post title, a short summary and a link to read the full article on your website.

= How it works =

1. Write your blog post in WordPress. Add a 10-150 word summary in its Excerpt field.
2. Publish the post. The plugin waits five minutes, then checks that readers can open the article on your public website.
3. With automatic sending enabled, it prepares and checks an email, then asks UltimateSales.AI to send it to your eligible subscribers.

Emails include sender details, privacy and unsubscribe links. UltimateSales.AI handles delivery; a send request does not guarantee that every email reaches an inbox.

Only first publication starts a notification. Old posts, edits and republishing do not trigger emails. Missing summaries or failed checks pause the notification. Interrupted sends need administrator review before further action.

= Standard and headless WordPress =

The plugin supports standard WordPress sites and headless sites. In a headless site, WordPress manages the posts while a separate website shows them to readers. Email links point to that public website. Its article paths must match WordPress's post links and meet the checks below. Single-site installations only; multisite and subdirectory installations are not supported.

= Before you start =

You need an UltimateSales.AI account, permission to email your subscribers and help from a site administrator or developer to connect and test the service. Service and email charges may apply.

It emails eligible contacts with your confirmed-subscriber tag and skips pending or unsubscribed contacts. Your signup process must record their permission; adding a tag does not create permission.

Sending starts Off. Draft-only mode prepares emails without sending. After testing, Live mode enables automatic sending for future new posts. Signup, sender setup and unsubscribe handling must already work in your account; this plugin does not create them.

= Future plans: more channels =

ReadySpace plans to add new-post sharing to WhatsApp groups, Telegram channels, Facebook Pages, LinkedIn Pages and Google Business Profile (formerly Google My Business). The aim is to share a link so more readers can find each new article. These features are not available in version 0.4.0.

= Requirements and limitations =

* PHP 8.1+ with OpenSSL, DOM, JSON and libxml; HTTPS administration and strong WordPress keys.
* Reliable scheduling and verified MySQL/MariaDB advisory locks.
* UltimateSales.AI authenticated sending, location-level private integration and verified staff identity.
* Accurate consent/suppression records and distinct confirmed, pending and unsubscribed tags.
* HTTPS CMS/public origins with no path, port, query, fragment or trailing slash; single-site only, no subdirectory installations.
* Public articles with matching canonical/H1 and no noindex. Only a final-slash URL difference and one same-origin 301/308 redirect are allowed.

Private configuration and installation verification require an operator; no connection wizard is provided. New accounts/sites need their own delivery, unsubscribe and host checks.

Source and operating documentation: https://github.com/readyspace/WP-Ultimatesales.ai-Newsletter-Plugin

= External services =

UltimateSales.AI is operated by ReadySpace using licensed third-party technology. A service account is required; subscription/email charges may apply. This plugin does not buy credits or change billing.

Activation defaults to Off. Saving a credential switches sending Off and resets verification. Review this data flow and your account's consent/privacy practices before configuring the service, authorizing its connections and completing acceptance.

The plugin uses these hosts:

* https://services.leadconnectorhq.com - UltimateSales.AI's underlying contact-search and campaign create/read/schedule API.
* https://storage.googleapis.com and https://firebasestorage.googleapis.com - campaign HTML readback at permitted API-supplied URLs.
* Your configured public origin - article availability, title, canonical URL and indexing checks.

API requests send the token, location/staff IDs and confirmed-tag search. Campaign requests send article title, Excerpt, URL, newsletter HTML, sender/reply details, timezone, preview text, legal footer and privacy URL. Scheduling sends eligible contact IDs. Services also receive connection metadata such as the server IP.

Contact emails, tags and Do Not Disturb fields are processed in memory for eligibility. Subscriber lists/full response bodies are not persisted in the ledger. The plugin does not write contacts, grant consent or guarantee legal compliance; confirmed tags require a real consent process.

WordPress stores the encrypted credential, mode/verification settings and configuration fingerprint. The ledger stores post/campaign/source IDs, states, due times, hashes, recipient/attempt counts, timestamps and diagnostics. Private wp-config.php holds per-site configuration. Encryption protects a database-only copy, not a compromised server. Key rotation requires credential re-entry.

Scheduling disables click/UTM tracking and automatic resends; it does not establish that open tracking or all provider tracking is disabled. Review account settings and document actual practices in your site's privacy notice.

Service information and policies:

* Service: https://ultimatesales.ai/
* Terms: https://ultimatesales.ai/terms
* Privacy notice: https://ultimatesales.ai/privacy
* Messaging policy: https://ultimatesales.ai/messaging-policy

Account retention and tracking depend on service practices/settings; review the linked notices and your site's practices.

== Installation ==

1. Prepare separate staging and a test location with controlled recipients. Verify sender/domain, legal identity/address, privacy page, consent and unsubscribe suppression.
2. Create a location-level private integration with contacts.readonly, users.readonly, emails/campaigns.readonly and emails/campaigns.write. Confirm endpoint access; keep the token private.
3. Upload the installable ZIP through Plugins -> Add New Plugin -> Upload Plugin and activate. Do not upload the whole repository ZIP. Activation excludes the archive; preserve its ledger.
4. Add the installation guide's configuration to private wp-config.php before WordPress loads, replacing every placeholder. Never commit populated settings or credentials.
5. In Tools -> UltimateSales.AI Newsletter, enter the token in the password field and select Save credential and keep sending off. Storage is not connection verification.
6. Begin read/setup acceptance while Off, then verify an unsent draft in Draft-only before enabling Live. Configure one scoped scheduler and verify a controlled new-post job.

= Developer setup guide =

The full private configuration example and verification/scheduler commands are in the installation guide:
https://github.com/readyspace/WP-Ultimatesales.ai-Newsletter-Plugin/blob/main/docs/INSTALLATION.md

CMS/public origins can match on standard WordPress. Set privacy_url to your publication's live privacy notice on public_origin. Never put private account settings or credentials in public files.

Stop queue workers and choose Off before changing configuration or credentials. Any configuration change invalidates verification.

= Maintainer acceptance and scheduler =

Follow the guide's staged account, draft, controlled-delivery and unsubscribe checks. Read/setup checks begin while Off; creating a draft requires the accepted configuration gate and Draft-only mode. Its administrative command records a gate and does not perform checks. Finish controlled acceptance before enabling Live. Existing drafts are not promoted to live sends.

Run the every-minute cleverspeed_newsletter_tick hook with reliable scheduling and locks. Avoid duplicate schedules. A heartbeat alone does not prove delivery. Keep credentials and subscriber data out of logs.

== Frequently Asked Questions ==

= Can I retry a failed or interrupted send? =

Administrators can recheck held jobs before campaign creation. Creating/sending/uncertain jobs require provider reconciliation. No blind resend exists. Deactivation cannot recall already accepted campaigns.

= What happens to stored data when I deactivate or remove the plugin? =

Deactivation clears the queue hook. There is no automatic uninstall cleanup: settings, encrypted credential, post metadata and ledger remain. The ledger protects against repeat sends. Stop workers, reconcile campaigns and consult recovery docs before manual cleanup. Provider data remains in the provider account.

= Can I replace a working site-specific pilot with this plugin? =

Complete staging acceptance and keep a private rollback backup first. Preserve the pilot folder, options, hooks, ledger and metadata. Legacy cleverspeed-newsletter identifiers remain. Keep the published directory slug when updating through WordPress.org. Site-specific compatibility adapters require a reviewed migration; the 0.4.0 release does not remove them automatically.

== Changelog ==

= 0.4.0 =
* Integrate newsletter request validation, bounded transport and full saved-email verification into the regular plugin.
* Check the complete saved draft, allowing only narrowly recognized Outlook formatting from the provider.
* Bind scheduling to the same verified draft and a freshly checked, frozen recipient list; consume authorization before transport.
* Support standard and headless WordPress using each site's existing configuration, with optional explicit integration for hosts that restrict outbound requests.
* Preserve settings, encrypted credentials, newsletter history, archive exclusions and ordinary-site verification during upgrades.

= 0.3.3 =
* Fix newsletter holds when a public article returns a root-relative permanent redirect that only adds or removes its final slash.
* Retain same-origin and same-article validation, one-redirect limit, consent checks and duplicate protection.
* Preserve existing settings, credentials, newsletter history and exclusions during upgrades.

= 0.3.2 =

* Rename to Newsletter for UltimateSales.AI; keep the published slug and integration unchanged.
* Explain new-post email notifications in plain language; identify other channels as future plans.

= 0.3.1 =

* Replace fictional setup URLs with explicit placeholders; service policy links remain above.

= 0.3.0 =

* Initial directory submission with numeric metadata and approved GPLv2-or-later licensing.

== Upgrade Notice ==

= 0.4.0 =
Newsletter safeguards now run inside the regular plugin. Existing standard sites need no new configuration. Sites with custom outbound restrictions should follow the installation guide before removing their newsletter compatibility adapter. This release does not change previews, redirects or other CMS protections.
