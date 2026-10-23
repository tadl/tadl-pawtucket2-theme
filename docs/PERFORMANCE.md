# Performance

For the completed changes and their measured effects, see the
[performance improvements report](PERFORMANCE_IMPROVEMENTS.md).

The theme works without a core patch, new service or additional database index.
Use the application's existing native cache backend. Keep theme page caching and
shared collection result caching disabled as shipped: those keys do not describe
all visitor preferences and current permissions.

## Avoid writes when opening detail pages

The theme omits native detail-page view tracking. Pawtucket normally updates
the record's `view_count` before rendering
its detail page. A long-running Providence import can hold a lock on that same
record, making a public page wait for a database write even when its ordinary
reads would succeed. More RAM, smaller result pages and result caching do not
remove that wait.

The theme also removes Site Page counters and download logging, disables native
SQL search logging, and stores result navigation in the session instead of
`ca_users`. Detail view tracking cannot be enabled through the old
`record_detail_views` setting. Native `caRecentlyViewed` tracking is also omitted;
the theme uses neither that list nor the database view counter. Web-server logs
remain available for traffic analysis. Explicit account actions are preserved.

These theme protections require no core patch or database migration. Native
startup, session and viewer writes found during the audit are outside this task;
making the entire application read-only is not the goal. See the
[database-write audit](READ_ONLY_CATALOGUE.md).

## What is reused

- Media usability is decoded natively and saved in batches of up to 500
  representations. Each request still checks the current descriptor's SHA-256,
  record/representation access and deletion, relationships, bundles and native
  restrictions. Changed media invalidates its saved validity immediately. The
  one-day expiry limits unused cache entries; it does not delay visibility changes.
- Anonymous object Browse reuses native wildcard result IDs and facet availability,
  complete sorted ID sets, and supported type/people/place/subject filter choices.
  Page offsets and sizes are applied afterwards, so moving to another page does
  not rerun the complete sort. Filtered Browse still executes its native query;
  its ordering and supported facets can be reused for the freshly returned IDs.
- Large object sets also reuse media-eligible IDs and adjusted facet counts across
  requests, including object Search and collection contents. Sets smaller than
  128 IDs keep their existing media path. Full-text queries execute against the
  current native search index rather than reusing an old match list.
- The new result-data cache requires MySQL. Logged-in/privileged sessions,
  additional ACL/type/source policies, restricted bundles and unavailable input
  snapshots bypass it. Unsupported facet contents retain native computation.
  The earlier narrow wildcard-facet cache remains available independently.
- Shared entries contain scalar result data, not rendered pages, request objects,
  sessions, CSRF tokens or configuration secrets. Payloads are compressed, expire
  after one hour, and are limited to 4 MiB compressed / 32 MiB JSON; ID lists are
  limited to 200,000 unique positive IDs. Malformed entries and cache failures
  fall back to native computation.
- Visible-page thumbnails and format tags use batched lookups. Result pages
  contain their cards in the initial HTML and retain ordinary GET navigation.

The Browse, Detail and Site Page integrations are theme controller overrides based
on Pawtucket 2.0.10. Review them against the upstream controllers when upgrading
Pawtucket.

## Freshness and cache controls

Each participating request reads the native change-log count and greatest log ID,
plus current scalar visibility, labels, hierarchy, relationships and representation
descriptor fingerprints. The count detects a transaction committing an older log
ID after a newer one. Access/deletion and media changes are detected even when
made directly in SQL. Source, configuration, criteria, exact result IDs/options,
access mask, locale and media preference also identify each cache entry. View
counters are excluded. The one-hour TTL is an expiry limit, not a permission delay.

New entries are queued until request shutdown and published only if a second
fresh snapshot matches the first. MySQL named locks coordinate missing entries;
another worker waits up to two seconds per entry for a published value, then
falls back to computation. Locks are released at shutdown. Native browse-cache
hydration uses public APIs and verifies the returned IDs before accepting them,
since native browse keys do not include access scope. Normal ResultContext and
Back/Previous/Next handling remain in the request.

Normal Providence model writes invalidate data through `ca_change_log`. Direct SQL
edits to arbitrary attribute/sort fields that bypass those logs are not all
fingerprinted. After such maintenance, increment `tadl_result_cache_epoch` in the
theme's `conf/app.conf`; the changed configuration creates a new namespace without
deleting sessions or flushing Redis. The shipped epoch is `1`. Set
`disable_tadl_result_cache = 1` to bypass this new layer while keeping the existing
descriptor and narrow facet optimizations.

