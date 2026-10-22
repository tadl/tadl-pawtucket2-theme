# Catalogue performance improvements

Recorded October 22, 2026. Includes the shared result-data cache added after
implementation commit `d0c9d4e`.

The performance work reduced repeated database queries, cache requests, media
decoding and thumbnail rendering. The latest warm native application profiles
for 72-item object browses improved from approximately 2.4–2.5 seconds to
1.7–1.9 seconds with all items, and from 3.2–3.3 seconds to 1.8–1.9 seconds with
the media-only filter. Current permissions and accurate filter counts were
preserved.

The implementation stays in the theme and uses the application's existing cache
backend. An optional MySQL index was also installed during the optimization
pass. No Pawtucket or Providence core files were changed, and no additional
service or hardware upgrade was required.

## Sources of the delay

A page displaying 72 cards can still need to consider the entire matching
catalogue. The media-only option must determine which objects have usable public
media before calculating totals and pagination. Sidebar filters also need counts
across those results, rather than just the visible cards.

Profiling identified repeated cache lookups and descriptor decoding, expensive
per-card thumbnail work, repeated expansion of the same result IDs for filter
counts, and rebuilding largely unchanged filter choices. Native result generation
and sorting also contribute to response time.

## Changes to result delivery

### Include results in the initial response

Search, Browse, collection contents and related-item previews now render their
cards in the original HTML response. This removed the extra AJAX request and the
brief loading placeholder. Paging and hierarchy navigation use ordinary links.

The change makes results usable as soon as the response arrives and supports
navigation without JavaScript. The server still performs the database work needed
to produce those results. Implementation: [server result composition](../helpers/server_results.php).

### Keep rendering within the requested page

Returning from an object to its results now locates the object's position in the
already ordered result IDs and jumps directly to the appropriate page. A missing,
stale or crafted return target can no longer make the view render the remainder
of a large result set.

Card rendering, thumbnail lookups and format-tag inventory remain bounded by the
selected page size. Regression checks exercise missing and distant return targets
against 5,000 synthetic results. Implementation: [result paging helpers](../views/Browse/tadl_result_helpers.php).

### Reject retired exports before expensive processing

Legacy result-export requests for PDF, Excel and PowerPoint now receive HTTP 410
before native result processing starts. Crawlers following old export URLs
therefore cannot trigger those expensive exports. Individual media downloads and
finding aids remain available. Implementation: [export guards](../helpers/result_exports.php).

## Changes to media filtering

### Batch cache lookups and descriptor decoding

Media usability is saved in stable numeric buckets containing at most 500
representations. One cache request retrieves a bounded map of fingerprints and
usability booleans, avoiding a network round trip for every representation.
Overlapping searches and different sort orders can reuse the same buckets.

The relationship queries collect pending representations across the complete
candidate set before decoding. They also return current descriptor fingerprints,
so those fingerprints do not need a second query for the same objects. Untested
representations are decoded in batches, trying alternatives only while an object's
media eligibility remains unresolved.

The saved value describes whether a media descriptor contains a usable original
or supported embed. It does not decide whether a visitor can access an object.
Implementation: [media eligibility](../helpers/media_filter_helpers.php) and
[descriptor validity cache](../helpers/media_usability.php).

### Reuse unchanged validity for longer

Descriptor-validity entries now expire after one day instead of five minutes.
The shorter expiry caused an unchanged catalogue to repeatedly decode its media
descriptors, producing periodic slow requests.

Each request still reads the current SHA-256 fingerprint. A changed descriptor
invalidates its saved validity immediately, while access and deletion are checked
separately in the current request. Cache keys include the site and decoder
implementation, and cache failures fall back to native decoding.

### Avoid duplicate permission queries

The joined media query already checks both object and representation access and
deletion. Where these checks completely establish row visibility, the theme uses
that result directly instead of querying the same flags again.

Bundle checks still run. When native type restrictions, source restrictions or
Pawtucket record ACLs apply, the complete native bulk permission path remains in
use. This removes duplicate work while retaining the applicable authorization
rules. Implementation: [record access helpers](../helpers/record_access.php).

## Changes to thumbnails and filter counts

### Fetch primary thumbnails in batches

