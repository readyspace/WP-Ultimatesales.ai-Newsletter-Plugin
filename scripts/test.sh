#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
find cleverspeed-newsletter tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
for suite in policy worker credential admin config activation; do
  php "tests/${suite}-test.php" cleverspeed-newsletter
done
