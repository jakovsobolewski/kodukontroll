#!/bin/sh
# Renders the three report templates to PDF with headless Chrome.
# Usage: sh assets/report/template/build.sh   (set CHROME=/path/to/chrome on other systems)
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
OUT="$DIR/.."
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
render() {
  "$CHROME" --headless=new --disable-gpu --no-pdf-header-footer --virtual-time-budget=12000 \
    --print-to-pdf="$OUT/$2" "file://$DIR/$1" 2>/dev/null
  echo "built $2"
}
render report-et.html kodukontroll-naidisaruanne-et.pdf
render report-en.html kodukontroll-sample-report-en.pdf
render report-ru.html kodukontroll-primer-otcheta-ru.pdf
