#!/bin/sh
# Renders the partner flyers to PDF with headless Chrome.
#
# A partner flyer is a one-page PDF that a partner forwards to its own clients
# by e-mail, with a partner discount code. It is not linked from the site. Output lands in assets/flyer/out/
# (git-ignored); copy the PDF wherever it is needed.
#
# Usage: sh assets/flyer/build.sh   (set CHROME=/path/to/chrome on other systems)
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
OUT="$DIR/out"
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
mkdir -p "$OUT"

render() {  # render <source name without .html> <target.pdf>
  "$CHROME" --headless=new --disable-gpu --no-pdf-header-footer --virtual-time-budget=12000 \
    --print-to-pdf="$OUT/$2" "file://$DIR/$1.html" 2>/dev/null
  echo "built out/$2"
}

render flyer-lahe-et Kodukontroll_Lahe_Kinnisvara_LAHE20.pdf
