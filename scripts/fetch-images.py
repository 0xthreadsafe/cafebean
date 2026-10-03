#!/usr/bin/env python3
"""Fetch public-domain / CC0 coffee photos from Wikimedia Commons.

Only PD and CC0 files are kept, so the demo needs no attribution anywhere.
Writes JPEG/PNG originals to a staging dir; seed-products.sh converts to WebP.
Re-running skips files that already exist.
"""
import json, os, sys, urllib.parse, urllib.request

UA = "cafebean-demo/0.1 (local portfolio demo; contact: admin@cafebean.test)"
API = "https://commons.wikimedia.org/w/api.php"
CATEGORIES = [
    "Category:Coffee beans", "Category:Roasted coffee beans", "Category:Coffee",
    "Category:Ground coffee", "Category:Espresso", "Category:Moka pots",
    "Category:Coffee grinders", "Category:Turkish coffee", "Category:Coffee filters",
    "Category:Coffee cups", "Category:Cold brew coffee", "Category:Coffee roasting",
]
FREE = ("public domain", "cc0", "pd-")
# Commons categories contain engravings, museum shots and portraits alongside
# product photography, so the title has to look like a coffee product too.
WANTED = ("coffee", "espresso", "bean", "roast", "moka", "grinder", "cup", "brew", "filter")
UNWANTED = ("petition", "broad-side", "broadside", "answer to", "museum", "engraving",
            "map", "poster", "advertisement", "portrait", "unsplash)", "logo", "sign",
            "plantation", "harvest", "1600", "1700", "1800", "19th",
            "mill", "patent", "drawing", "met sf", "nga ", "mus", "antique",
            "vintage", "19", "history", "chip", "clock")


def api(params):
    url = API + "?" + urllib.parse.urlencode({**params, "format": "json"})
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.load(r)


def is_relevant(title):
    low = title.lower()
    return any(w in low for w in WANTED) and not any(w in low for w in UNWANTED)


def is_free(meta):
    lic = meta.get("LicenseShortName", {}).get("value", "").lower()
    return any(tag in lic for tag in FREE)


def main(outdir, want):
    os.makedirs(outdir, exist_ok=True)
    seen, kept = set(), []
    for cat in CATEGORIES:
        if len(kept) >= want:
            break
        try:
            data = api({
                "action": "query", "generator": "categorymembers",
                "gcmtitle": cat, "gcmtype": "file", "gcmlimit": "40",
                "prop": "imageinfo", "iiprop": "url|extmetadata|mime",
                "iiurlwidth": "900",
            })
        except Exception as exc:
            print(f"  ! {cat}: {exc}", file=sys.stderr)
            continue
        for page in data.get("query", {}).get("pages", {}).values():
            if len(kept) >= want:
                break
            info = page["imageinfo"][0]
            meta = info.get("extmetadata", {})
            if not is_free(meta) or info.get("mime") not in ("image/jpeg", "image/png"):
                continue
            if not is_relevant(page["title"]):
                continue
            url = info.get("thumburl") or info["url"]
            if url in seen:
                continue
            seen.add(url)
            # The thumb URL carries query parameters, so derive the extension
            # from the path only.
            ext = os.path.splitext(urllib.parse.urlparse(url).path)[1].lower() or ".jpg"
            name = f"{len(kept):02d}{ext}"
            path = os.path.join(outdir, name)
            if not os.path.exists(path):
                try:
                    req = urllib.request.Request(url, headers={"User-Agent": UA})
                    with urllib.request.urlopen(req, timeout=60) as r, open(path, "wb") as f:
                        f.write(r.read())
                except Exception as exc:
                    print(f"  ! {url}: {exc}", file=sys.stderr)
                    continue
            kept.append((name, page["title"], meta.get("LicenseShortName", {}).get("value", "")))
            print(f"  + {name}  {meta.get('LicenseShortName',{}).get('value','')}  {page['title'][:50]}")
    if len(kept) < want:
        print(f"Only found {len(kept)} of {want} free files.", file=sys.stderr)
    return 0 if kept else 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1], int(sys.argv[2])))
