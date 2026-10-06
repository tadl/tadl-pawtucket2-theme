# TADL Pawtucket theme

Read `README.md` and `docs/CODEX_HANDOFF.md` before substantive work. This file
also applies when the theme is cloned without the surrounding workspace.

## Scope and workflow

- This is the standalone `tadl` theme repository, normally on `main` with source
  remote `git@github.com:tadl/tadl-pawtucket2-theme.git`.
- Inspect Git status and relevant source before editing. Preserve user changes.
- Prefer theme configuration, helpers, controllers, views and scoped CSS over
  upstream application patches. Nearby Pawtucket/Providence trees are references.
- Keep changes focused and compatible with the existing native APIs and runtime.
- Use synthetic tests; do not commit catalog exports, production records, media,
  credentials, cookies, screenshots, private configuration or logs.
- Run checks relevant to the change. Normally commit and push coherent verified
  source changes; never force-push or rewrite user work without authorization.
- Do not deploy, run the enclosing deployment helper, clear production caches,
  restart services or mutate production data unless the user explicitly asks.
  A source push does not grant deployment authorization.

## Contracts to preserve

- The media preference defaults to **Only items with media**, persists in a
  cookie and stays out of ordinary URLs. It controls display, not authorization.
- Keep native record access, representation access, bundle permissions and ACLs.
  Filter eligible results before theme counts, paging and result-context storage.
- Preserve normal links, browser Back and direct-link fallbacks. Do not replace
  result pages with carousels or require AJAX for basic navigation.
- Keep browse/search thumbnails based on the primary representation. Object
  details may prefer attached video and cover it with the primary still image.
- Preserve the native player initialization, slide IDs and callbacks when
  changing the representation viewer. Avoid duplicate play controls.
- Keep responsive layouts, keyboard access, visible focus and readable controls.
- Do not restore collection or whole-page caching without handling the media
  preference and current access mask in cache keys.
- User instructions in the current conversation take precedence over these
  defaults. Consult the handoff for deferred product decisions before adding scope.

## Checks

From this repository, with PHP 8+ CLI (DOM, PDO SQLite, JSON, fileinfo and `proc_open`)
and Node.js plus Python 3.11+ available (Python is used by the package export checks):

```sh
(
  for test_file in tests/*_test.php; do
    php "$test_file" || exit 1
  done
)
```

Lint changed PHP files with `php -l` and review `git diff --check`. The handoff
maps tests to features. Standalone tests do not bootstrap the application or
connect to a production database. Browser verification remains separate.

Update the handoff when behavior, test requirements or unresolved decisions
change. Use relative paths; do not depend on a particular workstation or `/tmp`.
