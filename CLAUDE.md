# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# WooCommerce Demo Store (Persian / RTL)

## Status
The stack is built and verified. `docker compose up -d && ./scripts/setup.sh && ./scripts/seed-products.sh`
produces the full site from empty volumes with no manual steps (~6m30s, mostly plugin downloads).

Verified on a from-scratch rebuild: 12 products, Persian/RTL throughout, COD order placed end to end,
no horizontal scroll at 360px, `debug.log` empty with `WP_DEBUG` on, and Lighthouse mobile at
Performance 89-93 / Accessibility 92-93 / SEO 100 / Best Practices 100.

Two deviations from the plan below, both deliberate:
- **Elementor is not installed.** The pages are core block markup written by `scripts/setup-pages.php`,
  so layouts are reproducible from the script with no builder state in the database.
- **Sitemap and meta descriptions do not come from Rank Math.** Rank Math only registers its sitemap
  routes and emits front-end meta after its *interactive* setup wizard, which a script cannot drive.
  The sitemap is WordPress core's `/wp-sitemap.xml` and the description tag is emitted by the
  mu-plugin. Rank Math still provides titles and breadcrumbs.

Remaining: the screen-recorded admin walkthrough, and deployment to the demo subdomain.

## Purpose
A portfolio demo of a small Persian online store, built to show Karlancer clients what I deliver
for "طراحی و راه‌اندازی سایت فروشگاهی" projects. It must look production-quality, load fast,
and demonstrate: RTL Persian design, WooCommerce checkout with an Iranian gateway (sandbox),
basic SEO, and code-level customization (child theme + hooks), not just page-builder work.

Demo niche: a small specialty coffee shop ("کافه‌بین" — fictional brand, no real company names or logos).

## Stack
- WordPress (latest stable) + WooCommerce, PHP 8.2, MariaDB 10.11
- Local dev: Docker Compose (`wordpress`, `db`, `wpcli` services)
- Parent theme: **Astra (free)** — child theme `cafebean-child` holds all custom code
- Page builder: **Elementor (free)** for page layouts only; logic lives in PHP, never in builder widgets
- Plugins (free, from wordpress.org only):
  - `woocommerce`
  - `wp-parsidate` (Jalali dates)
  - Zarinpal WooCommerce gateway (sandbox mode)
  - `seo-by-rank-math`
  - `wp-super-cache` — file-based page cache; works on both local Docker/Apache and cPanel/DirectAdmin
    shared hosting. Do NOT swap in `litespeed-cache`: its page cache is inert unless the server runs
    LiteSpeed, so local and production would diverge.
  - `limit-login-attempts-reloaded`
- Font: **Vazirmatn**, self-hosted in the child theme (do NOT load Google Fonts — unreliable from Iran)

## Hard rules
- NEVER install nulled/cracked themes or plugins. Only wordpress.org or officially licensed sources.
- NEVER edit the parent theme or plugin files. All changes go in the child theme or `mu-plugins/`.
- Keep the plugin count minimal; justify every new plugin in the commit message.
- No external CDNs or third-party scripts that may be blocked in Iran (Google Fonts, reCAPTCHA, etc.).
- No real personal data: placeholder phone/email/address only, clearly fictional.
- Site language `fa_IR`, direction RTL everywhere, including admin, emails, and invoices.
- Currency: **Iranian Toman (IRT)**, thousand separator `,`, no decimals.

## Directory layout
```
.
├── CLAUDE.md
├── docker-compose.yml
├── .env.example              # DB creds, WP_URL, ports
├── scripts/
│   ├── setup.sh              # full bootstrap via wp-cli (idempotent)
│   ├── seed-products.sh      # imports sample products from data/products.csv
│   └── export.sh             # db export + wp-content archive for deployment
├── data/
│   ├── products.csv          # 12–15 sample products, Persian names/descriptions
│   └── images/               # royalty-free product images (compressed WebP)
└── wp-content/
    ├── themes/cafebean-child/
    │   ├── style.css          # child theme header + CSS variables
    │   ├── functions.php      # only require_once of files in inc/
    │   ├── inc/
    │   │   ├── setup.php      # theme supports, enqueue, fonts
    │   │   ├── woocommerce.php# WC hooks/filters (checkout fields, labels, badges)
    │   │   └── performance.php# dequeue unused assets, disable emojis/embeds
    │   ├── assets/fonts/      # Vazirmatn woff2
    │   ├── assets/css/
    │   └── woocommerce/       # template overrides (only when hooks can't do it)
    └── mu-plugins/
        └── cafebean-core.php  # site-wide logic that must survive a theme switch
```

