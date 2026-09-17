#!/usr/bin/env python3
"""Rebuild the JSON-LD block of every page from the page's own copy.

Run from the repository root:  python3 assets/seo/build-jsonld.py

Each HTML file carries the markers

    <!-- jsonld:start -->  ...  <!-- jsonld:end -->

in <head>. This script reads the visible copy of the page (pricing cards, the
six check areas, the FAQ accordion) and writes a schema.org @graph describing
exactly that. Re-run it after editing prices, service names or FAQ entries,
otherwise the structured data drifts away from what a visitor sees - which is
what Google and the AI crawlers penalise.
"""
import html
import json
import pathlib
import re

SITE = "https://kodukontroll.ee"

PAGES = {
    "index.html": dict(url=SITE + "/", lang="et", og="/assets/img/og-et.png"),
    "en/index.html": dict(url=SITE + "/en/", lang="en", og="/assets/img/og-en.png"),
    "ru/index.html": dict(url=SITE + "/ru/", lang="ru", og="/assets/img/og-ru.png"),
}

# The one thing that cannot be read off the page: which price tier is which.
TIER_IDS = ["express", "standard", "complex", "recheck"]


def text(fragment):
    """Visible text of an HTML fragment."""
    t = re.sub(r"<[^>]+>", " ", fragment)
    return re.sub(r"\s+", " ", html.unescape(t)).strip()


def meta(src, name, attr="name"):
    m = re.search(r'<meta %s="%s" content="(.*?)">' % (attr, name), src, re.S)
    return html.unescape(m.group(1)) if m else ""


def price_tiers(src):
    out = []
    for i, card in enumerate(re.findall(r'<article class="pcard".*?</article>', src, re.S)):
        name = text(re.search(r'<p class="meta">(.*?)</p>', card, re.S).group(1))
        amount = re.search(r'data-count="(\d+)"', card).group(1)
        desc = text(re.findall(r"<p[^>]*>(.*?)</p>", card, re.S)[-1])
        out.append((TIER_IDS[i] if i < len(TIER_IDS) else "tier%d" % i, name, amount, desc))
    return out


def check_areas(src):
    return [text(t) for t in re.findall(r'<span class="tab__t">(.*?)</span>', src, re.S)]


def faq(src):
    out = []
    for item in re.findall(r'<details class="faq__item".*?</details>', src, re.S):
        q = text(re.search(r"<summary><span>(.*?)</span>", item, re.S).group(1))
        a = text(re.search(r'<div class="faq__body"><div>(.*?)</div></div>', item, re.S).group(1))
        out.append((q, a))
    return out


