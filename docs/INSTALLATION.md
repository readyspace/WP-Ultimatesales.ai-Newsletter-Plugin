# Installation and controlled launch

This plugin requires a developer/operator. It does not yet include a self-service connection wizard. Do not simply install it and set `verified=true` to bypass testing.

## 1. Prepare the account and site

Use a separate staging site and controlled test recipient. Verify the UltimateSales.AI location, sending domain, sender/reply mailbox, legal identity, postal address, privacy notice, opt-in confirmation flow and unsubscribe behaviour. The plugin neither creates these nor confirms subscribers. Confirmed, pending and unsubscribed tags must be distinct and maintained by your existing consent process. Do not tag an unconsenting contact as confirmed.

Create a location-level private integration with exactly these permissions:

- `contacts.readonly`
- `users.readonly`
- `emails/campaigns.readonly`
- `emails/campaigns.write`

Permissions do not guarantee that all campaign API endpoints are enabled for your account. Resolve provider access errors rather than spoofing browsers or weakening security controls. The API base is fixed to `https://services.leadconnectorhq.com`.

Back up WordPress files and database privately. Do not clone real subscribers or a live credential into a test site. Check that a restored site cannot send using production options and cron entries.

## 2. Install and configure

Install from WordPress.org, or build the reviewed installable ZIP with `bash scripts/package.sh --submission`, then upload and activate it in WordPress. Activation creates the ledger and excludes all already-published posts. Do not delete that ledger to retry or reinstall.

Copy the following **fictional** example into private `wp-config.php` before WordPress loads. Replace every value for your site; capitalized values are placeholders, not URLs. Never commit the populated configuration. The public and CMS origins may be identical on non-headless sites. They must use HTTPS, without paths, ports, query strings or trailing slashes. Subdirectory WordPress installations are not supported.

```php
define('RS_NEWSLETTER_CONFIG', [
    'location_id' => 'YOUR_LOCATION_ID',
    'confirmed_tag' => 'newsletter-confirmed',
    'pending_tag' => 'newsletter-pending',
    'unsubscribed_tag' => 'newsletter-unsubscribed',
    'cms_origin' => 'YOUR_HTTPS_CMS_ORIGIN',
    'public_origin' => 'YOUR_HTTPS_PUBLIC_ORIGIN',
    'brand' => 'Your publication',
    'from_email' => 'newsletter@example.org',
    'reply_email' => 'reply@example.org',
    'timezone' => 'UTC',
    'legal_name' => 'Your legal operator',
    'postal_address' => 'Your accurate postal address',
    'privacy_url' => 'YOUR_PUBLIC_PRIVACY_NOTICE_URL',
    'preview_text' => 'Practical ideas from our latest article.',
]);
```

These are required configuration values, not verified promises. The sender must be approved by your provider. Set `privacy_url` to your publication's live privacy notice on `public_origin`; it must match your actual practices. Any configuration change requires renewed verification. Stop workers and switch mode Off before changing configuration or credentials.

Open **Tools → UltimateSales.AI Newsletter**. Enter the token in its password field and choose **Save credential and keep sending off**. This requires an administrator, HTTPS and a valid WordPress nonce. The token is never displayed. A successful save proves storage, not connectivity. Do not put the token in the configuration example, shell history, issue tracker or chat.

## 3. Maintainer acceptance checks

Keep mode Off. With the intended WordPress HTTP transport, verify the configured location/staff identity and read access. Check campaign readback and explicit subscriber DND (Do Not Disturb) fields. Missing DND is not permission to send. Never log credentials, full contacts or provider response bodies.

Also verify the private configuration, public article checks, approved sender, consent process, scheduler and database locks against the controlled test installation. After documenting these read and setup checks, a maintainer may record the configuration gate while sending remains Off. The following **administrative WP-CLI command writes a gate; it does not perform the checks**. Replace the staff identifier with the one actually verified. Use it only on the intended installation, not a live bootstrap for unit tests. It leaves delivery Off.

```sh
wp --path=/absolute/path/to/wordpress eval '$s=\CleverSpeed\Newsletter\Plugin::settings(); $s["mode"]="off"; $s["user_id"]="VERIFIED_STAFF_ID"; $s["verified"]=true; $s["verified_config"]=\CleverSpeed\Newsletter\Config::fingerprint(); update_option("cs_newsletter_settings",$s,false);'
```