## Pages (2–5 page scope, like the client brief)
1. **خانه** — hero, featured products, categories, trust badges (ارسال سریع، پرداخت امن، ضمانت بازگشت)
2. **فروشگاه** — WooCommerce shop archive with category filter
3. **صفحه محصول** — gallery, price, variations (e.g. وزن: ۲۵۰ / ۵۰۰ / ۱۰۰۰ گرم), related products
4. **درباره ما**
5. **تماس با ما** — simple form (no reCAPTCHA; use honeypot)
Plus WooCommerce defaults: سبد خرید، تسویه حساب، حساب کاربری.

## Customizations to demonstrate (in code)
- Iranian checkout fields: remove company/address_2/country, add استان dropdown, require کد پستی (10 digits) and موبایل (09xxxxxxxxx) with server-side validation.
- Convert Persian/Arabic digits to English digits before validating/saving phone and postal code.
- Show prices with "تومان" label and Persian digits on the frontend.
- "ارسال رایگان" progress bar in cart (free shipping above a threshold).
- Custom product badge (e.g. "تازه برشته") via a product meta field + hook.
- Shipping methods: پست پیشتاز (flat rate) and پیک تهران (local pickup/flat, Tehran only).

## Coding conventions
- Prefix every function, hook, option, and CSS class with `cafebean_` / `.cb-`.
- Use `add_action` / `add_filter`; prefer hooks over template overrides.
- Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`); sanitize all input; use nonces on forms.
- Translatable strings via `__()` / `esc_html__()` with text domain `cafebean`.
- CSS: logical properties (`margin-inline-start`, etc.) so RTL works without overrides.
- PHP follows WordPress Coding Standards; run `phpcs --standard=WordPress` if available.

## Performance & SEO targets
- Lighthouse mobile: Performance ≥ 85, SEO ≥ 95, Accessibility ≥ 90.
- All images WebP, lazy-loaded, with Persian `alt` text.
- Rank Math: Persian permalinks for products (`/product/قهوه-اسپرسو-...`), sitemap enabled, Product schema.
- Disable emojis, oEmbed, unused Elementor/WooCommerce assets on non-shop pages.

## Commands
```bash
cp .env.example .env
docker compose up -d
./scripts/setup.sh          # installs WP (fa_IR), plugins, theme, pages, menus, WC settings
./scripts/setup.sh --force-pages   # same, but also rewrites page content edited in wp-admin
./scripts/seed-products.sh  # sample products + images
docker compose run --rm wpcli wp <command>   # any wp-cli command
./scripts/export.sh         # produces dist/db.sql + dist/wp-content.tar.gz
./scripts/export.sh https://demo.example   # same, with URLs rewritten to the target
```
Local URL: http://localhost:8080 — admin credentials come from `.env` (never commit `.env`).

## Deployment (demo subdomain)
- Target: `demo.<my-domain>` on Iranian shared hosting (cPanel/DirectAdmin), SSL via Let's Encrypt.
- Run `export.sh`, upload, import DB, then `wp search-replace 'http://localhost:8080' 'https://demo.<my-domain>'`.
- Zarinpal stays in **sandbox** mode on the demo; add a visible banner: "این سایت نمونه‌کار است و سفارش‌ها واقعی نیستند".
- Set `noindex` on the demo so it doesn't compete with real client sites.

## Definition of done
- [ ] Fresh `setup.sh` + `seed-products.sh` produces the full site with no manual steps
- [ ] Full purchase flow works end-to-end with Zarinpal sandbox
- [ ] Mobile layout checked on 360px width; no horizontal scroll
- [ ] Lighthouse targets met
- [ ] No PHP warnings with `WP_DEBUG` on
- [ ] Short screen-recorded walkthrough of the admin panel (for client training demos)
