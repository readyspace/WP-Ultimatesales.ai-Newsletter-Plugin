=== ReadySpace Newsletter for UltimateSales.AI ===
Contributors: readyspace
Tags: newsletter, email, headless, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 0.3.0-alpha.3
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send article excerpts through your UltimateSales.AI account, with subscriber checks, draft review and a durable newsletter send ledger.

== Description ==

Connect a single-site WordPress backend to your UltimateSales.AI account. Headless frontends work when public articles pass the checks below.

Version 0.3.0-alpha.3 is an unverified developer preview, licensed under GPLv2 or later. ReadySpace has authorized this licence and confirmed the necessary rights. Release validation and provider acceptance remain prerequisites to public submission.

Five minutes after a post's first publication, the plugin checks its public page and uses its explicit 10-150 word Excerpt. Existing published posts, edits and republishes do not resend. Missing excerpts or failed checks hold the job.

It reads subscribers, creates and checks a remote draft and, in Live mode, requests delivery. Off and Draft-only modes are available. Interrupted create/send requests require reconciliation; there is no blind retry. Provider acceptance does not prove mailbox delivery.

It does not create signup forms, contacts, confirmation workflows or consent; authenticate sending domains; use WordPress SMTP; or replace provider unsubscribe/suppression.

= Requirements and limitations =

* PHP 8.1+ with OpenSSL, DOM, JSON and libxml; HTTPS administration and strong WordPress keys.
* Reliable scheduling and verified MySQL/MariaDB advisory locks.
* UltimateSales.AI authenticated sending, location-level private integration and verified staff identity.
* Accurate consent/suppression records and distinct confirmed, pending and unsubscribed tags.
* HTTPS CMS/public origins with no path, port, query, fragment or trailing slash; single-site only, no subdirectory installations.
* Public articles with an exact canonical URL, one matching H1 and no noindex directive.

An operator must edit private configuration and complete provider acceptance. There is no connection verification wizard. The predecessor pilot does not certify this alpha; use separate staging and controlled recipients.

Development, installation and recovery documentation: https://github.com/readyspace/WP-Ultimatesales.ai-Newsletter-Plugin

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

On 8 October 2026, these policies identify themselves as drafts for owner/legal review before production launch. They are not finalized policies; policy completion and the applicable customer agreement remain release blockers.

== Installation ==

1. Prepare separate staging and a test location with controlled recipients. Verify sender/domain, legal identity/address, privacy page, consent and unsubscribe suppression.
2. Create a location-level private integration with contacts.readonly, users.readonly, emails/campaigns.readonly and emails/campaigns.write. Confirm endpoint access; keep the token private.
3. Upload the installable ZIP through Plugins -> Add New Plugin -> Upload Plugin and activate. Do not upload the whole repository ZIP. Activation excludes the archive; preserve its ledger.
4. Add the fictional example below to private wp-config.php before WordPress loads, replacing every value. Never commit populated settings or credentials.
5. In Tools -> ReadySpace Newsletter, enter the token in the password field and select Save credential and keep sending off. Storage is not connection verification.
6. Complete acceptance while Off. Configure one scoped scheduler and verify a controlled new-post job.

= Private configuration example =

CMS/public origins can match on conventional WordPress. The privacy URL must exist on the public origin.

    define('RS_NEWSLETTER_CONFIG', [
        'location_id' => 'YOUR_LOCATION_ID',
        'confirmed_tag' => 'newsletter-confirmed',
        'pending_tag' => 'newsletter-pending',
        'unsubscribed_tag' => 'newsletter-unsubscribed',
        'cms_origin' => 'https://cms.example.org',
        'public_origin' => 'https://example.org',
        'brand' => 'Your publication',
        'from_email' => 'newsletter@example.org',
        'reply_email' => 'reply@example.org',
        'timezone' => 'UTC',
        'legal_name' => 'Your legal operator',
        'postal_address' => 'Your accurate postal address',
        'privacy_url' => 'https://example.org/privacy/',
        'preview_text' => 'Practical ideas from our latest article.',
    ]);

Stop queue workers and choose Off before changing configuration or credentials. Any configuration change invalidates verification.

= Maintainer acceptance and scheduler =

While Off, verify location/staff identity, scopes and subscriber DND with the actual WordPress HTTP transport. In the test location, create/read a draft; inspect content, sender/footer, article URL and unsubscribe token. Perform an explicitly approved controlled-recipient send; inspect delivery/rendering and unsubscribe suppression. Never log tokens, full contacts or response bodies.

Only after documenting acceptance may a maintainer use this command on the intended installation. It writes the gate without performing checks. Replace VERIFIED_STAFF_ID with the verified ID. Delivery stays Off.

    wp --path=/absolute/path/to/wordpress eval '$s=\CleverSpeed\Newsletter\Plugin::settings(); $s["mode"]="off"; $s["user_id"]="VERIFIED_STAFF_ID"; $s["verified"]=true; $s["verified_config"]=\CleverSpeed\Newsletter\Config::fingerprint(); update_option("cs_newsletter_settings",$s,false);'

Test a controlled first publication in Draft-only. Changing to Live does not promote existing drafts. Enable Live in the admin screen only after acceptance and audience verification; it covers future first publications.

The every-minute hook is cleverspeed_newsletter_tick. Low-traffic/headless sites can use a scoped scheduler:

    wp --path=/absolute/path/to/wordpress cron event run cleverspeed_newsletter_tick --due-now

Inspect existing scheduling to avoid duplicates. Add a host lock alongside the per-post database lock. Heartbeat alone does not prove delivery.

== Frequently Asked Questions ==

= Can I retry a failed or interrupted send? =

Administrators can recheck held jobs before campaign creation. Creating/sending/uncertain jobs require provider reconciliation. No blind resend exists. Deactivation cannot recall already accepted campaigns.

= What happens to stored data when I deactivate or remove the plugin? =

Deactivation clears the queue hook. There is no automatic uninstall cleanup: settings, encrypted credential, post metadata and ledger remain. The ledger protects against repeat sends. Stop workers, reconcile campaigns and consult recovery docs before manual cleanup. Provider data remains in the provider account.

= Can I replace a working site-specific pilot with this alpha? =

Complete staging acceptance and keep a private rollback backup first. Preserve the pilot folder, options, hooks, ledger and metadata. Legacy cleverspeed-newsletter identifiers remain. The proposed directory slug may require reviewed folder migration for directory updates.

== Changelog ==

= 0.3.0-alpha.3 =

* Directory preparation candidate with ReadySpace naming and external-service/install documentation.
* Hardened mode requests, durable ledger transitions and malformed consent responses while retaining legacy integration identifiers.
* GPLv2-or-later licensing approved by ReadySpace; MySQL/provider acceptance and final service policies remain release gates.
