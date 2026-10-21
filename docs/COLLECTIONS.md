# Collection pages

Collection details initially show a flat list of the selected collection's objects
and every readable descendant's objects. The catalogue hierarchy is preserved;
this is a presentation choice. An object linked to several nodes appears once
in the flat results. Selecting a subcollection includes only that branch, not
its ancestors, siblings or unrelated collections.

## Flat and hierarchy views

When readable children exist and the browser is enabled in configuration,
**Collection hierarchy view** appears beneath **View finding aid**. It opens
the hierarchy browser and restores directly attached items on the collection page.
**Flat collection view** returns to the flat list. Leaf collections omit the switch.
The chosen mode is remembered for subsequent collections with a hierarchy.
Both view-switch links have an eye icon; **View finding aid** has a document icon.
The document viewer provides the download option.

| Behavior | Flat view | Hierarchy view |
| --- | --- | --- |
| Item scope | Selected collection and readable descendants | Objects directly attached to the selected collection |
| Hierarchy browser | Hidden | Shown when enabled and readable children exist |
| Result controls | Sort by, Tiles/List and paging | Same controls for directly attached objects, including a single item |
| Finding-aid scope | Top-level collection and readable descendants | Same scope as flat view |

Both modes use the current **Only items with media / All items** preference and
native record, bundle and Pawtucket ACL permissions. Unavailable ancestors prune
their whole branch. Distinct objects are not merged because their names or
identifiers happen to match. The switch retains sort/display state and resets
paging to the first page of the new scope.

Single items use the regular collection-items section in both modes. Object
images and video previews are never embedded in the collection's metadata
columns. The shared results loader applies the current media preference and
permissions before showing cards and totals.

The overview groups description/source, scope/content and provenance separately
from creators, dates, extent, language, subjects, rights, external links and related
records. The same fields apply to every collection level, including series,
containers and folders, independently of linked-object counts or media.
Description, extent and other facts come from the selected unit, not its parent.
Only existing readable bundles are fetched; metadata defaults, private notes and
preservation-system identifiers are not used to fill gaps. A unit with no
descriptive metadata remains sparse until staff catalogue it in Providence.
External links retain their source labels. HTTP(S) URLs become links; other
recorded URI text remains escaped plain text. Empty metadata
columns and fields are omitted; long text uses **Read more / Read less**. Columns
stack on phones. **View finding aid** uses sentence case, ordinary detail-link
styling and a hover underline; `rel="nofollow"` remains. It opens the existing
document viewer, with a direct PDF fallback.
At every archival level, the viewer and direct-PDF fallback target the top-level
public collection's full finding aid. The readable ancestor chain stops below
private administrative groups, and at the configured `landing_page_collection_type`
even when staff can read its administrative parents. Those groups neither appear
in **Part of** nor hide the public collection's finding aid. **Part of** lists only genuine readable
ancestors, never the selected record, and is omitted on public roots. Missing
records, cycles and hierarchy-bundle restrictions fail closed. The page's own
metadata and item scope stay unchanged.
The title header has a right-aligned **Prev / Next / Browse collections** toolbar.
Prev/Next use the visitor's native result context; absent neighbors are omitted.
Their hover tooltips and screen-reader labels say **Go to previous collection**
and **Go to next collection**, distinguishing them from item pagination.
There is no Back button or reserved side column. Controls wrap below the title
when space is limited, and browser Back remains available.
Logged-in full-access staff also get **View in Providence** beneath that toolbar.
It opens the currently viewed collection, series, container or folder in a new
tab, leaving Providence to enforce its own login and permissions. Public accounts
and anonymous visitors receive no editor link.

## Navigation and URL state

Ordinary links, reloads and browser Back retain collection context. Paging,
sorting and Tiles/List stay on `/Detail/collections/<id>` rather than switching
the visitor to a standalone Search page. The page loads its results through
`/CollectionContents/Objects/collection_id/<id>`.
The AJAX response activates its filtered native result context as well as saving
the ordered IDs, so object **Prev / Next** follows those collection results.

| Parameter | Meaning |
| --- | --- |
| `collection_view` | Explicit `flat` or `hierarchy`; otherwise the remembered mode, initially `flat` |
| `view` | `images` for Tiles or `list` |
| `per_page` | Tiles: `9`, `18`, …, `72`; List: `25`, `50`, `100`; `all` within the current view's limit |
| `sort` / `direction` | Configured object sort label and `asc` or `desc` |
| `s` | Nonnegative result offset; zero starts the first page |

For example, with an invented collection ID:

```text
/Detail/collections/42
/Detail/collections/42?collection_view=hierarchy
/Detail/collections/42?collection_view=flat&view=list&per_page=25&sort=Title&direction=asc&s=25
```

