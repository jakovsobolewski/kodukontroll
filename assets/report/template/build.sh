#!/bin/sh
# Renders the three report templates to PDF with headless Chrome.
#
# The published sample is gated: page 1 is the real cover, the pages after it
# ship as blurred images with a notice pointing at the form on the site. Pass
# FULL=1 to skip the gate and render the complete, readable report instead —
# that is what you want when producing a real report from a template.
#
# Usage: sh assets/report/template/build.sh   (set CHROME=/path/to/chrome on other systems)
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
OUT="$DIR/.."
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
TMP="$DIR/.build"
mkdir -p "$TMP"
trap 'rm -rf "$TMP"' EXIT

pdf() {  # pdf <source.html> <target.pdf>
  "$CHROME" --headless=new --disable-gpu --no-pdf-header-footer --virtual-time-budget=12000 \
    --print-to-pdf="$2" "file://$1" 2>/dev/null
}

render() {  # render <lang> <target.pdf>
  if [ -n "$FULL" ]; then
    pdf "$DIR/report-$1.html" "$OUT/$2"
  else
    pdf "$DIR/report-$1.html" "$TMP/full-$1.pdf"
    python3 "$DIR/make-preview.py" "$1" "$TMP/full-$1.pdf" "$DIR/preview-$1.html"
    pdf "$DIR/preview-$1.html" "$OUT/$2"
    rm -f "$DIR/preview-$1.html" "$DIR/preview-$1"-*.jpg
  fi
  echo "built $2"
}

render et kodukontroll-naidisaruanne-et.pdf
render en kodukontroll-sample-report-en.pdf
render ru kodukontroll-primer-otcheta-ru.pdf