In the isolated test account, use Draft-only mode for the next controlled first-publication check. Verify real campaign creation and full saved-body readback, including title, excerpt, exact article URL, legal footer and unsubscribe token. Draft creation requires the accepted configuration gate and Draft-only or Live mode; Off allows read checks but blocks all campaign writes. If any check fails, return to Off and reset verification before proceeding. Then perform an explicitly approved controlled-recipient test and check actual mailbox delivery, rendering, button destination and provider unsubscribe behaviour. A visual unsubscribe link alone does not prove suppression works. The repository includes no live-send acceptance script. Existing remote drafts are never automatically promoted when switching to Live. Keep the test audience isolated. Do not create a public test post in production or resend an old article to subscribers just to test.

After acceptance, enable Live through the administrator screen for future first publications. Recheck that the actual audience is correct before enabling. Provider actions may incur charges under your own account; this plugin does not purchase credits or alter billing.

## 4. Scheduler

The plugin registers `cleverspeed_newsletter_tick` every minute. On low-traffic/headless sites, configure one reliable host scheduler to run the due plugin hook, such as `wp --path=/absolute/path/to/wordpress cron event run cleverspeed_newsletter_tick --due-now`. Inspect existing scheduling first; do not add duplicate cron entries or run every WordPress cron event as a test. Use a host lock as well as the plugin's per-post database lock.

Verify the heartbeat and a controlled new-post job. A heartbeat alone does not prove delivery is enabled or successful.

## Pilot upgrades

Do not replace a working site-specific pilot with this release without staging acceptance. Preserve the existing folder name, options, ledger and post metadata. Supply all private configuration, switch mode Off, stop the scoped worker and reverify. An old pilot verification flag lacks the new configuration fingerprint and will not enable sending. Keep private rollback copies outside web roots.


## Upgrading to 0.4.0

Directory updates preserve the existing options, encrypted credential, ledger, post exclusions, active plugin entry and queue hook. On ordinary sites the configuration fingerprint remains identical to 0.3.3, so an accepted installation with unchanged configuration keeps its verification. There is no archive catch-up and no automatic promotion of previously prepared drafts.

If the site has no custom outbound HTTP restrictions, no additional setting or must-use plugin is required. Standard WordPress may use the same `cms_origin` and `public_origin`; headless sites may use different HTTPS origins with matching article paths. Both use the same provider client and verification logic.

### Optional host HTTP isolation integration

A host that deliberately blocks outbound WordPress HTTP can opt in to permitting only this plugin's active, validated newsletter requests. This is an operator integration, not a general network exemption. Leave the setting undefined on ordinary sites.

The host's isolation filter must return a `WP_Error` with one unique custom error code. In private `wp-config.php`, before WordPress loads, select that exact code:

```php
// Replace this with the unique lowercase error code returned by your host policy.
define('RS_NEWSLETTER_HTTP_ISOLATION_ERROR_CODE', 'your_host_network_isolated');
```

The plugin permits only the exact in-progress request, approved route, configured location/origin and bounded transport. It retains SSL verification, disables redirects, caps responses and never forwards CRM credentials to articles or saved-body storage. A nested request cannot borrow another request's authorization. Unrelated, generic and mixed errors remain blocked. The bridge is disabled by default; adding or changing its code changes the configuration fingerprint and requires fresh acceptance.

A host policy may alternatively use the read-only `\CleverSpeed\Newsletter\Client::allowsHttp($url, $args)` decision inside its own `pre_http_request` filter. That method grants no persistent authorization and registers no global exception. No network policy is needed merely because a site is headless.

### Migrating a newsletter compatibility adapter

Pause the scoped worker, choose Off, and take a private rollback backup of plugin files, configuration and database. Identify only the newsletter adapter and its request-policy and HTML-comparison helpers. Keep previews, redirects and other CMS protections for a separate migration.

Install the reviewed release and configure the explicit host integration if needed. An older version-pinned adapter may intentionally stop allowing requests after an update. Replace its newsletter-specific behavior in staging first; do not activate overlapping adapters or bypass a version check to force it through. Verify staff/location reads, eligible recipients and public article validation while Off, using the documented controlled procedure. After recording the read/setup acceptance gate, create an unsent draft and verify its full saved body in Draft-only mode. Reconcile any existing remote campaigns before proceeding.

After the new plugin passes acceptance, remove only the obsolete newsletter adapter and its two helper files from the live installation in a controlled maintenance window, repeat the read checks, and verify the scheduler and preserved ledger before resuming the previously accepted mode. Keep a rollback copy outside the web root. Removal is a separate deployment operation; this release does not delete must-use files automatically.
