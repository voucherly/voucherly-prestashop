#!/usr/bin/env bash
# Builds build/voucherly-prestashop.zip, used by "scripts/env.sh zip" and by the release workflow.
set -euo pipefail

cd "$(dirname "$0")/.."

version="$(php -r 'preg_match("/->version = \x27([^\x27]+)\x27/", file_get_contents("voucherly.php"), $m); echo $m[1] ?? "";')"
composer_version="$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["version"] ?? "";')"
if [ -z "$version" ] || [ "$version" != "$composer_version" ]; then
	echo "Version mismatch: voucherly.php '$version', composer.json '$composer_version'" >&2
	exit 1
fi

changelog_heading="$(grep -m1 -F "## [$version]" CHANGELOG.md || true)"
if [ -z "$changelog_heading" ]; then
	echo "CHANGELOG.md has no entry for $version" >&2
	exit 1
fi

if [ -n "${RELEASE_TAG:-}" ]; then
	if [ "${RELEASE_TAG#v}" != "$version" ]; then
		echo "Tag $RELEASE_TAG does not match module version $version" >&2
		exit 1
	fi
	if [[ "$changelog_heading" == *Unreleased* ]]; then
		echo "CHANGELOG.md still marks $version as Unreleased" >&2
		exit 1
	fi
fi

mapfile -t excluded < <(grep -vE '^\s*(#|$)' scripts/list-of-excluded-files.txt | tr -d '\r')

rm -rf build
mkdir -p build/voucherly

# Tracked and new files only, so local leftovers such as caches or the dev vendor never reach the ZIP.
git -c safe.directory="$PWD" ls-files -z --cached --others --exclude-standard | while IFS= read -r -d '' file; do
	[ -f "$file" ] || continue
	for pattern in "${excluded[@]}"; do
		# shellcheck disable=SC2053
		[[ "$file" == $pattern ]] && continue 2
	done
	mkdir -p "build/voucherly/$(dirname "$file")"
	cp -p "$file" "build/voucherly/$file"
done

# The lock pins the SDK release that was tested; it is not shipped.
cp composer.lock build/voucherly/
(cd build/voucherly && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet)
rm build/voucherly/composer.lock
# Dependencies ship their own tooling dotfiles, such as a php-cs-fixer config, which PrestaShop does not need.
find build/voucherly/vendor -type f -name '.*' -delete

# PrestaShop requires an index.php in every folder, and composer has just created new ones under vendor.
find build/voucherly -type d ! -exec test -e '{}/index.php' \; -exec cp index.php '{}/' \;

(cd build && zip -qrX voucherly-prestashop.zip voucherly)
echo "build/voucherly-prestashop.zip (version $version)"
