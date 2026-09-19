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
