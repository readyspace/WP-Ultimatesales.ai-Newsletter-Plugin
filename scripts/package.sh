#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
archive="dist/wp-ultimatesales-ai-newsletter-0.3.0-alpha.1.zip"
if test -e "$archive"; then
  echo 'Archive already exists; inspect or move it before rebuilding.' >&2
  exit 1
fi
zip "$archive" cleverspeed-newsletter/cleverspeed-newsletter.php \
  cleverspeed-newsletter/includes/config.php cleverspeed-newsletter/includes/policy.php \
  cleverspeed-newsletter/includes/client.php cleverspeed-newsletter/includes/credential.php
unzip -l "$archive"
