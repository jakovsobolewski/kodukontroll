# Kodukontroll — Mööbli tehniline järelevalve

Static marketing site for an independent furniture-installation inspection
service in Estonia ("owner's supervision, but for premium furniture").
No build step, no dependencies. Plain HTML/CSS/JS.

The brand and domain are **Kodukontroll / kodukontroll.com**. Search and
replace the placeholder contact details listed below before publishing.

## Files

```
index.html          Home (ET) — hero, why, construction analogy, 8 check areas,
                    process, sample report, audiences, pricing, FAQ, CTA
teenus.html         Full checklist by furniture type, severity grades, equipment, timing
hinnakiri.html      Packages, add-on price table, terms
meist.html          About, values, B2B framework agreement
kontakt.html        Booking form + contact details + service area
en/index.html       English one-page version with its own contact form
404.html            Not-found page
.htaccess           HTTPS + www redirect, extensionless URLs, caching, 404
robots.txt, sitemap.xml, favicon.svg
assets/css/style.css        All styling; light/dark via prefers-color-scheme
assets/js/main.js           Mobile nav, contact form, footer year
```

## Placeholders to replace before launch

| What | Where | Current value |
|---|---|---|
| Phone | every page (`tel:` links, JSON-LD) | `+372 5000 0000` |
| Email | every page, `data-to` on forms | `info@kodukontroll.com` |
| Company name / reg. number | footers, `meist.html` | `Kodukontroll OÜ` |
| Domain | canonical/og/sitemap/robots | `kodukontroll.com` |
| Prices | `index.html`, `hinnakiri.html`, `en/index.html`, JSON-LD | 290 € / 590 € / add-ons |
| Service area | `kontakt.html`, `en/index.html` | Tallinn, Tartu, Pärnu… |

Quick find: `grep -rn "5000 0000\|kodukontroll.com\|Kodukontroll OÜ" --include=*.html .`

## Contact form

The form has no backend. On submit it opens the visitor's mail client with a
prefilled message (`mailto:`), so nothing is lost if hosting is static-only.
To switch to a real backend (Formspree, Netlify Forms, Web3Forms, your own
endpoint), give the `<form>` an `action` + `method="post"` and remove the
`submit` handler in `assets/js/main.js`.

## Design

- Fonts: Newsreader (all text) + IBM Plex Mono (labels, numbers), loaded from Google Fonts.
- Palette: paper, ink and one marker red for findings. Tokens at the top of `style.css`. Light only.
- Every page is server-independent; the `.htaccess` only adds clean URLs and redirects.

## Local preview

```bash
python3 -m http.server 8080
```

Then open http://localhost:8080/. Absolute paths (`/assets/...`) are used, so
serve from the project root rather than opening files directly.

## Deploy

Upload everything except `README.md` to the web root. Any static host works
(Zone, Veebimajutus, Netlify, Cloudflare Pages). `.htaccess` is only read by Apache.