Tiles initially use **18 items per page**. The picker beside the count offers
9-item steps up to 72, the equivalent of 24 rows in the desktop three-column grid.
These steps stay the same on mobile and tablet. List view has its own **25-item
default**, with 25, 50 and 100 as its numeric choices.

Choices above the current filtered total are omitted; **All** replaces the exact
total when it is at most 72 for Tiles or 100 for List, including totals that do
not fill the last grid row. Changing the picker updates results immediately and
starts at the first page with the same scope and sorting. Without JavaScript,
an **Apply** button submits the ordinary GET form.

The `tadlDisplayPreferences` cookie remembers Tiles/List, each view's numeric page size and
flat/hierarchy mode for one year, scoped to the current host and application path.
Explicit links override these defaults. **All** applies to the current results;
choosing it remembers **18** for the next Tiles view or **25** for the next List
view. An All request exceeding its view's limit also displays that safe default.
Switching between Tiles and List restores each view's own numeric preference.
Cookies cannot restore a search, change access,
or lift the page-size limit. Missing or invalid stored values use safe defaults.
The media preference remains
in the visitor's cookie/session rather than these URLs, so shared links follow
the recipient's preference. Incoming search expressions, browse keys and facet
state cannot replace the controller's selected collection scope.

The first collection-items page includes the full descriptive metadata. Later
pages omit the description, facts and related-record fields without fetching
their values again. They retain the collection title/identifier, finding aid,
hierarchy controls and navigation, with **About this collection** linking back
to the first page using the same display and sort settings.

The same picker and display preferences apply to full Search/Browse result pages
and the collection index. Six-item search overview previews retain their purpose
as previews rather than becoming paged results.

Collection item cards in Tiles and List show small square, white-on-dark-gray
format tags stacked at the right of their titles, replacing the object identifier
line. On narrow List rows, the stack sits beneath the title and stays right-aligned.
Their 14px text is intentionally smaller than the page's 18px body text. Each
readable original format appears once, such as **PDF**, **TIFF** or **JPEG**, including
accessible secondary representations. Private paired PDFs/JPEGs, deleted
representations, generated previews and download conversions do not add tags.
Items without representation formats start with their title. Tags describe the
attached files; they are not download buttons. The same tags appear in object
Search/Browse results, related-item results, six-item search previews and lightbox
Tiles/List views. Object-detail identifiers and non-object results retain their
existing presentation.

In the hierarchy browser, expanding a left-column container loads its branch in
the right panel and records `#collection-<id>` in browser history. Normal detail
URLs remain available as non-JavaScript and modified-click fallbacks. Sibling
titles default to case-insensitive natural order, so Drawer 2 precedes Drawer 10.

## Digitized media and result counts

Both hierarchy browser columns use **bold titles** for branches with accessible
digitized object media, including media in readable descendants below the right
panel's configured display depth. Titles have no numeric count suffix. A matching
screen-reader description says "contains digitized media" without replacing the
collection's name. Empty branches and branches containing only metadata records
use normal-weight titles. Media attached only to an ancestor does not make its
empty descendants bold.

This indicator uses the same object/representation access, ACL and usable-media
checks as the theme's media filter. It is independent of the visitor's display
preference: **All items** keeps metadata-only branches visible without bolding them;
**Only items with media** omits those branches. Media attached directly to a
collection, rather than to a related object, does not qualify a branch.

Item totals remain in the results toolbar and describe visible catalogue objects:

| Count | Includes descendants? | Uses the media preference? |
| --- | --- | --- |
| Flat result total | Yes, deduplicated across the selected branch | Yes |
| Hierarchy page's item total | No, directly attached objects | Yes |

All result totals respect access permissions. PDF finding aids have no object
counts. They describe every readable archival unit using its recorded extent,
description, dates and other public metadata, including units with no separately
catalogued objects. They do not infer physical contents from browser totals.
See [Finding aids](FINDING_AIDS.md) for field mappings and export details.

## Configuration and implementation

Relevant settings in `conf/collections.conf`:

| Setting | Current value / effect |
| --- | --- |
| `landing_page_collection_type` | `collection`; also defines the base level used for parent links and finding-aid scope. |
| `detail_child_collection_sort` | `ca_collections.preferred_labels.name`; natural ordering is applied by the views. Explicit non-name sorts remain respected. |
| `do_not_display_collection_browser` | `0`; `1` hides the hierarchy browser and its switch. |
| `browser_closed` | `0`; controls initial browser visibility within hierarchy mode. |
| `collapse_levels` | `0`; enables native nested expansion when set. |
| `max_levels` | `4`; limits right-panel rendering, not flat contents, branch media checks or finding aids. |
| `always_link_to_detail` | `1`; native non-linkable type configuration can still override it. |
| `cache_timeout` | Keep `0`; native child-list HTML cache keys omit media preference and access context. |

