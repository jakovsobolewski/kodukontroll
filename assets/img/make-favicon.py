#!/usr/bin/env python3
"""Draw the Kodukontroll mark: a white Helvetica K on an ink tile.

Run from the repository root:  python3 assets/img/make-favicon.py
Writes favicon-16/32/192/512.png and apple-touch-icon.png.
"""
from PIL import Image, ImageDraw, ImageFont

INK = (29, 29, 31, 255)      # --ink
PAPER = (255, 255, 255, 255)
FONT = "/System/Library/Fonts/Helvetica.ttc"
FONT_INDEX = 1               # Bold
SS = 8                       # supersampling factor

# size -> (corner radius as a share of the tile, cap height as a share)
OUTPUTS = [
    ("favicon-16.png", 16, 0.16),
    ("favicon-32.png", 32, 0.20),
    ("favicon-192.png", 192, 0.22),
    ("favicon-512.png", 512, 0.22),
    ("apple-touch-icon.png", 180, 0.0),   # iOS masks its own corners
]
CAP = 0.56                   # K cap height as a share of the tile


def render(size, radius_share):
    s = size * SS
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    r = round(s * radius_share)
    if r:
        d.rounded_rectangle((0, 0, s - 1, s - 1), radius=r, fill=INK)
    else:
        d.rectangle((0, 0, s - 1, s - 1), fill=INK)

    # Size the glyph by its own cap height, then centre on the ink bounds.
    font_px = s
    font = ImageFont.truetype(FONT, font_px, index=FONT_INDEX)
    box = d.textbbox((0, 0), "K", font=font)
    font_px = round(font_px * (s * CAP) / (box[3] - box[1]))
    font = ImageFont.truetype(FONT, font_px, index=FONT_INDEX)
    box = d.textbbox((0, 0), "K", font=font)
    x = (s - (box[2] - box[0])) / 2 - box[0]
    y = (s - (box[3] - box[1])) / 2 - box[1]
    d.text((x, y), "K", font=font, fill=PAPER)
    return img.resize((size, size), Image.LANCZOS)


for name, size, radius in OUTPUTS:
    render(size, radius).save(name)
    print("wrote", name, f"{size}x{size}")
