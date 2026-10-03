#!/usr/bin/env bash
#
# Import the sample catalogue and its images. Idempotent: products are matched
# by SKU, so re-running updates them in place.
# Usage: ./scripts/seed-products.sh
#
set -euo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.."

if [[ ! -f .env ]]; then
	echo "Missing .env — copy .env.example and edit it first." >&2
	exit 1
fi
set -a; . ./.env; set +a

if [[ ! -f data/products.csv ]]; then
	echo "Missing data/products.csv" >&2
	exit 1
fi

wp() { docker compose run --rm -T wpcli wp "$@"; }

if ! wp core is-installed >/dev/null 2>&1; then
	echo "WordPress is not installed yet — run ./scripts/setup.sh first." >&2
	exit 1
fi

# data/ and scripts/ are both mounted into the cli container, so the PHP file
# reads the CSV straight off the host copy.
wp eval-file /scripts/seed-products.php
