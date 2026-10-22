# Performance

For the completed changes and their measured effects, see the
[performance improvements report](PERFORMANCE_IMPROVEMENTS.md).

The theme works without a core patch, new service or additional database index.
Use the application's existing native cache backend. Keep theme page caching and
shared collection result caching disabled as shipped: those keys do not describe
all visitor preferences and current permissions.

## What is reused

- Media usability is decoded natively and saved in batches of up to 500
  representations. Each request still checks the current descriptor's SHA-256,
  record/representation access and deletion, relationships, bundles and native
  restrictions. Changed media invalidates its saved validity immediately. The
  one-day expiry limits unused cache entries; it does not delay visibility changes.
- Large unfiltered anonymous object browses reuse native type, people, place and subject
  filter choices. Each request fingerprints the actual scalar records, labels,
  access/deletion flags, hierarchy and relationships involved. Result IDs,
  criteria, access options, restrictions, locale and implementation also identify
  the entry. A cache hit requires matching current inputs. A change during native
  computation prevents saving that result. Entries expire after one hour and have
  bounded compressed size.
- Filtered searches, logged-in or specially restricted views, unsupported facets and result sets
  smaller than 128 IDs retain native facet computation. Cache failures fall back
  to native computation. The cache stores digests and native readable facet data;
  it does not store raw private source records.
- Visible-page thumbnails and format tags use batched lookups. Result pages
  contain their cards in the initial HTML and retain ordinary GET navigation.

The object browse integration is a theme controller override based on Pawtucket
2.0.10. Review it against the upstream controller when upgrading Pawtucket.

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
| All items | 2.4 s | 1.8 s |
| Only items with media | 3.5 s | 2.4–2.5 s |

The indexed media identity query alone fell from roughly 0.44 s to 0.20 s.
Ordered result IDs, filter text/counts and thumbnail sources matched in the A/B
checks. These are development measurements, not a universal response-time
guarantee or proof that a particular server has deployed the revision. Cold
requests can cost more. Large native facet queries and result sorting still
consume time even on fast hardware.

No cache purge or service restart is required for these theme changes. Source
updates change the relevant cache fingerprints; the new entries warm naturally.