The grid and list views now fetch primary representation relationships and their
permitted descriptors for the displayed page in batches. The native media renderer
still produces the image tags, with primary-image preference, secondary-image
fallback, focal points and responsive sources preserved.

The surrounding thumbnail link supplies the accessible record name. This avoids
repeated expansion of representation-label templates for every card. In an earlier
profile, primary-thumbnail processing fell from roughly 0.8 seconds to 0.03 seconds
for a 72-card page. Implementation: [object thumbnails](../helpers/object_thumbnails.php).

### Recount the smaller media cohort

Media-only filter counts use whichever is smaller: the included objects or the
excluded objects. When the excluded cohort is smaller, its counts are subtracted
from the native baseline. The counts remain accurate for the displayed results.

For a supported unfiltered anonymous browse, an isolated native counter reuses
the exact cohort within the request. It avoids repeatedly expanding the same
primary-key restriction and skips unrelated facet-availability work. More complex
searches retain the general counting path. Adjusted counts are not persisted in
the native shared facet cache.

This reduced an unfiltered media-facet recount from approximately 0.6 seconds to
0.25 seconds in an earlier profile. Implementation: [media facet counts](../helpers/media_facet_counts.php).

## Reuse unchanged browse filters

Large unfiltered anonymous object browses can reuse native type, people, place and
subject filter choices. This applies to result sets containing at least 128 IDs.
This narrower optimization remains available independently of the result-data
cache below. Logged-in sessions, unsupported facets and additional native
permission restrictions retain native computation.

Before reuse, the theme reads and fingerprints the current scalar inputs:
records, access and deletion flags, labels, hierarchy, type lists and relationships.
The key also includes result IDs, criteria, access options, type/source
restrictions, locale, blank-label settings and implementation hashes. Matching
fingerprints allow expensive native filter generation to be reused without
waiting for an expiry to notice a metadata or visibility change.

The native availability boolean is stored separately from full filter content,
preserving the behavior of empty groups. An input change during computation
prevents publishing that cache entry. Entries expire after one hour and have a
2 MiB compressed size limit; malformed data or cache failures fall back to native
computation.

The integration uses a theme override of the Pawtucket 2.0.10 Browse controller
and a theme subclass of the native object browse engine. The override must be
reviewed against upstream when upgrading Pawtucket. Implementation:
[browse subclass](../helpers/catalogue_browse.php),
[input fingerprints](../helpers/facet_snapshot.php) and
[theme Browse controller](../controllers/BrowseController.php).

## Share computed result data

The next pass caches the expensive data used to build anonymous object results:
wildcard Browse execution, complete sorted ID lists, supported filter choices,
media-eligible object IDs and adjusted filter counts. Paging slices an existing
ordered set. Large object Search and collection-content results can reuse the
media stages too; full-text matching still reads the current search index.
Rendered pages, sessions and CSRF controls are generated on every request.

The implementation uses native `ExternalCache`, so an installation already using
Redis needs no second client or service. It requires MySQL and bypasses restricted
or privileged sessions. Before reuse it checks the native change-log count/max and
current visibility, labels, relationships, hierarchy and media fingerprints.
Entries also depend on the exact result scope, options, access mask, media
preference, locale, configuration and source. New values are published at request
shutdown only if the inputs still match. Native-cache hydration checks reject a
concurrent overwrite that returns another result set.

Entries expire after an hour, with limits of 4 MiB compressed / 32 MiB JSON.
MySQL named locks coordinate missing values with bounded waits; malformed entries,
cache outages and unsupported policies retain native computation. Small media
sets avoid the extra snapshot cost. Normal Providence edits invalidate through
the change log; direct SQL visibility/media changes are independently detected.
After arbitrary direct SQL metadata maintenance that bypasses native logging,
increment `tadl_result_cache_epoch` in `conf/app.conf`. The new layer can be bypassed
with `disable_tadl_result_cache = 1`. Neither operation needs a Redis/session flush.

Implementation: [result-data cache](../helpers/result_cache.php),
[browse adapter](../helpers/catalogue_browse.php),
[media filtering](../helpers/media_filter_helpers.php) and
[adjusted filter counts](../helpers/media_facet_counts.php).

## Optional database index

