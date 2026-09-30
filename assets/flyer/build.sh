#!/bin/sh
# Renders the partner flyer ("visiitkaart") to PDF with headless Chrome.
#
# The flyer is the one-page PDF that partners forward to their customers by
# e-mail. It is not linked from the site. Output lands in assets/flyer/out/
# (git-ignored); copy the PDF wherever it is needed.
#
# Usage: sh assets/flyer/build.sh   (set CHROME=/path/to/chrome on other systems)
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
OUT="$DIR/out"
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
mkdir -p "$OUT"

render() {  # render <lang> <target.pdf>
  "$CHROME" --headless=new --disable-gpu --no-pdf-header-footer --virtual-time-budget=12000 \
    --print-to-pdf="$OUT/$2" "file://$DIR/flyer-$1.html" 2>/dev/null
  echo "built out/$2"
}

render et kodukontroll-visiitkaart-et.pdf