`helpers/collection_contents.php` traverses scalar collection IDs and parent IDs in
batches of 500, with bulk native type/source/bundle/Pawtucket ACL checks and cycle
protection. The active scope, media-presence and optional count paths do not retain
or load a model per descendant. Private intermediate records prune their branch.
The root/ancestor metadata paths still use independent native models.

The automatically discovered `tadlPlaceContents` theme plugin handles both
`CollectionContents/Objects` and `PlaceContents/Objects`. It reads indexed
relationships and gives native Browse a wildcard plus an intrinsic object-ID
filter, rather than constructing thousands of Lucene OR terms. Empty scopes use
ID zero. Native base criteria, type restrictions, visibility, sorting, exports and
result context remain in force. These scoped results have their own context and
recompute membership/permissions instead of reloading a caller's browse key. Deploy
the complete theme including `plugins/`; a missing plugin fails closed.

`tadlCollectionContentsMediaPresence()` batches memberships, applies the current
usable-media checks, and propagates presence through the scalar parent map.
`tadlCollectionContentsCounts()` is used only by the optional
`always_link_to_detail = 0` policy; it also uses an indexed object-ID filter.
Neither path performs a separate object search for every title.

Collection index, Search and Browse use the same image policy: permitted native
collection media, direct-object images, then readable descendants. A public
secondary image can replace an unavailable primary. Candidate queries return at
most 32 rows per root/keyset window, continuing past inaccessible or unusable
candidates and stopping when an image is found. Media blobs are read only as
needed. Responsive derivatives preserve the selected representation's geometry.

Media-only filtering still checks the complete candidate set before pagination so
counts and navigation stay correct. Its optional native-cache entries contain only
media-descriptor validity, keyed by representation ID, the current SHA-256 descriptor
fingerprint, site and decoder version. Current permissions/membership are never
persisted. Cached validity expires after five minutes; changed media invalidates
it immediately. Without a working cache the native decoder remains the fallback.
Search/Browse result HTML remains uncached.

`helpers/representation_formats.php` fetches scalar original MIME types and,
when permitted, original filenames for only the displayed object IDs. It never
loads full media descriptors for tags. Inventory queries batch up to 500 object
IDs, and the shared visibility helper applies object/representation access,
type/source restrictions, bundles and Pawtucket ACLs. The original MIME type
takes precedence; a filename extension provides a fallback for legacy or unknown
types. Repeated formats are deduplicated and sorted alphabetically. Paging and
native Back restoration use the same displayed-ID scope as the result templates.
Search overviews inventory only their six displayed previews; lightboxes inventory
only their current page. These views share the same format and access rules.

The main integration points are the collection detail view,
`controllers/CollectionContentsController.php`, `helpers/collection_contents.php`,
the hierarchy views/helpers under `views/Collections/`, shared result helpers under
`views/Browse/`, and the theme stylesheets. Collection result responses are private,
noncached and `noindex, follow`. The root crawler policy separately excludes the
result endpoint; see [Crawler policy](CRAWLER_POLICY.md).

## Verification and rollout

Focused synthetic checks, run from the theme repository:

```sh
php tests/collection_contents_test.php
php tests/collection_hierarchy_test.php
php tests/collection_detail_scripts_test.php
php tests/result_context_heading_test.php
php tests/representation_formats_test.php
php tests/finding_aid_test.php
```

The full portable suite and requirements are in [AGENTS.md](../AGENTS.md) and the
[handoff](CODEX_HANDOFF.md#local-verification-on-the-laptop). These tests do not
bootstrap a production catalogue. Synthetic desktop/phone previews verified the
mode switch, page/sort state, AJAX expansion, media emphasis and finding-aid hover
style. Native production integration and performance remain deployment-time checks.

Deploy a coherent theme revision, including the controller, helper, views and
stylesheets together. No new dependency, database schema change or search-index
rebuild is required for these collection presentation changes. Normal browser
Reload picks up changed CSS/JS through the existing content versions.

After an authorized deployment, check a flat collection, a deeply nested branch,
an overlapping-membership example and an empty branch. In both media preferences,
verify result counts, media emphasis, access restrictions, paging, sorting,
Tiles/List, Back and the two mode-switch links. Check that the finding aid retains each unit's recorded extent
and description without adding catalogue-object counts.
Check a series with directly attached items and empty boxes/folders underneath it:
only the series should have a bold title and media description; empty descendants
must remain normal weight without a media description.
Check normal/hover link styling and narrow-screen layouts. Keep native collection
HTML and whole-page caches disabled; source pushes alone do not deploy anything.