MySQL previously had to read media descriptor blobs and compute their SHA-256
hashes during the media relationship query. Two virtual generated columns now
describe the fingerprint and whether the descriptor is nonempty. A covering index
stores those values alongside representation ID, deletion and access fields.

The index is named `i_tadl_media_identity`. MySQL maintains its values when the
underlying fields change. The generated columns are invisible to `SELECT *`,
while the index itself is visible. The indexed query explicitly requests this
index because the tested optimizer otherwise preferred the primary index and
recomputed the virtual values.

The theme checks the index's visibility, column order and generated expressions
once per connection per request. Installations without a matching index use the
ordinary descriptor-hashing query. Web requests never install schema changes.

On the tested MySQL 8.0.46 installation, the identity query fell from approximately
0.44 seconds to 0.20 seconds, with matching public relationship and fingerprint
results. The index adds storage and some write cost. Schema details, installation
SQL and rollback are in the [performance operations guide](PERFORMANCE.md).

## Measurements and verification

The latest comparison used the same catalogue, anonymous access, 72-card page size,
sort and media preference for each pair of source revisions. Serial native
application profiles included bootstrap, the controller, result views, page
shell and deferred cache writes.

| Latest warm comparison | Before | After |
| --- | ---: | ---: |
| All-item object browse | 2.4–2.5 s | 1.7–1.9 s |
| Media-only object browse | 3.2–3.3 s | 1.8–1.9 s |
| Media-only browse, offset 216 | 3.2–3.3 s | 1.8 s |
| List view, descending title sort | 2.3–2.4 s | 1.6 s |
| Media-only browse with an object-type filter | 3.4–3.5 s | 1.9 s |

The optional media identity index was measured separately at approximately
0.44 to 0.20 seconds for its identity query.

An earlier comparison of the thumbnail, permission and recount changes measured
media-only browsing at approximately 4.5–5.0 seconds before and 3.5–3.7 seconds
after. These are separate comparisons; component savings overlap and should not
be added together. CLI profiles are development measurements rather than live
HTTP latency or concurrent-load guarantees. First fills took about 4–7 seconds,
including native descriptor warming and the second input snapshot. Unique uncached
queries do not receive the warm-cache gain. The measured browse paths remain above
one second.

Validation preserved ordered result IDs, filter text/counts, displayed thumbnail
URLs, responsive sources and focal data. All 50 standalone PHP suites passed
locally, along with changed PHP lint, whitespace checks, accessibility browser
checks and server-rendered result navigation checks. Synthetic tests cover cache
invalidation, permission changes, malformed entries, failures, concurrent input
changes and bounded result rendering. The result-data suite additionally covers
page/sort reuse, media eligibility and count reuse, out-of-order log commits,
expiry, worker contention and native-cache hydration races.

## Experiments and deployment record

A separate object visibility/type index was trialled while invisible. It offered
little benefit and changed native ordering among tied results when enabled in
the test session, so it was removed. Native browse algorithm changes were also
evaluated in disposable copies, including compact result-ID predicates and linear
facet-array merging. Those core changes were not installed.

The earlier optimization pass applied the media identity index and pushed theme
implementation commit `d0c9d4e`. The result-data cache adds no schema change. Neither
pass deployed the theme, changed proxy settings, restarted services or purged
application caches. The validation above describes local and native application
checks; source control and deployment remain separate steps.

Routine theme deployment preserves existing caches and sessions. Changed source
fingerprints create new cache entries naturally. Shared rendered-page and native
browse-result caching remain disabled as shipped. The new layer uses its own
namespace with current input checks and explicit access/preference scope.

Freshness snapshots, application bootstrap, remaining native work and rendering
still limit response time. Further gains should be measured after deployment
under representative requests before adding more complexity.

## Implementation history

| Commit | Change |
| --- | --- |
| `ded38eb` | Render catalogue result cards in the initial response. |
| `05e3985` | Reject retired catalogue exports before native processing. |
| `f6526e7` | Batch media-usability cache lookups. |
| `59532d4` | Restore media-aware object filter counts. |
| `a3ae217` | Batch thumbnail work, reduce repeated permission/recount work and bound Back restoration. |
| `d0c9d4e` | Reuse validated browse filters and longer-lived descriptor validity; support the optional covering index. |
