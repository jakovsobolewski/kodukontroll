# Kodukontroll — Sõltumatu kontroll

Static marketing site for an independent inspection service in Estonia:
renovation work, built-in furniture, appliance installation, plumbing and
electrical work are checked before the client signs the acceptance act.
No build step, no dependencies. Plain HTML/CSS/JS.

Brand and domain: **Kodukontroll / kodukontroll.ee** (registered at Zone.ee, DNS
delegated to Hostinger's nameservers, hosted on Hostinger). Legal entity: Little
Kris OÜ. kodukontroll.com is a parked alias that `.htaccess` redirects to .ee.
Site copy follows `Kodukontroll_итоговый_текст_сайта.docx` (Russian original);
the Estonian and English pages are translations of it.

## Files

```
index.html          One-page site, Estonian (default)
en/index.html       Same page in English
ru/index.html       Same page in Russian
404.html            Not-found page (Estonian)
.htaccess           HTTPS + www redirect, old-URL redirects, clean URLs, caching, 404
robots.txt, sitemap.xml, favicon-*.png, apple-touch-icon.png
assets/css/style.css    All styling + animation system; light only
assets/js/main.js       Nav, scroll progress, reveal, tabs, timeline, count-up, FAQ, form
assets/report/          Sample inspection report as PDF (ET/EN/RU), linked from the site
assets/report/template/ HTML+CSS source of the report — the template for real reports
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
| Privacy policy text | `#privacy` on every page | short generic placeholder — review with a lawyer |
| Inspector name | `assets/report/template/*.html`, signature block | `Ees- ja perekonnanimi` |

Quick find: `grep -rn "5555 5555\|37255555555" --include=*.html .`

The contact address everywhere (pages, forms, JSON-LD, report) is
**info@kodukontroll.ee**. The mailbox itself has to exist on the hosting — see
*Mailbox* under Deploy.

## Editing content

`style.css` and `main.js` are referenced with a `?v=<hash>` query string in all
four HTML files. `.htaccess` lets browsers cache CSS/JS for a month, so after
editing either file change the `?v=` value everywhere (any new string works),
otherwise visitors keep the old file and the layout breaks.

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

## Sample report

The "view a sample report" button in `#report`, the report card next to it and
the footer link open a PDF in a new tab:

```
assets/report/kodukontroll-naidisaruanne-et.pdf    (ET page)
assets/report/kodukontroll-sample-report-en.pdf    (EN page)
assets/report/kodukontroll-primer-otcheta-ru.pdf   (RU page)
```

The PDFs are rendered from `assets/report/template/report-{et,en,ru}.html` +
`report.css`, which is also the layout template for real reports: 6 fixed A4
pages (cover + summary, scope + location drawing, findings 1–4, 5–8, 9–11 +
check-point table, requirements + assessment + signature). Copy a template,
replace the text and swap the grey photo placeholders for `<img>` tags. Each
`.page` is a fixed A4 box with `overflow: hidden`, so keep each page's content
within it (open the HTML in a browser to check, or add a page).

Rebuild the PDFs after editing:

```bash
sh assets/report/template/build.sh
```

It uses headless Google Chrome (macOS path by default; set `CHROME=` for
another location). The sample data is illustrative (address, names, numbers
changed, photos replaced by placeholders); the counts match the numbers shown
on the site (96 check points, 11 findings: 1 critical, 4 major, 6 cosmetic).

## Contact form

The form has no backend. On submit it opens the visitor's mail client with a
prefilled message (`mailto:`) built from the field labels, so it works in all
languages. Attachments (photos, drawings) are added in the mail client.
To switch to a real backend (Formspree, Web3Forms, your own endpoint), give
the `<form>` an `action` + `method="post"` and remove the submit handler in
`assets/js/main.js`.

## Design

- Font: Onest only, from Google Fonts, all languages (it has native Cyrillic
  and Latin Extended). 400 for text, 500 for nav and buttons, 600 for uppercase
  labels/tags/dimensions, 700 for headings, 800 available. Onest has no true
  italic, so `em` is set in weight 600 instead. The tokens `--sans-head`,
  `--sans-cond` and `--sans-xcond` all alias `--sans`; they are kept so a
  second face can be reintroduced in one place.
- Palette: paper and ink neutrals plus two pencil colours. Red `--mark`
  (#BC0A00) is used only on findings (numbers, critical tags, markers). Blue
  `--check` (#17336E) is used only on things that were checked or measured
  (dimension lines, checkmarks, timeline progress, focus, form success).
  Everything else is ink. Tokens at the top of `style.css`; the report
  template mirrors them in `report.css`.
- Mark: a red ring with a K, inline in the header and report wordmark.
  Favicon and touch icon: the red-blue pencil illustration (`favicon-16/32/
  192/512.png`, `apple-touch-icon.png`; source in the client's files).
  Brand book: see the published artifact "Kodukontroll Brand Book".

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

### Mailbox

The site sends everything to **info@kodukontroll.ee**, so that mailbox must be
created on Hostinger: hPanel → Emails → kodukontroll.ee → Create email account
(`info`). Hostinger adds the MX/SPF records to its DNS zone automatically, but
that only works once the kodukontroll.ee zone is actually served by Hostinger's
nameservers (see Domains). Until then, mail to info@kodukontroll.ee bounces.

### Domains

- kodukontroll.ee is the primary domain of the website in hPanel; its nameservers
  at Zone.ee are `atlas.dns-parking.com` / `hyperion.dns-parking.com`.
- kodukontroll.com is added as a parked domain in hPanel; `.htaccess` is meant
  to send any host other than kodukontroll.ee to https://kodukontroll.ee with
  the same path. That rule is commented out until .ee resolves — as of
  2026-09-15 the registry still publishes a DNSSEC DS record for
  kodukontroll.ee (from the old Zone.ee keys) while the nameservers are
  Hostinger's, which cannot sign the zone; Hostinger's servers answer REFUSED
  and validating resolvers fail. Fix at Zone.ee: disable DNSSEC / remove the
  DS record, then confirm in hPanel that the zone exists, then uncomment the
  two redirect lines in `.htaccess`.
