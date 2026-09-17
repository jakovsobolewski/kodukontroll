#!/usr/bin/env python3
"""Build the gated preview of a report: page 1 as it is, pages 2+ blurred.

Called by build.sh, which has already rendered the full report to PDF. Here the
pages after the cover are rasterised, blurred (the running header and footer
strips are pasted back sharp) and re-assembled into a preview HTML that build.sh
prints to the PDF the site links.

Blurring in the browser instead would work, but Chrome rasterises every filtered
layer at 300 ppi lossless, which turned a 0.7 MB report into 8.5 MB.

Usage: make-preview.py <lang> <full.pdf> <out.html>
"""
import re
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageFilter

DPI = 200
MM = DPI / 25.4                # pixels per mm
PAGE_H_MM = 296.8
SHARP_TOP_MM = 27              # running header band, kept sharp
SHARP_BOTTOM_MM = 280          # everything below this is the footer band
BLUR_PX = 9.5                  # ~4.6 px at 96 dpi, the on-screen equivalent

MARK = ('<svg class="lock__mark" viewBox="0 0 64 64" aria-hidden="true">'
        '<circle cx="32" cy="32" r="28.5" fill="none" stroke="#6E6E73" stroke-width="4"/>'
        '<path d="M23.5 17v30M42 17 23.5 35.5M29 30l13.5 17" fill="none" stroke="currentColor" '
        'stroke-width="6" stroke-linejoin="miter"/></svg>')

COPY = {
    "et": ("Näidisaruande eelvaade",
           "Sellest leheküljest alates on tekst hägustatud — näete ainult aruande "
           "ülesehitust. Täitke kodukontroll.ee päringuvorm ja saadame teile kogu "
           "näidisaruande loetaval kujul."),
    "en": ("Sample report preview",
           "From this page on the text is blurred — you see the structure of the report "
           "only. Fill in the form at kodukontroll.ee and we will send you the complete "
           "sample report."),
    "ru": ("Предпросмотр примера отчёта",
           "Начиная с этой страницы текст размыт — видна только структура отчёта. "
           "Заполните форму на kodukontroll.ee, и мы пришлём вам полный пример отчёта."),
}
FOOT = "kodukontroll.ee · info@kodukontroll.ee"


def blurred_pages(pdf, out_dir, stem):
    """Rasterise every page after the cover and blur its body."""
    subprocess.run(["pdftoppm", "-png", "-r", str(DPI), "-f", "2", str(pdf),
                    str(out_dir / f"{stem}-raw")], check=True)
    pages = sorted(out_dir.glob(f"{stem}-raw-*.png"))
    if not pages:
        sys.exit("pdftoppm produced no pages — is poppler installed?")

    out = []
    for src in pages:
        im = Image.open(src).convert("RGB")
        blur = im.filter(ImageFilter.GaussianBlur(BLUR_PX))
        top, bottom = round(SHARP_TOP_MM * MM), round(SHARP_BOTTOM_MM * MM)
        blur.paste(im.crop((0, 0, im.width, top)), (0, 0))
        blur.paste(im.crop((0, bottom, im.width, im.height)), (0, bottom))
        dst = out_dir / f"{stem}-{src.stem.rsplit('-', 1)[1]}.jpg"
        blur.save(dst, "JPEG", quality=80, optimize=True, progressive=True)
        src.unlink()
        out.append(dst.name)
    return out


def main():
    lang, pdf, out_html = sys.argv[1], Path(sys.argv[2]), Path(sys.argv[3])
    source = Path(__file__).with_name(f"report-{lang}.html").read_text()
    title, text = COPY[lang]

    # head + cover: everything before the first non-cover page
    head = source[:source.index('<section class="page">')]
    head = re.sub(r'<!-- =+ 2 .*?-->\s*$', '', head, flags=re.S)

    lock = (f'<div class="lock"><div class="lock__card">{MARK}'
            f'<p class="lock__title">{title}</p>'
            f'<p class="lock__text">{text}</p>'
            f'<p class="meta">{FOOT}</p></div></div>')

    pages = blurred_pages(pdf, out_html.parent, f"preview-{lang}")
    body = "\n".join(
        f'<section class="page page--preview"><img class="page__shot" src="{name}" alt="">{lock}</section>'
        for name in pages)
    out_html.write_text(f"{head}{body}\n\n</body>\n</html>\n")
    print(f"preview-{lang}: {len(pages)} gated pages")


if __name__ == "__main__":
    main()