The first request after expiry, relevant edits or a source update pays for native
computation and the final snapshot. Measured cold fills took about 4–7 seconds,
including initial descriptor warming; warm 72-card object requests were about
1.7–1.9 seconds. These are serial CLI application profiles, not live HTTP or
concurrent-load guarantees. Unique uncached searches do not receive the same gain.

## Optional MySQL 8 media index

On large media-only result sets, hashing every descriptor blob costs noticeable
time. MySQL can maintain those hashes in a covering secondary index. This is
optional, administrator-run schema maintenance; the theme never installs it.
Other database versions retain the ordinary hashing path.

This has been tested with MySQL 8.0.46. Before applying it to your installation,
save the table definition, check for existing columns/indexes with these names,
and use your normal database change procedure. `LOCK=NONE` permits concurrent
writes during the operation, but short metadata locks are still required.
The short session lock timeout makes contention fail promptly instead of waiting
behind a long transaction. Measure on your own data before keeping an index.

```sql
SHOW CREATE TABLE ca_object_representations;
SHOW INDEX FROM ca_object_representations;

SET SESSION lock_wait_timeout = 3;

ALTER TABLE ca_object_representations
  ADD COLUMN tadl_media_fingerprint CHAR(64) CHARACTER SET ascii
    GENERATED ALWAYS AS (SHA2(media, 256)) VIRTUAL INVISIBLE,
  ADD COLUMN tadl_has_media TINYINT
    GENERATED ALWAYS AS (media IS NOT NULL AND media <> '') VIRTUAL INVISIBLE,
  ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE ca_object_representations
  ADD INDEX i_tadl_media_identity
    (representation_id, deleted, access, tadl_has_media, tadl_media_fingerprint),
  ALGORITHM=INPLACE, LOCK=NONE;
```

The generated columns remain outside `SELECT *`. The visible secondary index
contains their values and is maintained automatically when media or access flags
change; catalogue records and original media are not rewritten by the theme.
The index adds storage and some write work.

The theme verifies the index's name, visibility, column order and generated
expressions through `information_schema` once per database connection per request.
Only a verified index is requested explicitly by the media relationship query;
otherwise the query hashes the original descriptor. Verify with `EXPLAIN` that
the indexed query uses `i_tadl_media_identity` and reports `Using index`. Native
access/deletion predicates remain in the query.

To roll back, first make the index invisible so subsequent requests choose the
fallback. Allow in-flight result requests to finish before dropping it and the
generated columns:

```sql
SET SESSION lock_wait_timeout = 3;
ALTER TABLE ca_object_representations
  ALTER INDEX i_tadl_media_identity INVISIBLE,
  ALGORITHM=INPLACE, LOCK=NONE;

-- After in-flight requests finish:
ALTER TABLE ca_object_representations
  DROP INDEX i_tadl_media_identity, ALGORITHM=INPLACE, LOCK=NONE;
ALTER TABLE ca_object_representations
  DROP COLUMN tadl_media_fingerprint, DROP COLUMN tadl_has_media,
  ALGORITHM=INPLACE, LOCK=NONE;
```

## Measuring changes

Compare the same URL, media preference, page size, sort, permissions and cache
state. Measure both first and repeated requests, and distinguish time to first
byte from image loading and full browser rendering. Start with serial requests;
a concurrency test against production requires separate operational planning.

Native application A/B profiles during development measured these approximate
warm times for 72-card object browses, including CLI application bootstrap:

| View | Before | After |
| --- | ---: | ---: |
| All items | 2.4–2.5 s | 1.7–1.9 s |
| Only items with media | 3.2–3.3 s | 1.8–1.9 s |

The indexed media identity query alone fell from roughly 0.44 s to 0.20 s.
Ordered result IDs, filter text/counts and thumbnail sources matched in the A/B
checks. These are development measurements, not a universal response-time
guarantee or proof that a particular server has deployed the revision. Cold
requests can cost more. Fresh input snapshots, bootstrap, native queries and
rendering still consume time even on fast hardware.

No cache purge or service restart is required for these theme changes. Source
updates change the relevant cache fingerprints; the new entries warm naturally.
