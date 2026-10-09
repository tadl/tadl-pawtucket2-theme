#!/usr/bin/env bash

set -euo pipefail

if [[ "${1:-}" == '--help' ]]; then
	printf 'Usage: %s user@host\n' "$0"
	exit 0
fi
if [[ "$#" -ne 1 || ! "$1" =~ ^([A-Za-z0-9_.-]+@)?[A-Za-z0-9][A-Za-z0-9_.-]*$ ]]; then
	printf 'Usage: %s user@host\n' "$0" >&2
	exit 1
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
source_dir="$(cd -- "${script_dir}/.." && pwd)/"
remote_host="$1"
remote_theme_dir="/var/www/pawtucket2/themes/tadl"
destination="${remote_host}:${remote_theme_dir}/"

if [[ ! -f "${source_dir}conf/app.conf" || ! -d "${source_dir}views" ]]; then
	printf 'Theme source directory is incomplete: %s\n' "${source_dir}" >&2
	exit 1
fi

printf 'Deploying %s to %s\n' "${source_dir}" "${destination}"

rsync \
	--archive \
	--verbose \
	--itemize-changes \
	--exclude='.git/' \
	--exclude='.gitignore' \
	--exclude='README.md' \
	--exclude='.DS_Store' \
	"${source_dir}" \
	"${destination}"

# Theme assets carry content versions; configuration validates its file mtimes.
# Preserve application caches, sessions and temporary files during routine deploys.
printf 'Validating Apache configuration and reloading Apache on %s\n' "${remote_host}"
ssh "${remote_host}" 'apache2ctl configtest && systemctl reload apache2'

printf '%s\n' 'Deployment complete (application caches preserved)'
