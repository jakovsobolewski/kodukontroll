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
import datetime
import html
import json
import pathlib
import re

SITE = "https://kodukontroll.ee"
TODAY = datetime.date.today().isoformat()

PAGES = {
    "index.html": dict(url=SITE + "/", lang="et", og="/assets/img/og-et.png"),
    "en/index.html": dict(url=SITE + "/en/", lang="en", og="/assets/img/og-en.png"),
    "ru/index.html": dict(url=SITE + "/ru/", lang="ru", og="/assets/img/og-ru.png"),
}

# Service pages: one service, its own offers, steps and FAQ; the organisation
# is described in full only on the home pages and referenced here by @id.
SUBPAGES = {
    "korteri-vastuvott/index.html": dict(url=SITE + "/korteri-vastuvott/", lang="et", og="/assets/img/og-et.png",
                                         home="index.html", published="2026-09-27", tiers=["acceptance", "recheck"],
                                         crumb="Korteri vastuvõtt"),
    "en/apartment-acceptance/index.html": dict(url=SITE + "/en/apartment-acceptance/", lang="en", og="/assets/img/og-en.png",
                                               home="en/index.html", published="2026-09-27", tiers=["acceptance", "recheck"],
                                               crumb="Apartment inspection"),
    "ru/priemka-kvartiry/index.html": dict(url=SITE + "/ru/priemka-kvartiry/", lang="ru", og="/assets/img/og-ru.png",
                                           home="ru/index.html", published="2026-09-27", tiers=["acceptance", "recheck"],
                                           crumb="Приёмка квартиры"),
}

# The one thing that cannot be read off the page: which price tier is which.
TIER_IDS = ["express", "standard", "complex", "recheck"]

# Topics the business is an authority on, in the words its audience searches.
# These drive entity association in AI answer engines; keep them to subjects
# the pages genuinely cover.
KNOWS_ABOUT = {
    "et": ["ehitustööde vastuvõtmine", "üleandmis-vastuvõtuakt", "remonditööde kvaliteedikontroll",
           "sisseehitatud mööbli paigalduse kontroll", "köögimööbli paigaldus",
           "kodutehnika paigalduse kontroll", "santehniliste tööde ülevaatus",
           "elektritööde nähtava kvaliteedi kontroll", "puuduste fikseerimine ja aruanne",
           "korduskontroll pärast puuduste kõrvaldamist", "sõltumatu ehituskontroll Tallinnas"],
    "en": ["construction work acceptance", "handover and acceptance act",
           "renovation quality inspection", "built-in furniture installation inspection",
           "kitchen installation inspection", "appliance installation inspection",
           "plumbing installation inspection", "visible electrical installation quality",
           "defect list and inspection report", "re-inspection after remedial work",
           "independent building inspection in Tallinn"],
    "ru": ["приёмка строительных и ремонтных работ", "акт приёмки-передачи",
           "контроль качества ремонта", "проверка установки встроенной мебели",
           "установка кухонной мебели", "проверка подключения бытовой техники",
           "проверка сантехнических работ", "видимое качество электромонтажа",
           "фиксация недостатков и отчёт", "повторная проверка после устранения недостатков",
           "независимый строительный контроль в Таллинне"],
}


def text(fragment):
    """Visible text of an HTML fragment."""
    t = re.sub(r"<[^>]+>", " ", fragment)
    return re.sub(r"\s+", " ", html.unescape(t)).strip()


def meta(src, name, attr="name"):
    m = re.search(r'<meta %s="%s" content="(.*?)">' % (attr, name), src, re.S)
    return html.unescape(m.group(1)) if m else ""


def price_tiers(src, tier_ids=None):
    out = []
    for i, card in enumerate(re.findall(r'<article class="pcard".*?</article>', src, re.S)):
        name = text(re.search(r'<p class="meta">(.*?)</p>', card, re.S).group(1))
        amount = re.search(r'data-count="(\d+)"', card).group(1)
        intro = re.search(r'<p class="pcard__intro">(.*?)</p>', card, re.S)
        if intro:
            items = [text(li) for li in re.findall(r"<li[^>]*>(.*?)</li>", card, re.S)]
            desc = text(intro.group(1)) + " " + " ".join(items)
        else:
            desc = text(re.findall(r"<p[^>]*>(.*?)</p>", card, re.S)[-1])
        ids = tier_ids or TIER_IDS
        out.append((ids[i] if i < len(ids) else "tier%d" % i, name, amount, desc))
    return out


