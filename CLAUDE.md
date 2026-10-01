# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PrestaShop payment module for Voucherly (Italian meal voucher payments). Distributed as a ZIP attached to GitHub releases, not through PrestaShop Addons. Supports PrestaShop 1.7.8 to 9.x on PHP 7.4 or later: the Voucherly SDK uses typed properties, and 1.7.8 is the first PrestaShop release running on PHP 7.4.

## Commands

```bash
composer install                    # Dev tools (php-cs-fixer, phpstan, php-dev-tools) in the host vendor
composer fix-code                   # php-cs-fixer + autoindex + header-stamp

# Local test environment (Docker, run from Git Bash). Version: 1.7 (1.7.8.11, PHP 7.4), 8 (8.2.8, PHP 8.1, default), 9 (9.2.0, PHP 8.5)
scripts/env.sh start [1.7|8|9]      # http://localhost:8017 | 8080 | 8090, back office /admin-dev (demo@prestashop.com / prestashop_demo)
scripts/env.sh tunnel [version]     # Public HTTPS URL (Voucherly rejects non-HTTPS callback URLs); prints URL and a new admin password
scripts/env.sh tunnel-stop [version]
scripts/env.sh log [version]        # PHP error log + latest PrestaShop log entries
scripts/env.sh phpstan [version]    # PHPStan level 5 against that PrestaShop version, as the PrestaShop validator does
scripts/env.sh cs                   # php-cs-fixer and header-stamp in dry-run mode
scripts/env.sh console [version] <args>     # bin/console
scripts/env.sh zip                  # build/voucherly-prestashop.zip
scripts/env.sh destroy [version]    # Delete containers and data
```

The module folder is mounted live into the container, while its `vendor` is a separate volume holding only production dependencies, as in the ZIP. PrestaShop redirects every host but the main shop URL, so while the tunnel is up the shop lives on the tunnel host and `tunnel-stop` moves it back to localhost.

## Release

Releases work as in the Voucherly SDKs. `.github/workflows/ci.yml` checks every pull request and every push to `main`: PHPStan against 1.7.8, 8.2 and 9.2, coding standards, PHP 7.4 lint, and `scripts/build-zip.sh`, whose ZIP is kept as an artifact.

A release is a pushed tag `vX.Y.Z`. Bump the version and add the `## [X.Y.Z] - YYYY-MM-DD` section to `CHANGELOG.md` first (see Version Management). `.github/workflows/release.yml` checks that the tag, `voucherly.php`, `composer.json` and a dated `CHANGELOG.md` section agree, runs the CI, then builds the ZIP and creates the GitHub release with `voucherly-prestashop.zip` and that section as notes. Never create a GitHub release by hand. Files listed in `scripts/list-of-excluded-files.txt` are kept out of the ZIP.

The online PrestaShop validator (validator.prestashop.com) needs a PrestaShop account, so it is run by hand on the built ZIP.

## Architecture

- `voucherly.php`: `Voucherly extends PaymentModule`. Configuration and refund forms (`getContent`), checkout option and saved cards (`hookPaymentOptions`), Voucherly box on the order page (`hookDisplayAdminOrderMainBottom`).
- `controllers/front/payment.php`: builds `CreatePaymentRequest` from the cart and redirects to the Voucherly hosted checkout.
- `controllers/front/callback.php`: server-to-server webhook that creates the order with `validateOrder`. Voucherly retries it up to 3 times when the answer is not HTTP 2xx with `ok:true`, also concurrently, so it holds a MySQL `GET_LOCK` on the cart and answers duplicates with `ok:true`. It always answers HTTP 200 with `ok`/`stop` in the JSON.
- `controllers/front/redirect.php`: customer return from Voucherly. Voucherly delivers the callback first, so the order already exists.
- `classes/VoucherlyUsers.php`: maps PrestaShop customers to Voucherly customers, per environment (`t` sandbox, `p` live).

Payment gateways are cached in the `VOUCHERLY_GATEWAYS` configuration when the settings are saved; `Hidden` and `Custom` gateways are filtered out at display time.

## Version Management

Version must be updated in `voucherly.php` (`$this->version`) and `composer.json`, with a new section in `CHANGELOG.md`. Then run `composer update --lock`: the `version` of `composer.json` is part of the `content-hash` of `composer.lock`.

## Code Standards

- PrestaShop coding standard via `.php-cs-fixer.dist.php`, GPL headers via header-stamp (`assets/gpl.txt`); header-stamp is limited to source files because it rewrites the `composer.json` license.
- `composer fix-code` runs autoindex with `--exclude=vendor,build`: an `index.php` inside `vendor` makes php-cs-fixer stop silently with exit code 0. If php-cs-fixer stops at 0% without a report, delete `vendor` and run `composer install` again.
- PHP files are checked out with LF (`.gitattributes`), so php-cs-fixer gives the same result on Windows as in CI.
- Smarty variables are always escaped (`|escape:'html':'UTF-8'`); in the front office PrestaShop turns that modifier into a no-op because output is already escaped.
- Translations use the legacy system: `translations/it.php`, keys are `<{voucherly}prestashop>{source}_{md5}`. Front controllers must call `$this->module->l('...', 'payment')` with their file name as source.
- No automated test suite is configured.
