#!/usr/bin/env bash
# "scripts/env.sh start" runs this after every start, so each step must be safe to repeat.
set -euo pipefail

cd /var/www/html

# On first boot the image installs PrestaShop in the background, holding install.lock until it is done.
for _ in $(seq 1 180); do
	if [ -f app/config/parameters.php ] && [ ! -f install.lock ] && [ ! -d install-dev ]; then
		break
	fi
	sleep 5
done

if [ ! -f app/config/parameters.php ] || [ -f install.lock ] || [ -d install-dev ]; then
	echo 'PrestaShop is not installed, check: docker compose logs prestashop' >&2
	exit 1
fi

if ! php modules/voucherly/scripts/ps-setup.php is-installed; then
	php bin/console prestashop:module install voucherly
fi

php modules/voucherly/scripts/ps-setup.php configure