def check_areas(src):
    return [text(t) for t in re.findall(r'<span class="tab__t">(.*?)</span>', src, re.S)]


def steps(src):
    block = re.search(r'<ol class="tl">.*?</ol>', src, re.S).group(0)
    out = []
    for li in re.findall(r'<li class="tl__step".*?</li>', block, re.S):
        name = text(re.search(r"<h3>(.*?)</h3>", li, re.S).group(1))
        body = text(re.search(r"<p>(.*?)</p>", li, re.S).group(1))
        out.append((name, body))
    return out


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
        "knowsAbout": KNOWS_ABOUT[lang],
        "contactPoint": [{
            "@type": "ContactPoint",
            "telephone": "+372 5747 6331",
            "email": "info@kodukontroll.ee",
            "contactType": "customer service",
            "areaServed": "EE",
            "availableLanguage": ["et", "en", "ru"],
        }],
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
            "name": text(re.search(r'<section class="sec[^"]*" id="report">.*?<b>(.*?)</b>', src, re.S).group(1)),
            "encodingFormat": "application/pdf",
        },
    }

    catalog = {
        "@type": "OfferCatalog",
        "@id": url + "#catalog",
        "name": text(re.search(r'<section class="sec[^"]*" id="pricing">.*?<h2>(.*?)</h2>', src, re.S).group(1)),
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
        "datePublished": "2026-09-15",
        "dateModified": TODAY,
        "mainEntity": {"@id": SITE + "/#service"},
        "hasPart": [{"@id": url + "#faq"}, {"@id": url + "#process"}],
    }

    howto = {
        "@type": "HowTo",
        "@id": url + "#process",
        "name": text(re.search(r'id="process">.*?<h2>(.*?)</h2>', src, re.S).group(1)),
        "inLanguage": lang,
        "isPartOf": {"@id": url + "#webpage"},
        "estimatedCost": {"@type": "MonetaryAmount", "currency": "EUR",
                          "minValue": min(int(a) for _, _, a, _ in tiers)},
        "step": [{"@type": "HowToStep", "position": i + 1, "name": n, "text": t,
                  "url": url + "#process"} for i, (n, t) in enumerate(steps(src))],
    }

    faqpage = {
        "@type": "FAQPage", "@id": url + "#faq", "inLanguage": lang,
        "isPartOf": {"@id": url + "#webpage"},
        "mainEntity": [{"@type": "Question", "name": q,
                        "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in qa],
    }

    return {"@context": "https://schema.org",
            "@graph": [org, website, webpage, service, catalog, howto, faqpage]}


def subgraph(path, page):
    """A service page: WebPage + breadcrumb, the Service with its offers, the HowTo and the FAQ."""
    src = pathlib.Path(path).read_text(encoding="utf-8")
    url, lang = page["url"], page["lang"]
    title = html.unescape(re.search(r"<title>(.*?)</title>", src, re.S).group(1))
    desc = meta(src, "description")
    h1 = text(re.search(r"<h1[^>]*>(.*?)</h1>", src, re.S).group(1))
    tiers = price_tiers(src, page["tiers"])
    areas = check_areas(src)
    qa = faq(src)
    area_served = [
        {"@type": "City", "name": "Tallinn"},
        {"@type": "AdministrativeArea", "name": "Harju maakond"},
        {"@type": "Country", "name": "Estonia"},
    ]
    home_url = PAGES[page["home"]]["url"]
    home_name = text(re.search(r'<span class="brand__name">(.*?)</span>', src, re.S).group(1))

    webpage = {
        "@type": "WebPage", "@id": url + "#webpage", "url": url, "name": title,
        "description": desc, "inLanguage": lang,
        "isPartOf": {"@id": SITE + "/#website"},
        "about": {"@id": SITE + "/#organization"},
        "primaryImageOfPage": {"@type": "ImageObject", "url": SITE + page["og"],
                               "width": 1200, "height": 630},
        "datePublished": page["published"],
        "dateModified": TODAY,
        "breadcrumb": {"@id": url + "#breadcrumb"},
        "mainEntity": {"@id": url + "#service"},
        "hasPart": [{"@id": url + "#faq"}, {"@id": url + "#process"}],
    }
    crumbs = {
        "@type": "BreadcrumbList", "@id": url + "#breadcrumb",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": home_name, "item": home_url},
            {"@type": "ListItem", "position": 2, "name": page["crumb"], "item": url},
        ],
    }
    service = {
        "@type": "Service",
        "@id": url + "#service",
        "name": title.split("—")[0].strip(),
        "serviceType": title.split("—")[0].strip(),
        "description": h1 + " " + desc,
        "provider": {"@id": SITE + "/#organization"},
        "areaServed": area_served,
        "hasOfferCatalog": {"@id": url + "#catalog"},
        "serviceOutput": {"@type": "CreativeWork", "name": text(re.search(r'<div class="rcard__head"><span class="meta"><b>(.*?)</b>', src, re.S).group(1)),
                          "encodingFormat": "application/pdf"},
    }
    catalog = {
        "@type": "OfferCatalog",
        "@id": url + "#catalog",
        "name": text(re.search(r'<section class="sec[^"]*" id="pricing">.*?<h2>(.*?)</h2>', src, re.S).group(1)),
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
            "areaServed": area_served,
            "itemOffered": {"@type": "Service", "name": name,
                            "provider": {"@id": SITE + "/#organization"},
                            "serviceType": ", ".join(areas)},
        } for tid, name, amount, d in tiers],
    }
    howto = {
        "@type": "HowTo",
        "@id": url + "#process",
        "name": text(re.search(r'id="process">.*?<h2>(.*?)</h2>', src, re.S).group(1)),
        "inLanguage": lang,
        "isPartOf": {"@id": url + "#webpage"},
        "estimatedCost": {"@type": "MonetaryAmount", "currency": "EUR",
                          "minValue": min(int(a) for _, _, a, _ in tiers)},
        "step": [{"@type": "HowToStep", "position": i + 1, "name": n, "text": t,
                  "url": url + "#process"} for i, (n, t) in enumerate(steps(src))],
    }
    faqpage = {
        "@type": "FAQPage", "@id": url + "#faq", "inLanguage": lang,
        "isPartOf": {"@id": url + "#webpage"},
        "mainEntity": [{"@type": "Question", "name": q,
                        "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in qa],
    }
    return {"@context": "https://schema.org",
            "@graph": [webpage, crumbs, service, catalog, howto, faqpage]}


START, END = "<!-- jsonld:start -->", "<!-- jsonld:end -->"

for path, page in list(PAGES.items()) + list(SUBPAGES.items()):
    p = pathlib.Path(path)
    src = p.read_text(encoding="utf-8")
    build = subgraph if path in SUBPAGES else graph
    block = '%s\n<script type="application/ld+json">%s</script>\n%s' % (
        START, json.dumps(build(path, page), ensure_ascii=False, separators=(",", ":")), END)
    if START in src:
        src = re.sub(re.escape(START) + r".*?" + re.escape(END), lambda _: block, src, count=1, flags=re.S)
    else:
        src = re.sub(r'<script type="application/ld\+json">.*?</script>', lambda _: block, src, count=1, flags=re.S)
    p.write_text(src, encoding="utf-8")
    g = build(path, page)
    by_type = {node["@type"] if isinstance(node["@type"], str) else "Org": node for node in g["@graph"]}
    print("%-36s offers=%d steps=%d faq=%d" % (
        path, len(by_type["OfferCatalog"]["itemListElement"]),
        len(by_type["HowTo"]["step"]), len(by_type["FAQPage"]["mainEntity"])))
