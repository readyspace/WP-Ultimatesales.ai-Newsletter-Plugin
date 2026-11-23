# Release 0.4.1 — review candidate

Prepared on 23 November 2026. The previously published WordPress.org release is 0.4.0. This document prepares the source update and explains publication; it does not record a WordPress.org release or a live-site deployment.

## What changes

Tools -> UltimateSales.AI Newsletter gains a read-only Sending configuration section for administrators with `manage_options`. It shows this installation's configured publication and public origin, provider location, sender name/email, Reply-To, confirmed/pending/unsubscribed tags, saved sending mode and recorded connection gate. The sender name is the configured `brand`, matching the existing campaign payload.

The section explains the existing eligibility, email/contact Do Not Disturb, excluded-tag and duplicate-email rules. Configured values are not presented as freshly verified provider data. Missing or invalid values are labelled; all output is escaped. Only allowlisted fields are displayed.

No current eligible-recipient count is available without retrieving and paginating private contact records. The screen explicitly reports **Unavailable — not checked** and **Not checked** for the timestamp. It performs no provider request and does not infer a count from old jobs. Counts would not establish consent, delivery or future eligibility.

Sending code, credentials, configuration fingerprints, audience tags, consent, DND, jobs, scheduler and duplicate protection retain their existing behavior. The update requires no configuration or database migration. Do not re-save credentials or change sending mode simply to display the new information.

## Review and validation

- The original manually assembled patch was corrected against actual GitHub source at `634fdbf697d3552bc2949823c03834e427dcc808` before use.
- Local PHP 8.4.27 validation: all 22 syntax checks and 1,065 isolated assertions passed, including 42 new display assertions. All provider calls use fictional fixtures; no newsletter was sent.
- The allowlisted package builder includes the new helper. The main PHP header, admin version, readme Stable tag and integration-test expected version are 0.4.1.
- Before release, inspect the pull request's final-commit CI: PHP 8.1/8.3/8.4, official Plugin Check, and WordPress/MySQL integration jobs. The existing upgrade laboratory exercises the published 0.3.3 baseline, both standard/headless layouts and default/restricted-host profiles. Passing it is not live-provider acceptance.
- Review the new screen on an isolated WordPress installation with fictional configuration and a non-administrator account. Confirm the displayed identity matches that installation, no secret/contact appears, and loading the screen leaves settings and jobs unchanged. This task does not deploy to a site or authorize a test send.

## Publish an update to the existing listing

The existing listing is https://wordpress.org/plugins/readyspace-newsletter-for-ultimatesales-ai/ and its SVN repository is https://plugins.svn.wordpress.org/readyspace-newsletter-for-ultimatesales-ai/ . GitHub contains development source; WordPress.org serves releases from its SVN repository. The GitHub Actions workflow currently tests code and does not publish it to WordPress.org.

### 1. Review, merge and package

After reviewing the pull request and its checks, merge the approved source into `main`. In a clean local checkout of that reviewed revision, with PHP 8.1+ and the documented extensions, Python 3, Git, unzip and SVN available:

```bash
git switch main
git pull --ff-only
git status --short
bash scripts/test.sh
bash scripts/package.sh --submission
```

Stop on any failure or unexpected local change. The output is `dist/readyspace-newsletter-for-ultimatesales-ai-0.4.1.zip`. This build command creates a local package; it does not publish it. Do not upload the complete GitHub source ZIP as a plugin.

### 2. Prepare WordPress.org access

Use a WordPress.org account with commit access to this plugin. The 0.4.0 release record identifies `readyspace`; verify that this is still the intended authorized account. Enable the required two-factor authentication, then use **Profile -> Account & Security -> SVN credentials** to generate an SVN-specific password if one is needed. Enter it only at the SVN password prompt, never in Git, a command argument or chat. Use your exact case-sensitive username, not your email address.

### 3. Stage the release locally

Run the following from the Git repository root after the package and review are complete. Use a new SVN working copy separate from Git:

```bash
source_repo="$(pwd)"
release_root="$(mktemp -d "${TMPDIR:-/tmp}/rs-newsletter-release.XXXXXX")"
unzip -q "$source_repo/dist/readyspace-newsletter-for-ultimatesales-ai-0.4.1.zip" -d "$release_root/package"
svn checkout https://plugins.svn.wordpress.org/readyspace-newsletter-for-ultimatesales-ai/ "$release_root/svn"
cd "$release_root/svn"
svn status
svn list tags
```

The working copy must be clean and `tags/0.4.1` must not already exist. If 0.4.1 has already been released, stop; never rewrite its tag. Choose a new version and repeat versioning, validation and packaging.

For this update, no existing runtime file is removed and one helper is added:

```bash
cp -R "$release_root/package/readyspace-newsletter-for-ultimatesales-ai/." trunk/
svn add trunk/includes/sending-configuration-status.php
svn copy trunk tags/0.4.1
svn status
svn diff
diff -rq "$release_root/package/readyspace-newsletter-for-ultimatesales-ai" trunk
diff -rq trunk tags/0.4.1
```

Both file comparisons should produce no differences. Inspect SVN status and diff: only `trunk` and the new 0.4.1 tag should change. Preserve existing tags and top-level directory artwork in `assets`. `trunk/cleverspeed-newsletter.php` must exist directly; do not create an extra nested plugin folder. Do not copy tests, Git history, private configuration or the ZIP file into SVN.

### 4. Publish only after release review

The following command is the external publishing step. Run it only after approving the exact release contents, using your verified WordPress.org committer username in place of `YOUR_WORDPRESS_ORG_USERNAME`:

```bash
svn commit --username YOUR_WORDPRESS_ORG_USERNAME --no-auth-cache -m "Release 0.4.1: show read-only sending configuration"
```

SVN prompts for the SVN-specific password. If Release Confirmation is enabled for this plugin, follow the emailed link and confirm the version in WordPress.org's Release Management dashboard. The feature is optional; its current state for this plugin has not been inspected.

### 5. Verify publication and roll out

Check the directory page version, download the official versioned ZIP, and compare its extracted files against the reviewed package. ZIP container hashes can differ; the file contents must match. Record the SVN revision and verified download in the release notes. WordPress.org applies automated security review and a cooldown before dashboard update distribution; do not assume immediate update availability. Monitor the committer inbox for any blocked-release findings.

Publishing the directory release can offer updates to installed sites, including sites with automatic updates enabled. Plan production rollout separately. Preserve the plugin slug/folder, private configuration, encrypted credential, ledger and cron identifiers. Back up before an approved site update; verify the read-only screen and retained state without creating a test campaign. For a rollback, restore the previous plugin files from a reviewed backup without reverting the live database or replaying jobs. A later public correction uses a new version; do not alter an already released tag.

## Official references

- [Using Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [How plugin readmes and Stable tags work](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [WordPress.org SVN credentials](https://make.wordpress.org/meta/handbook/tutorials-guides/svn-access/)
- [Release Confirmation](https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/)
- [Automated security review](https://developer.wordpress.org/plugins/wordpress-org/automated-security-review/)
