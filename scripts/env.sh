#!/usr/bin/env bash
# Local test environment: scripts/env.sh <command> [1.7|8|9], default version 8.
set -euo pipefail

cd "$(dirname "$0")/.."

# Git Bash would rewrite the container paths below into Windows paths.
export MSYS_NO_PATHCONV=1

usage() {
	cat >&2 <<-'EOF'
	Usage: scripts/env.sh <command> [1.7|8|9] [args]

	  start        Start PrestaShop, install the module and a test product
	  stop         Stop the containers
	  destroy      Delete containers and data
	  tunnel       Expose the shop on a public HTTPS URL (Voucherly rejects non-HTTPS callback URLs)
	  tunnel-stop  Stop the tunnel and move the shop back to localhost
	  log          Show the PHP error log and the latest PrestaShop log entries
	  console      Run bin/console with the given args
	  exec         Run a command in the PrestaShop container as www-data
	  phpstan      Run PHPStan against this PrestaShop version
	  cs           Check coding standards (php-cs-fixer, header-stamp)
	  zip          Build the distributable ZIP in build/
	EOF
	exit 1
}

command="${1:-}"
[ -n "$command" ] || usage
shift

version="${PS_ENV:-8}"
if [[ "${1:-}" =~ ^(1\.7|8|9)$ ]]; then
	version="$1"
	shift
fi

case "$version" in
	1.7) export PS_IMAGE=1.7.8.11-7.4-apache PS_PORT=8017 ;;
	8) export PS_IMAGE=8.2.8-8.1-apache PS_PORT=8080 ;;
	9) export PS_IMAGE=9.2.0-apache PS_PORT=8090 ;;
esac
export COMPOSE_PROJECT_NAME="voucherly-ps-${version//./}"

in_shop() {
	docker compose exec -T --user www-data prestashop "$@"
}

case "$command" in
	start)
		docker compose up -d db prestashop
		docker compose run --rm composer install --no-dev --optimize-autoloader --no-interaction --no-progress
		# PrestaShop logs an error when a module folder is not writable, and the volume is created by root.
		docker compose exec -T prestashop chown -R www-data:www-data modules/voucherly/vendor
		in_shop bash modules/voucherly/scripts/ps-setup.sh
		;;
	stop)
		docker compose --profile tunnel stop
		;;
	destroy)
		docker compose --profile tunnel --profile tools down -v
		;;
	tunnel)
		docker compose --profile tunnel up -d tunnel
		in_shop bash modules/voucherly/scripts/ps-tunnel.sh
		;;
	tunnel-stop)
		docker compose --profile tunnel stop tunnel
		in_shop bash modules/voucherly/scripts/ps-tunnel.sh --stop
		;;
	log)
		in_shop bash -c 'tail -n 100 /tmp/php-errors.log 2>/dev/null || echo "No PHP errors logged"'
		docker compose exec -T db mariadb -uroot -proot prestashop -e \
			'SELECT date_add, severity, object_type, object_id, LEFT(message, 200) AS message FROM ps_log ORDER BY id_log DESC LIMIT 20'
		;;
	console)
		in_shop php bin/console "$@"
		;;
	exec)
		in_shop "$@"
		;;
	phpstan)
		docker compose --profile tools run --rm phpstan analyse --configuration=tests/phpstan/phpstan.neon "$@"
		;;
	cs)
		# PHP 7.4 as in CI: the lowest supported version, which php-cs-fixer recommends for this project.
		docker run --rm -v "$PWD:/app" -w /app --entrypoint bash prestashop/prestashop:1.7.8.11-7.4-apache -c \
			'vendor/bin/php-cs-fixer fix --dry-run --diff && vendor/bin/header-stamp --license=assets/gpl.txt --exclude=vendor,node_modules,tests,build --extensions=php,js,css,tpl --dry-run'
		;;
	zip)
		docker run --rm -v "$PWD:/app" -w /app composer:2 bash scripts/build-zip.sh
		;;
	*)
		usage
		;;
esac
