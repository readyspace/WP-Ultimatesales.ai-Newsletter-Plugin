#!/usr/bin/env bash
# Installs only a disposable WordPress/MySQL laboratory; never use a site database.
set -euo pipefail
cd "$(dirname "$0")/.."
repository=$(pwd)
: "${WP_VERSION:?Set the exact released WordPress version to test}"
: "${WP_DB_PASSWORD:?Set the fictional local test database password}"
WP_DB_HOST=${WP_DB_HOST:-127.0.0.1:3306}
WP_DB_NAME=${WP_DB_NAME:-rs_newsletter_test}
WP_DB_USER=${WP_DB_USER:-root}
if [[ ${RS_ALLOW_TEST_DB_RESET:-} != 1 || ! $WP_DB_NAME =~ ^rs_newsletter_test(_[a-zA-Z0-9_]+)?$ || ! $WP_DB_HOST =~ ^(127\.0\.0\.1|localhost)(:[0-9]+)?$ ]]; then
  echo 'Refusing to reset a database outside the explicitly enabled local test laboratory.' >&2
  exit 1
fi
command -v wp >/dev/null
command -v php >/dev/null
work=$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/rs-newsletter-wordpress.XXXXXX")
trap 'rm -rf "$work"' EXIT
core="$work/wordpress"
baseline="$work/baseline"
mkdir -p "$core" "$baseline"
# Pinned source corresponding to the verified public 0.3.3 release.
git archive 889da2c21e4eff0ab9598418d6edf858001e97f4 cleverspeed-newsletter | tar -x -C "$baseline"
wp --path="$core" core download --version="$WP_VERSION" --locale=en_US --force
wp --path="$core" core verify-checksums --version="$WP_VERSION"
wp --path="$core" config create --dbname="$WP_DB_NAME" --dbuser="$WP_DB_USER" --dbpass="$WP_DB_PASSWORD" --dbhost="$WP_DB_HOST" --skip-check --extra-php <<'PHP'
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
$standard = getenv('RS_TEST_LAYOUT') === 'standard';
define('RS_NEWSLETTER_CONFIG', [
    'location_id'=>'test-location-123',
    'confirmed_tag'=>'newsletter-confirmed',
    'pending_tag'=>'newsletter-pending',
    'unsubscribed_tag'=>'newsletter-unsubscribed',
    'cms_origin'=>$standard ? 'https://example.org' : 'https://cms.example.org',
    'public_origin'=>'https://example.org',
    'brand'=>'Example Newsletter',
    'from_email'=>'newsletter@example.org',
    'reply_email'=>'reply@example.org',
    'timezone'=>'UTC',
    'legal_name'=>'Example Organisation',
    'postal_address'=>'Example postal address; replace before use',
    'privacy_url'=>'https://example.org/privacy/',
    'preview_text'=>'Practical ideas from our latest article.',
]);
if (getenv('RS_TEST_ISOLATION') === 'guard') {
    define('RS_NEWSLETTER_HTTP_ISOLATION_ERROR_CODE', 'rs_test_headless_http_blocked');
}
PHP
slug=readyspace-newsletter-for-ultimatesales-ai
plugin="$core/wp-content/plugins/$slug"
test_file="$repository/tests/wordpress-integration-test.php"
for layout in standard headless; do
  for isolation in default guard; do
    export RS_TEST_LAYOUT=$layout RS_TEST_ISOLATION=$isolation
    echo "Testing WordPress $WP_VERSION / PHP $(php -r 'echo PHP_VERSION;') / $layout / $isolation"
    wp --path="$core" db reset --yes
    rm -rf "$plugin"
    mkdir -p "$plugin"
    cp -R "$baseline/cleverspeed-newsletter/." "$plugin/"
    if [[ $layout == standard ]]; then site_url=https://example.org; else site_url=https://cms.example.org; fi
    wp --path="$core" core install --url="$site_url" --title='Fictional newsletter laboratory' --admin_user=test-owner --admin_password=fictional-test-password-only --admin_email=owner@example.org --skip-email
    RS_TEST_PHASE=before_activation wp --path="$core" --skip-plugins --skip-themes eval-file "$test_file"
    wp --path="$core" --skip-themes plugin activate "$slug"
    RS_TEST_PHASE=seed wp --path="$core" --skip-themes eval-file "$test_file"
    # Normal directory upgrades keep the active plugin entry and do not deactivate.
    rm -rf "$plugin"
    mkdir -p "$plugin"
    cp -R "$repository/cleverspeed-newsletter/." "$plugin/"
    RS_TEST_PHASE=test wp --path="$core" --skip-themes eval-file "$test_file"
  done
done
echo "PASS WordPress $WP_VERSION integration matrix (fictional data; all newsletter HTTP intercepted; no emails)"
