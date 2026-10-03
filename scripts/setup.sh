#!/usr/bin/env bash
#
# Bootstrap the CafeBean demo store. Idempotent: re-running changes nothing.
# Usage: ./scripts/setup.sh
#
set -euo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.."

if [[ ! -f .env ]]; then
	echo "Missing .env — copy .env.example and edit it first." >&2
	exit 1
fi
set -a; . ./.env; set +a

wp() { docker compose run --rm -T wpcli wp "$@"; }
# The cli image's MariaDB 11.x client negotiates TLS by default and our local
# 10.11 server has no certificates, so client-backed commands need --skip-ssl.
wp_db() { wp "$@" --skip-ssl; }
step() { printf '\n==> %s\n' "$1"; }

step "Waiting for the database"
docker compose up -d db wordpress >/dev/null
# Bounded: a wrong password looks exactly like a slow boot, so never wait forever.
for attempt in $( seq 1 30 ); do
	if wp_db db check >/dev/null 2>&1; then
		break
	fi
	if [[ "${attempt}" -eq 30 ]]; then
		echo "Database unreachable after 60s. If you changed DB_USER/DB_PASSWORD in .env" >&2
		echo "after the first run, the old credentials are baked into the db volume:" >&2
		echo "  docker compose down -v && ./scripts/setup.sh" >&2
		exit 1
	fi
	sleep 2
done

step "Installing WordPress core"
if wp core is-installed >/dev/null 2>&1; then
	echo "already installed"
else
	wp core install \
		--url="${WP_URL}" \
		--title="${WP_TITLE}" \
		--admin_user="${WP_ADMIN_USER}" \
		--admin_password="${WP_ADMIN_PASSWORD}" \
		--admin_email="${WP_ADMIN_EMAIL}" \
		--skip-email
fi

step "Switching the site to ${WP_LOCALE}"
wp language core install "${WP_LOCALE}" --activate
wp site switch-language "${WP_LOCALE}"
# RTL in the admin as well as the front end.
wp option update WPLANG "${WP_LOCALE}"

step "Installing the parent theme and activating the child theme"
wp theme is-installed astra || wp theme install astra
wp theme activate cafebean-child

step "Installing plugins"
# Every plugin here is justified in CLAUDE.md; do not add to this list casually.
PLUGINS=(
	woocommerce
	wp-parsidate
	zarinpal-woocommerce-payment-gateway
	seo-by-rank-math
	wp-super-cache
	limit-login-attempts-reloaded
)
for plugin in "${PLUGINS[@]}"; do
	wp plugin is-installed "${plugin}" || wp plugin install "${plugin}"
	wp plugin is-active "${plugin}" || wp plugin activate "${plugin}"
done
wp language plugin install --all "${WP_LOCALE}" || true

step "Configuring WooCommerce"
wp option update woocommerce_currency IRT
wp option update woocommerce_price_thousand_sep ','
wp option update woocommerce_price_decimal_sep '.'
wp option update woocommerce_price_num_decimals 0
# Persian reads the unit after the amount: «۸۵۰,۰۰۰ تومان», not «تومان ۸۵۰,۰۰۰».
wp option update woocommerce_currency_pos right_space
wp option update woocommerce_default_country 'IR:THR'
wp option update woocommerce_allowed_countries 'specific'
wp option update woocommerce_specific_allowed_countries '["IR"]' --format=json
wp option update woocommerce_ship_to_countries 'specific'
wp option update woocommerce_specific_ship_to_countries '["IR"]' --format=json
wp option update woocommerce_enable_guest_checkout 'yes'
wp option update woocommerce_calc_taxes 'no'
# New WooCommerce installs default to "coming soon" mode, which replaces the
# whole storefront with a placeholder page. Without this the shop looks empty.
wp option update woocommerce_coming_soon 'no'
wp option update woocommerce_store_pages_only 'no'

# Skip the WooCommerce onboarding wizard so a fresh run lands on a usable admin.
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
wp option patch update _transient_woocommerce_onboarding_profile skipped true 2>/dev/null || true

step "Renaming the WooCommerce pages to Persian"
# WooCommerce creates these in English before the locale switch takes effect.
rename_page() {
	local id
	id="$( wp option get "$1" 2>/dev/null || true )"
	[[ -n "${id}" && "${id}" != "0" ]] || return 0
	wp post update "${id}" --post_title="$2" >/dev/null
}
rename_page woocommerce_shop_page_id "فروشگاه"
rename_page woocommerce_cart_page_id "سبد خرید"
rename_page woocommerce_checkout_page_id "تسویه حساب"
rename_page woocommerce_myaccount_page_id "حساب کاربری"
rename_page woocommerce_terms_page_id "قوانین و مقررات"

step "Removing the default WordPress sample content"
for sample in sample-page hello-world refund_returns privacy-policy; do
	ID="$( wp post list --post_type=page,post --name="${sample}" --field=ID --post_status=any )"
	[[ -n "${ID}" ]] && wp post delete "${ID}" --force >/dev/null || true
done

step "Creating pages"
# WooCommerce creates cart/checkout/account itself; these are ours.
declare -A PAGES=(
	[home]="خانه"
	[about]="درباره ما"
	[contact]="تماس با ما"
)
for slug in "${!PAGES[@]}"; do
	if [[ -z "$( wp post list --post_type=page --name="${slug}" --field=ID )" ]]; then
		wp post create --post_type=page --post_status=publish \
			--post_name="${slug}" --post_title="${PAGES[$slug]}"
	fi
done

HOME_ID="$( wp post list --post_type=page --name=home --field=ID )"
wp option update show_on_front page
wp option update page_on_front "${HOME_ID}"

step "Setting permalinks"
# Persian product slugs, per the SEO targets.
wp rewrite structure '/%postname%/'
wp rewrite flush

step "Building the primary menu"
# Match on the menu NAME: wp-cli derives a Persian slug from it, so a slug guard
# would never match and every run would create another menu.
MENU_NAME="منوی اصلی"
# wp-cli quotes non-ASCII names in CSV, so strip the quotes before comparing.
MENU_ID="$( wp menu list --fields=term_id,name --format=csv \
	| awk -F, -v n="${MENU_NAME}" '{ gsub(/\r/,""); gsub(/^"|"$/,"",$2) } $2==n { print $1 }' \
	| head -1 )"
if [[ -z "${MENU_ID}" ]]; then
	MENU_ID="$( wp menu create "${MENU_NAME}" --porcelain )"
fi

menu_has_post() {
	wp menu item list "${MENU_ID}" --fields=object_id --format=csv 2>/dev/null \
		| tail -n +2 | tr -d '"\r' | grep -qx "$1"
}
menu_add_post() {
	[[ -n "$1" && "$1" != "0" ]] || return 0
	menu_has_post "$1" || wp menu item add-post "${MENU_ID}" "$1"
}

menu_add_post "${HOME_ID}"
menu_add_post "$( wp option get woocommerce_shop_page_id 2>/dev/null || true )"
for slug in about contact; do
	menu_add_post "$( wp post list --post_type=page --name="${slug}" --field=ID )"
done
wp menu location assign "${MENU_ID}" primary 2>/dev/null || true

step "Discouraging indexing (this is a demo site)"
wp option update blog_public 0

printf '\nDone. %s — admin: %s\n' "${WP_URL}/wp-admin" "${WP_ADMIN_USER}"