def graph(path, page):
    src = pathlib.Path(path).read_text(encoding="utf-8")
    url, lang = page["url"], page["lang"]
    title = html.unescape(re.search(r"<title>(.*?)</title>", src, re.S).group(1))
    desc = meta(src, "description")
    tiers = price_tiers(src)
    areas = check_areas(src)
    qa = faq(src)

    org = {
        "@type": ["ProfessionalService", "HomeAndConstructionBusiness"],
        "@id": SITE + "/#organization",
        "name": "Kodukontroll",
        "legalName": "Little Kris OÜ",
        "url": SITE + "/",
        "description": desc,
        "slogan": text(re.search(r'<span class="brand__desc">(.*?)</span>', src, re.S).group(1)),
        "email": "info@kodukontroll.ee",
        "telephone": "+372 5747 6331",
        "logo": {"@type": "ImageObject", "@id": SITE + "/#logo",
                 "url": SITE + "/favicon-512.png", "width": 512, "height": 512,
                 "caption": "Kodukontroll"},
        "image": {"@id": SITE + "/#logo"},
        "address": {"@type": "PostalAddress", "addressLocality": "Tallinn",
                    "addressRegion": "Harju maakond", "addressCountry": "EE"},
        "areaServed": [
            {"@type": "City", "name": "Tallinn"},
            {"@type": "AdministrativeArea", "name": "Harju maakond"},
            {"@type": "Country", "name": "Estonia"},
        ],
        "knowsLanguage": [
            {"@type": "Language", "name": "Estonian", "alternateName": "et"},
            {"@type": "Language", "name": "English", "alternateName": "en"},
            {"@type": "Language", "name": "Russian", "alternateName": "ru"},
        ],
        "openingHoursSpecification": [{
            "@type": "OpeningHoursSpecification",
            "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
            "opens": "09:00", "closes": "18:00",
        }],
        "currenciesAccepted": "EUR",
        "priceRange": "99-499 EUR",
        "sameAs": ["https://wa.me/37257476331"],
        "hasOfferCatalog": {"@id": url + "#catalog"},
    }

    service = {
        "@type": "Service",
        "@id": SITE + "/#service",
        "serviceType": title.split("—")[0].strip(),
        "provider": {"@id": SITE + "/#organization"},
        "areaServed": org["areaServed"],
        "availableLanguage": org["knowsLanguage"],
        "hasOfferCatalog": {"@id": url + "#catalog"},
        "serviceOutput": {
            "@type": "CreativeWork",
            "name": text(re.search(r'<section class="sec" id="report">.*?<b>(.*?)</b>', src, re.S).group(1)),
            "encodingFormat": "application/pdf",
        },
    }

    catalog = {
        "@type": "OfferCatalog",
        "@id": url + "#catalog",
        "name": text(re.search(r'<section class="sec" id="pricing">.*?<h2>(.*?)</h2>', src, re.S).group(1)),
        "inLanguage": lang,
        "itemListElement": [{
            "@type": "Offer",
            "@id": "%s#offer-%s" % (url, tid),
            "name": name,
            "description": d,
            "priceCurrency": "EUR",
            "priceSpecification": {"@type": "PriceSpecification",
                                   "minPrice": int(amount), "priceCurrency": "EUR"},
            "availability": "https://schema.org/InStock",
            "areaServed": org["areaServed"],
            "itemOffered": {"@type": "Service", "name": name,
                            "provider": {"@id": SITE + "/#organization"},
                            "serviceType": ", ".join(areas)},
        } for tid, name, amount, d in tiers],
    }

    website = {
        "@type": "WebSite", "@id": SITE + "/#website", "url": SITE + "/",
        "name": "Kodukontroll", "publisher": {"@id": SITE + "/#organization"},
        "inLanguage": ["et", "en", "ru"],
    }

    webpage = {
        "@type": "WebPage", "@id": url + "#webpage", "url": url, "name": title,
        "description": desc, "inLanguage": lang,
        "isPartOf": {"@id": SITE + "/#website"},
        "about": {"@id": SITE + "/#organization"},
        "primaryImageOfPage": {"@type": "ImageObject", "url": SITE + page["og"],
                               "width": 1200, "height": 630},
    }

    faqpage = {
        "@type": "FAQPage", "@id": url + "#faq", "inLanguage": lang,
        "isPartOf": {"@id": url + "#webpage"},
        "mainEntity": [{"@type": "Question", "name": q,
                        "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in qa],
    }

    return {"@context": "https://schema.org",
            "@graph": [org, website, webpage, service, catalog, faqpage]}


START, END = "<!-- jsonld:start -->", "<!-- jsonld:end -->"

for path, page in PAGES.items():
    p = pathlib.Path(path)
    src = p.read_text(encoding="utf-8")
    block = '%s\n<script type="application/ld+json">%s</script>\n%s' % (
        START, json.dumps(graph(path, page), ensure_ascii=False, separators=(",", ":")), END)
    if START in src:
        src = re.sub(re.escape(START) + r".*?" + re.escape(END), lambda _: block, src, count=1, flags=re.S)
    else:
        src = re.sub(r'<script type="application/ld\+json">.*?</script>', lambda _: block, src, count=1, flags=re.S)
    p.write_text(src, encoding="utf-8")
    g = graph(path, page)
    print("%-16s offers=%d faq=%d" % (path, len(g["@graph"][4]["itemListElement"]), len(g["@graph"][5]["mainEntity"])))
