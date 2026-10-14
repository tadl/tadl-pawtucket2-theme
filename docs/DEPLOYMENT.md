# Theme deployment

Routine theme deployment does not clear application caches. CSS/JavaScript URLs
include content hashes, native configuration caching checks file modification
times, and PHP OPcache must have timestamp validation enabled so changed PHP files
are picked up automatically. Routine deploys neither reload Apache nor clear caches.
A cache purge would discard useful cached data and sessions.

After the operator authorizes deployment, run the enclosing `deploy` helper, or
from a standalone theme checkout:

```sh
bash support/deploy-theme.sh root@catalog.example.org
```

Replace the synthetic SSH target with the operator's host. The script uses the
existing `/var/www/pawtucket2/themes/tadl` layout and requires permission to write
there. Its source directory comes
from the script's location, so it works independently of the caller's working
directory. The enclosing workstation helper supplies its local SSH target and
delegates to this tracked implementation.

The script retains the existing rsync exclusions and does not use `--delete`.
Inspect server-local overrides before deployment: matching theme files are still
overwritten. A failed transfer exits unsuccessfully without reporting completion.
A partially failed rsync can leave some files copied and needs operator attention,
not an assumed rollback. The completion message is printed only after rsync succeeds.
The helper runs no remote shell commands and performs no service restart/reload.

Before using this workflow on a different installation, check the web SAPI
OPcache configuration: `opcache.validate_timestamps` must be enabled. Allow its
`opcache.revalidate_freq` interval before verifying PHP changes. With validation
disabled, a separate runtime invalidation/reload procedure is required; a browser
Reload or Redis purge does not invalidate PHP bytecode. Do not change that server
policy implicitly as part of theme deployment.

Neither Redis nor file caches are purged. Temporary files, durable face-detection
data and the shared database's persistent cache are preserved. The deployment
script does not run `caUtils clear-caches`, Redis flush commands or temporary-file
deletion. Cache maintenance remains a separately authorized operation: native
`clear-caches --cache=app` also clears temporary files, user sessions and the
shared persistent-cache table, so it is too broad for routine theme deployment.

Verify changed pages and a normal browser Reload after deploying. Source commits,
pushes and local checks do not deploy or clear production caches. Root robots
policy installation, FAQ activation and the face-detector runtime remain separate
steps documented in their feature guides.

Local regression check, with synthetic `rsync` and `ssh` executables only:

```sh
php tests/theme_deploy_test.php
bash -n support/deploy-theme.sh
```
