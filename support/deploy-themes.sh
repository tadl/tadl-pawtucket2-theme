#!/usr/bin/env bash

set -euo pipefail

usage() {
	printf 'Usage: %s user@host [--dry-run] [tadl|kcl]\n' "$0"
	printf '%s\n' 'Without a theme argument, deploy both TADL and KCL.'
}

if [[ "${1:-}" == '--help' && "$#" -eq 1 ]]; then
	usage
	exit 0
fi
if [[ "$#" -lt 1 ]]; then
	usage >&2
	exit 1
fi
remote_host="$1"
shift
dry_run=0
if [[ "${1:-}" == '--dry-run' ]]; then
	dry_run=1
	shift
fi
if [[ "$#" -eq 0 ]]; then
	themes=(tadl kcl)
elif [[ "$#" -eq 1 && ( "$1" == 'tadl' || "$1" == 'kcl' ) ]]; then
	themes=("$1")
else
	usage >&2
	exit 1
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
if [[ "${dry_run}" -eq 1 ]]; then
	exec bash "${script_dir}/deploy-theme.sh" --dry-run "${remote_host}" "${themes[@]}"
fi
exec bash "${script_dir}/deploy-theme.sh" "${remote_host}" "${themes[@]}"
