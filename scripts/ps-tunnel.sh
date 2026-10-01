#!/usr/bin/env bash
# PrestaShop redirects every host but the main shop URL, so the shop moves to the tunnel host and back instead of answering on both.
set -euo pipefail

cd /var/www/html

if [ "${1:-}" = '--stop' ]; then
	php modules/voucherly/scripts/ps-domain.php "$PS_DOMAIN" http
	exit 0
fi

host=''
for _ in $(seq 1 60); do
	host="$(php -r 'echo json_decode((string) @file_get_contents("http://tunnel:2000/quicktunnel"), true)["hostname"] ?? "";')"
	[ -n "$host" ] && break
	sleep 1
done

if [ -z "$host" ]; then
	echo 'Tunnel did not start, check: docker compose logs tunnel' >&2
	exit 1
fi

php modules/voucherly/scripts/ps-domain.php "$host" https
