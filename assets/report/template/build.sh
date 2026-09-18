#!/bin/sh
# Renders the three report templates to PDF with headless Chrome.
#
# These PDFs are NOT published on the site: the sample report is sent by e-mail
# to people who fill in the request form in the "Report" section. The same
# templates are what a real client report is produced from.
#
# Output lands in assets/report/template/out/ (git-ignored).
#
# Usage: sh assets/report/template/build.sh   (set CHROME=/path/to/chrome on other systems)
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
OUT="$DIR/out"
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
mkdir -p "$OUT"

render() {  # render <lang> <target.pdf>
  "$CHROME" --headless=new --disable-gpu --no-pdf-header-footer --virtual-time-budget=12000 \
    --print-to-pdf="$OUT/$2" "file://$DIR/report-$1.html" 2>/dev/null
  echo "built out/$2"
}

render et kodukontroll-naidisaruanne-et.pdf
render en kodukontroll-sample-report-en.pdf
render ru kodukontroll-primer-otcheta-ru.pdf
