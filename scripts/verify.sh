#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
echo '[1/4] PHP syntax'
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find backend frontend -name '*.php' -print0; printf '%s\0' install.php index.php)
echo '[2/4] JavaScript syntax'
while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find backend frontend/assets/js scripts -name '*.js' -print0)
echo '[3/4] V14 static structure'
node scripts/verify-static.js
echo '[4/4] FFmpeg availability'
if command -v ffmpeg >/dev/null 2>&1; then ffmpeg -version | head -n 1; else echo 'WARN: ffmpeg not found; media worker needs FFmpeg.'; fi
echo 'BUFF V14 static checks passed.'
