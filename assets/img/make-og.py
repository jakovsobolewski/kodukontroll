#!/usr/bin/env python3
"""Draw the Kodukontroll share card, one per language.

Run from the repository root:  python3 assets/img/make-og.py
Writes assets/img/og-et.png, og-en.png, og-ru.png (1200x630).

Greyscale only, same tokens as style.css: warm paper, ink type, the K tile
from make-favicon.py as the mark.
"""
from PIL import Image, ImageDraw, ImageFont

W, H = 1200, 630
SS = 2                                   # supersampling factor
PAPER = (248, 246, 242, 255)             # --paper
INK = (29, 29, 31, 255)                  # --ink / --mark
INK2 = (110, 110, 115, 255)              # --check
RULE = (214, 211, 205, 255)              # --line
HEAD = "/System/Library/Fonts/Helvetica.ttc"
HEAD_BOLD, HEAD_REG = 1, 0

CARDS = {
    "et": ("Remondi, mööbli ja tehnika", "sõltumatu kontroll.",
           "Kontrollime enne, kui te töö vastu võtate.",
           "Tallinn ja Harjumaa  ·  aruanne fotodega  ·  alates 149 €"),
    "en": ("Independent inspection of renovation,", "furniture and appliances.",
           "We check the work before you accept it.",
           "Tallinn and Harju County  ·  photo report  ·  from €149"),
    "ru": ("Независимая проверка ремонта,", "мебели и техники.",
           "Проверяем до того, как вы примете работу.",
           "Таллинн и Харьюмаа  ·  отчёт с фото  ·  от 149 €"),
}


def font(px, index=HEAD_BOLD):
    return ImageFont.truetype(HEAD, px, index=index)


def tile(d, x, y, size):
    """The K mark: white Helvetica K on a rounded ink tile."""
    d.rounded_rectangle((x, y, x + size, y + size), radius=round(size * 0.22), fill=INK)
    px = size
    f = font(px)
    box = d.textbbox((0, 0), "K", font=f)
    px = round(px * (size * 0.56) / (box[3] - box[1]))
    f = font(px)
    box = d.textbbox((0, 0), "K", font=f)
    d.text((x + (size - (box[2] - box[0])) / 2 - box[0],
            y + (size - (box[3] - box[1])) / 2 - box[1]), "K", font=f, fill=PAPER)


def render(lang):
    l1, l2, tagline, foot = CARDS[lang]
    img = Image.new("RGBA", (W * SS, H * SS), PAPER)
    d = ImageDraw.Draw(img)
    m = 64 * SS                                            # margin

    tile(d, m, m, 72 * SS)
    f_word = font(40 * SS)
    d.text((m + 96 * SS, m + 12 * SS), "KoduKontroll", font=f_word, fill=INK)

    f_head = font(62 * SS)
    d.text((m, 226 * SS), l1, font=f_head, fill=INK)
    d.text((m, 302 * SS), l2, font=f_head, fill=INK)

    f_lead = font(30 * SS, HEAD_REG)
    d.text((m, 408 * SS), tagline, font=f_lead, fill=INK2)

    d.line((m, 492 * SS, W * SS - m, 492 * SS), fill=RULE, width=1 * SS)
    f_foot = font(25 * SS, HEAD_REG)
    d.text((m, 524 * SS), foot, font=f_foot, fill=INK2)
    d.text((W * SS - m - d.textlength("kodukontroll.ee", font=f_foot), 524 * SS),
           "kodukontroll.ee", font=f_foot, fill=INK)

    img = img.resize((W, H), Image.LANCZOS).convert("RGB")
    out = f"assets/img/og-{lang}.png"
    img.save(out, optimize=True)
    print(out)


for lang in CARDS:
    render(lang)
