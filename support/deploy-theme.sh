#!/usr/bin/env bash

set -euo pipefail

usage() {
	printf 'Usage: %s [--dry-run] user@host [tadl|kcl ...]\n' "$0"
	printf '%s\n' 'Without a theme argument, deploy only this TADL checkout.'
}

if [[ "${1:-}" == '--help' && "$#" -eq 1 ]]; then
	usage
	exit 0
fi
dry_run=0
if [[ "${1:-}" == '--dry-run' ]]; then
	dry_run=1
	shift
fi
if [[ "$#" -lt 1 || ! "$1" =~ ^([A-Za-z0-9_.-]+@)?[A-Za-z0-9][A-Za-z0-9_.-]*$ ]]; then
	usage >&2
	exit 1
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
tadl_source_dir="$(cd -- "${script_dir}/.." && pwd)"
remote_host="$1"
shift
if [[ "$#" -eq 0 ]]; then
	set -- tadl
fi

themes=()
source_dirs=()
destinations=()
# Validate every requested source before transferring any theme.
for theme in "$@"; do
	case "${theme}" in
		tadl)
			source_dir="${tadl_source_dir}/"
			remote_theme_dir='/var/www/pawtucket2/themes/tadl'
			;;
		kcl)
			source_dir="$(dirname -- "${tadl_source_dir}")/kcl/"
			remote_theme_dir='/var/www/kcl/themes/kcl'
			# KCL inherits TADL's scoped runtime through sibling-relative links.
			# Install that complete parent before the child; never update the
			# independent TADL instance as a side effect of selecting KCL.
			source_dirs+=("${tadl_source_dir}/")
			destinations+=("${remote_host}:/var/www/kcl/themes/tadl/")
			;;
		*)
			usage >&2
			exit 1
			;;
	esac
	for selected in "${themes[@]:-}"; do
		if [[ "${selected}" == "${theme}" ]]; then
			printf 'Theme requested more than once: %s\n' "${theme}" >&2
			exit 1
		fi
	done
	themes+=("${theme}")
	source_dirs+=("${source_dir}")
	destinations+=("${remote_host}:${remote_theme_dir}/")
done
for source_dir in "${source_dirs[@]}"; do
	if [[ ! -f "${source_dir}conf/app.conf" || ! -d "${source_dir}views" ]]; then
		printf 'Theme source directory is incomplete: %s\n' "${source_dir}" >&2
		exit 1
	fi
done

rsync_options=(
	--archive
	--verbose
	--itemize-changes
	--exclude='.git/'
	--exclude='.gitignore'
	--exclude='README.md'
	--exclude='.DS_Store'
)
if [[ "${dry_run}" -eq 1 ]]; then
	rsync_options+=(--dry-run)
fi

for index in "${!source_dirs[@]}"; do
	printf 'Deploying %s to %s\n' "${source_dirs[index]}" "${destinations[index]}"
	rsync "${rsync_options[@]}" "${source_dirs[index]}" "${destinations[index]}"
done

# Theme assets carry content versions; configuration validates its file mtimes.
# Preserve application caches, sessions and temporary files during routine deploys.
if [[ "${dry_run}" -eq 1 ]]; then
	printf '%s\n' 'Dry run complete (no theme files changed)'
else
	printf '%s\n' 'Deployment complete (application caches preserved; no service reload)'
fi
