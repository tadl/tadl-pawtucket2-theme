# Catalogue database-write audit

The goal is to avoid unnecessary theme database writes and other behavior that
harms performance or accessibility. Intentional features and account actions
remain supported. This is not a requirement to prohibit all Pawtucket database
writes or modify its startup, authentication and maintenance behavior.

The following theme protections are implemented. They preserve native access,
representation permissions, readable metadata and ordinary result navigation.

| Path | Protection |
| --- | --- |
| Object, collection and authority details | The theme Detail controller omits `registerItemView()` permanently. Neither `view_count` nor the native recently-viewed list is updated. The old `record_detail_views` setting cannot restore tracking. |
| Site Pages | The theme Default controller passes `incrementViewCount = false` for both ordinary and `_default` routes. Publishing/editing FAQ entries in Providence is separate. |
| Searches | `dont_use_search_log = 1` in `conf/app.conf` prevents native `ca_search_log` inserts. |
| Back/Previous/Next and search history | `always_use_session_based_storage_for_find_result_contexts = 1` keeps result contexts out of signed-in `ca_users` records. |
| Original and generated downloads | Detail and ImageDownload controllers omit `Downloadlog`. Native file selection, permissions and streaming remain. ZIP cleanup never deletes source originals. |
| Catalogue editing | Comment forms, comment/tag/rating submission and annotation saves return 404 for anonymous and staff sessions. Existing annotation read endpoints remain. |
| Public preference/contact forms | Theme CSRF tokens use session storage instead of the native SQL token store. POST and CSRF validation remain required. Account forms retain native protection. |
| Theme result/media caches | Use the native external cache backend or private files. The theme installs no schema or index during a request. |

Use web-server access logs for traffic analysis. Do not restore database usage
counters, SQL analytics or catalogue editing as part of routine theme maintenance.

## Explicit writes that remain available

Staff login/logout, profile changes and password recovery remain native account
features with their own validation and permissions. A link to Providence opens
its separately authenticated editor; it does not edit the catalogue in Pawtucket.

Maintenance commands such as FAQ registration are explicit administrator tools,
not web-request installation steps. They can write when deliberately applied with
their documented backups and checks. Face detection writes cache files only.

## Native application findings outside this task

Inspection of Pawtucket 2.0.10 also found the paths below outside theme control.
These are background findings, not additional installation requirements or an
approved core-change plan. The user declined the separate core proposal.

| Native path | Effect and boundary |
| --- | --- |
| `index.php` → `GarbageCollection::gc()` | Before theme dispatch, periodically deletes stale SQL `PersistentCache` entries, including CSRF/session entries. Redis does not prevent these cleanup queries. |
| `RequestHTTP` → `ca_users::close()` | Authenticated browsing updates the user's last ping and automatically saves changed user variables at request shutdown. Session-based result contexts remove one source of these writes, but not the native ping. |
| `Session` with `__CA_CACHE_BACKEND__ = 'file'` | Native code selects SQL `PersistentCache` for sessions. Redis/another external backend avoids this particular fallback. |
| Native TileViewer construction | Creates SQL-backed CSRF tokens before rendering the theme's viewer wrapper; it also enables annotation editing controls for signed-in users. The theme rejects saves, but cannot prevent the earlier token writes from its view template. |
| Native account forms | Their CSRF tokens and explicit account changes remain SQL-backed. These are the intentional account exception, not catalogue state. |

Changing the startup/session/viewer paths would require a separate application
task. No core patch is needed for the completed theme optimizations.
Do not work around them by disabling CSRF, replacing the authenticated user with
an anonymous object, or weakening native access checks. The shipped media viewer
can still show native edit controls to staff until its application-level behavior
is changed; catalogue saves remain disabled.

Native search/browse engines may also create, populate and drop temporary SQL
tables for query execution. Those are working data, not catalogue updates. A
database account restricted to `SELECT` alone is therefore not a drop-in setup,
and it would also prevent the explicitly retained account operations.

## Verification

Standalone synthetic tests cover locked counter traps, original-file preservation,
rejected catalogue edits, session CSRF, Site Page rendering and retained native
access/navigation/media behavior. Optional native-reference checks compare retained
controller tokens against Pawtucket 2.0.10. These tests do not establish a deployed
server's complete SQL trace or apply the separate native application changes.
