#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
kind="${1:---candidate}"
if [[ "$kind" != --candidate && "$kind" != --submission ]]; then
  echo 'Usage: bash scripts/package.sh [--candidate|--submission]' >&2
  exit 1
fi
python3 - "$kind" <<'PY'
import hashlib
import re
import sys
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo
source = Path('cleverspeed-newsletter')
slug = 'readyspace-newsletter-for-ultimatesales-ai'
header = (source / 'cleverspeed-newsletter.php').read_text()
version = re.search(r'^ \* Version: (.+)$', header, re.M).group(1)
files = ['cleverspeed-newsletter.php', 'includes/config.php', 'includes/policy.php', 'includes/client.php', 'includes/credential.php', 'readme.txt']
if (source / 'LICENSE').is_file():
    files.append('LICENSE')
if sys.argv[1] == '--submission':
    readme = (source / 'readme.txt').read_text()
    if not re.fullmatch(r'\d+(?:\.\d+)*', version):
        sys.exit('Submission build blocked: WordPress.org requires a numeric version with periods only.')
    if f'Stable tag: {version}\n' not in readme:
        sys.exit('Submission build blocked: Stable tag must match the numeric release version.')
    if 'LICENSE' not in files or ' * License: GPLv2 or later' not in header or 'License: GPLv2 or later' not in readme:
        sys.exit('Submission build blocked: owner-approved GPLv2-or-later licence is required. Use --candidate for review only.')
    if not re.search(r'^ \* Requires at least: \d', header, re.M) or not re.search(r'^Tested up to: \d', readme, re.M):
        sys.exit('Submission build blocked: verified WordPress compatibility metadata is required.')
    suffix = ''
else:
    suffix = '-REVIEW-ONLY'
dist = Path('dist'); dist.mkdir(exist_ok=True)
archive = dist / f'{slug}-{version}{suffix}.zip'
# Deterministic allowlist: no Git history, tests, customer configuration, credentials or artwork.
with ZipFile(archive, 'w', ZIP_DEFLATED) as package:
    for name in files:
        entry = ZipInfo(f'{slug}/{name}', (2026, 10, 8, 0, 0, 0))
        entry.compress_type = ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        package.writestr(entry, (source / name).read_bytes())
with ZipFile(archive) as package:
    if package.testzip() is not None:
        sys.exit('Archive integrity failure.')
    for name in package.namelist():
        print(name)
sha = hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix('.zip.sha256').write_text(f'{sha}  {archive.name}\n')
print(f'{archive}: {archive.stat().st_size} bytes; SHA-256 {sha}')
if suffix:
    print('REVIEW ONLY: this archive is installable for isolated testing, not cleared for WordPress.org submission.')
PY
