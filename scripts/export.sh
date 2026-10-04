#!/usr/bin/env bash
#
# Produce a deployable bundle: dist/db.sql and dist/wp-content.tar.gz.
# Usage: ./scripts/export.sh [target-url]
#
# Passing a target URL rewrites the exported database to it, so the dump can be
# imported on the host without a second search-replace step. Without it the dump
# keeps the local URL and the rewrite is left to you.
#
set -euo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.."

if [[ ! -f .env ]]; then
	echo "Missing .env — copy .env.example and edit it first." >&2
	exit 1
fi
set -a; . ./.env; set +a

TARGET_URL="${1:-}"

wp() { docker compose run --rm -T wpcli wp "$@"; }
# The cli image's MariaDB client requires TLS against our 10.11 server.
wp_db() { wp "$@" --skip-ssl; }

if ! wp core is-installed >/dev/null 2>&1; then
	echo "WordPress is not installed — run ./scripts/setup.sh first." >&2
	exit 1
fi

mkdir -p dist

echo "==> Checking the demo is safe to publish"
SANDBOX="$( wp eval 'echo cafebean_is_sandbox() ? "yes" : "no";' 2>/dev/null | tr -dc 'a-z' )"
if [[ "${SANDBOX}" != "yes" ]]; then
	echo "REFUSING TO EXPORT: the Zarinpal gateway is not in sandbox mode." >&2
	echo "This bundle is for a public demo and must never be able to take a real" >&2
	echo "payment. Set sandbox back on, or export by hand if this is intentional." >&2
	exit 1
fi
echo "    Zarinpal sandbox: on, demo banner will be shown"

# search-replace talks to the database through PHP, not the mariadb client, so
# it takes no --skip-ssl; passing one is rejected outright.
restore_local_urls() {
	echo "==> Restoring local URLs"
	wp search-replace "${TARGET_URL}" "${WP_URL}" --all-tables --precise --quiet >/dev/null 2>&1 || true
}

if [[ -n "${TARGET_URL}" ]]; then
	echo "==> Rewriting URLs to ${TARGET_URL}"
	# The rewrite must be undone even if the export dies midway, or the local
	# site is left pointing at the demo domain and stops loading.
	trap restore_local_urls EXIT
	# --all-tables so WooCommerce's HPOS order tables are rewritten too; they are
	# not standard WP tables and a default run would skip them.
	wp search-replace "${WP_URL}" "${TARGET_URL}" --all-tables --precise --report-changed-only
fi

echo "==> Exporting the database"
wp_db db export /dist/db.sql --add-drop-table

if [[ -n "${TARGET_URL}" ]]; then
	restore_local_urls
	trap - EXIT
fi

echo "==> Archiving wp-content"
# Caches and logs are rebuilt on the target and only bloat the archive.
# The child theme and mu-plugins are bind-mounted from the host, so tar sees
# their timestamps shift mid-read and exits 1 with "file changed as we read it".
# The archive itself is fine, so exit 1 is tolerated while any higher code - a
# real write or permission failure - still aborts the export.
set +e
docker compose run --rm -T \
	--volume "$( pwd )/dist:/out" \
	wpcli tar \
		--warning=no-file-changed \
		--exclude='./cache' \
		--exclude='./*.log' \
		--exclude='./upgrade' \
		--exclude='./uploads/wc-logs' \
		-czf /out/wp-content.tar.gz -C /var/www/html/wp-content .
TAR_STATUS=$?
set -e
if (( TAR_STATUS > 1 )); then
	echo "tar failed with status ${TAR_STATUS}" >&2
	exit "${TAR_STATUS}"
fi

echo
echo "Bundle ready:"
ls -lh dist/db.sql dist/wp-content.tar.gz | awk '{ printf "  %-28s %s\n", $9, $5 }'
cat <<'NOTES'

To deploy:
  1. Upload wp-content.tar.gz and extract it over wp-content/ on the host.
  2. Import db.sql into the host database.
  3. If you did not pass a target URL above, run the rewrite on the host:
       wp search-replace 'http://localhost:8080' 'https://demo.example' --all-tables
  4. Set the demo to noindex so it does not compete with real client sites:
       wp option update blog_public 0
     Note this also disables /wp-sitemap.xml, which is expected for a demo.
  5. Confirm the Zarinpal gateway is still in sandbox on the host.
NOTES
