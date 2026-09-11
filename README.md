# Kodukontroll — Sõltumatu kontroll

Static marketing site for an independent inspection service in Estonia:
renovation work, built-in furniture, appliance installation, plumbing and
electrical work are checked before the client signs the acceptance act.
No build step, no dependencies. Plain HTML/CSS/JS.

Brand and domain: **Kodukontroll / kodukontroll.ee**. Legal entity: Little Kris OÜ.
Site copy follows `Kodukontroll_итоговый_текст_сайта.docx` (Russian original);
the Estonian and English pages are translations of it.

## Files

```
index.html          One-page site, Estonian (default)
en/index.html       Same page in English
ru/index.html       Same page in Russian
404.html            Not-found page (Estonian)
.htaccess           HTTPS + www redirect, old-URL redirects, clean URLs, caching, 404
robots.txt, sitemap.xml, favicon.svg
assets/css/style.css    All styling + animation system; light only
assets/js/main.js       Nav, scroll progress, reveal, tabs, timeline, count-up, FAQ, form
```

Each page has the same sections, in this order, with the same anchor ids in
every language so links work across languages:

`#when` · `#checks` · `#report` · `#process` · `#pricing` · `#levels` · `#why` ·
`#story` (+ `#team`) · `#faq` · `#contact` (+ `#privacy`)

The old sub-pages (`/teenus`, `/hinnakiri`, `/meist`, `/kontakt`) redirect to
the matching section via `.htaccess`.

## Placeholders to replace before launch

| What | Where | Current value |
|---|---|---|
| Phone / WhatsApp | every page (`tel:`, `wa.me`, JSON-LD) | `+372 5555 5555` |
| Email | every page, `data-to` on forms | `info@kodukontroll.ee` |
| Privacy policy text | `#privacy` on every page | short generic placeholder — review with a lawyer |

Quick find: `grep -rn "5555 5555\|37255555555\|info@kodukontroll.ee" --include=*.html .`

## Editing content

The three HTML files are hand-editable. If you change structure, edit all
three so the anchors and section order stay identical. Prices live in the
`#pricing` cards (`data-count` drives the count-up animation — keep it equal
to the visible number).

## Animations

All motion is CSS-first with a small JS layer (`assets/js/main.js`):

- Hero: headline rises word by word, the kitchen drawing draws itself
  (`pathLength="1"` + `stroke-dashoffset`), findings markers pop with a pulse
  ring, a red "scan" line sweeps the sheet.
- Scroll: `[data-reveal]` elements fade up when they enter the viewport
  (`--i` staggers siblings); the process timeline fills as you scroll and
  marks passed steps; prices count up on first view.
- Interaction: tab switch with sliding indicator (keyboard accessible), FAQ
  accordion with animated height, sliding-fill buttons, active nav underline,
  reading-progress bar at the top.
- `prefers-reduced-motion: reduce` disables all of it.

## Contact form

The form has no backend. On submit it opens the visitor's mail client with a
prefilled message (`mailto:`) built from the field labels, so it works in all
languages. Attachments (photos, drawings) are added in the mail client.
To switch to a real backend (Formspree, Web3Forms, your own endpoint), give
the `<form>` an `action` + `method="post"` and remove the submit handler in
`assets/js/main.js`.

## Design

- Fonts: Newsreader (ET/EN text), Literata (RU text — Newsreader has no
  Cyrillic), IBM Plex Mono (labels, numbers). Loaded from Google Fonts.
- Palette: paper, ink and one marker red for findings. Tokens at the top of `style.css`.

## Local preview

```bash
python3 -m http.server 8080
```

Then open http://localhost:8080/. Absolute paths (`/assets/...`) are used, so
serve from the project root rather than opening files directly.

## Deploy

Hosted on **Hostinger** web hosting, deployed from this GitHub repository with
hPanel's Git integration (Websites → kodukontroll.ee → Advanced → Git).
The repo is pulled into `public_html`; the branch is `main`.

- Auto-deploy: hPanel's Git page provides a webhook URL. It is registered as a
  push webhook on this repo, so every push to `main` redeploys.
- Manual redeploy: hPanel → Git → Deploy.
- `.htaccess` is active on Hostinger (Apache/LiteSpeed).
- SSL: enable the free Let's Encrypt certificate in hPanel → Security → SSL
  once the domain points at the hosting.
