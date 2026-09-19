#!/usr/bin/env python3
"""Resize the Kodukontroll mark (assets/img/mark.png) into the site favicons.

mark.png is the blueprint K-in-a-circle cropped square from the logo, without
the wordmark. Run from the repository root:  python3 assets/img/make-favicon.py
Writes favicon-16/32/192/512.png and apple-touch-icon.png, then prints the
md5-based `?v=` token to paste into the <link> tags of every page.
"""
import hashlib
from PIL import Image

SOURCE = "assets/img/mark.png"

# name, size, gamma applied after downscaling (>1 darkens the hairlines that
# would otherwise fade to grey at tab-icon sizes)
OUTPUTS = [
    ("favicon-16.png", 16, 2.2),
    ("favicon-32.png", 32, 1.6),
    ("favicon-192.png", 192, 1.0),
    ("favicon-512.png", 512, 1.0),
    ("apple-touch-icon.png", 180, 1.0),
]

mark = Image.open(SOURCE).convert("RGB")
for name, size, gamma in OUTPUTS:
    icon = mark.resize((size, size), Image.LANCZOS)
    if gamma != 1.0:
        icon = icon.point(lambda v: round(255 * (v / 255) ** gamma))
    icon.save(name, optimize=True)
    with open(name, "rb") as f:
        token = hashlib.md5(f.read()).hexdigest()[:8]
    print("wrote", name, f"{size}x{size}", "?v=" + token)

# Header mark: 128 px (4x the 32 px CSS size) with the white background
# lifted to transparency so it sits on the paper tint without a white square.
HEADER = "assets/img/mark-128.png"
icon = mark.resize((128, 128), Image.LANCZOS).convert("RGBA")
px = icon.load()
for y in range(128):
    for x in range(128):
        r, g, b, _ = px[x, y]
        lum = round(0.299 * r + 0.587 * g + 0.114 * b)
        a = 255 - lum
        if a == 0:
            px[x, y] = (0, 0, 0, 0)
            continue
        # un-composite from white: c = c' * a + 255 * (1 - a)
        un = lambda c: max(0, min(255, round((c - 255 * (1 - a / 255)) / (a / 255))))
        px[x, y] = (un(r), un(g), un(b), a)
icon.save(HEADER, optimize=True)
with open(HEADER, "rb") as f:
    print("wrote", HEADER, "128x128", "?v=" + hashlib.md5(f.read()).hexdigest()[:8])
