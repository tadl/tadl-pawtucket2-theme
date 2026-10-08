# Collection pages

Collection details default to a flat list of the selected collection's objects
and every readable descendant's objects. The catalogue hierarchy is preserved;
this is a presentation choice. An object linked to several nodes appears once
in the flat results. Selecting a subcollection includes only that branch, not
its ancestors, siblings or unrelated collections.

## Flat and hierarchy views

When readable children exist and the browser is enabled in configuration,
**View collection hierarchy** appears beneath **Download finding aid**. It opens
the hierarchy browser and restores directly attached items on the collection page.
**View all collection items** returns to the flat list. Leaf collections omit the switch.
Both view-switch links have an eye icon; the finding-aid download keeps its document icon.

| Behavior | Flat view | Hierarchy view |
| --- | --- | --- |
| Item scope | Selected collection and readable descendants | Objects directly attached to the selected collection |
| Hierarchy browser | Hidden | Shown when enabled and readable children exist |
| Result controls | Options, sorting, Tiles/List and paging | Same controls for multiple directly attached objects; a single qualifying object uses the existing inline preview |
| Finding-aid scope | Selected collection and readable descendants | Same scope as flat view |

Both modes use the current **Only items with media / All items** preference and
native record, bundle and Pawtucket ACL permissions. Unavailable ancestors prune
their whole branch. Distinct objects are not merged because their names or
identifiers happen to match. The switch retains sort/display state and resets
paging to the first page of the new scope.

The overview groups description/source and scope/content separately from creators,
dates, extent, language, subjects, rights and related records. Empty metadata
columns and fields are omitted; long text uses **Read more / Read less**. Columns
stack on phones. **Download finding aid** uses sentence case, ordinary detail-link
styling and a hover underline; the file icon and `rel="nofollow"` remain.

## Navigation and URL state

Ordinary links, reloads and browser Back retain the collection overview. Paging,
sorting and Tiles/List stay on `/Detail/collections/<id>` rather than switching
the visitor to a standalone Search page. The page loads its results through
`/CollectionContents/Objects/collection_id/<id>`.

| Parameter | Meaning |
| --- | --- |
| `collection_view` | `flat` by default; exact `hierarchy` selects hierarchy mode |
| `view` | `images` for Tiles or `list` |
| `sort` / `direction` | Configured object sort label and `asc` or `desc` |
| `s` | Nonnegative result offset; zero starts the first page |

For example, with an invented collection ID:

```text
/Detail/collections/42
/Detail/collections/42?collection_view=hierarchy
/Detail/collections/42?collection_view=flat&view=list&sort=Title&direction=asc&s=24
```

Tiles use nine results per page; List uses 24. Missing/invalid mode defaults to
flat, and unsupported display state is normalized. The media preference remains
in the visitor's cookie/session rather than these URLs, so shared links follow
the recipient's preference. Incoming search expressions, browse keys and facet
state cannot replace the controller's selected collection scope.

In the hierarchy browser, expanding a left-column container loads its branch in
the right panel and records `#collection-<id>` in browser history. Normal detail
URLs remain available as non-JavaScript and modified-click fallbacks. Sibling
titles default to case-insensitive natural order, so Drawer 2 precedes Drawer 10.

## Counts

Both browser columns append an inline numeric suffix, such as **Series (42)**,
inside the title link or nonlinked title. There is no forced line break or
`records` label. Each total includes unique visible objects throughout that
branch, even below the right panel's configured display depth. Visible empty
branches show **(0)**. Shared objects count once in each ancestor total; sibling
totals can overlap and should not be summed to infer the parent total.

Browser counts and PDF finding-aid counts serve different purposes:

| Count | Includes descendants? | Uses the media preference? |
| --- | --- | --- |
| Flat result total | Yes, deduplicated across the selected branch | Yes |
| Hierarchy page's item total | No, directly attached objects | Yes |
| Inline hierarchy title count | Yes, deduplicated within that title's branch | Yes |
| Finding-aid overall total | Yes, deduplicated across the selected branch | No |
| Finding-aid count beside each node | No, directly linked objects | No |

All counts still respect access permissions. The finding aid lists every readable
descendant, including empty subcollections, and omits individual object entries.
See [Finding aids](FINDING_AIDS.md) for field mappings and export details.

## Configuration and implementation

Relevant settings in `conf/collections.conf`:

| Setting | Current value / effect |
| --- | --- |
| `detail_child_collection_sort` | `ca_collections.preferred_labels.name`; natural ordering is applied by the views. Explicit non-name sorts remain respected. |
| `do_not_display_collection_browser` | `0`; `1` hides the hierarchy browser and its switch. |
| `browser_closed` | `0`; controls initial browser visibility within hierarchy mode. |
| `collapse_levels` | `0`; enables native nested expansion when set. |
| `max_levels` | `4`; limits right-panel rendering, not flat contents, branch counts or finding aids. |
| `always_link_to_detail` | `1`; native non-linkable type configuration can still override it. |
| `cache_timeout` | Keep `0`; native child-list HTML cache keys omit media preference and access context. |

`helpers/collection_contents.php` traverses readable collection models in batches
of 500 parents, guards cycles and builds numeric collection terms for native object
search. Retained nodes use independent, uncached model instances: native
`Datamodel::getInstance(..., true)` returns the same mutable model, so loading a
later child would overwrite earlier nodes' IDs, parents and access state. Counting
shares one fresh native object browse per hierarchy response,
applies media filtering before counting, and batches memberships by 500 collections.
Objects are not individually loaded for browser rollups or searched separately
for every title. Native object search still handles visibility and deduplication.

Collection browse eligibility and thumbnails use `helpers/record_access.php` to
check native type/source/bundle permissions and Pawtucket-only ACLs in bulk,
including unreadable intermediate collections. Thumbnail selection prefers
direct collection media, then direct-object images, then readable descendants.
Within an object, a public secondary image can replace an unavailable primary.
Queries fetch scalar candidate IDs first and read media descriptors only until
each card has a usable image. Search/browse Tiles and List HTML is rendered fresh;
the theme does not save or reuse shared result HTML even when a native cache
backend treats a zero lifetime as persistent.

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
php tests/finding_aid_test.php
```

The full portable suite and requirements are in [AGENTS.md](../AGENTS.md) and the
[handoff](CODEX_HANDOFF.md#local-verification-on-the-laptop). These tests do not
bootstrap a production catalogue. Synthetic desktop/phone previews verified the
mode switch, page/sort state, AJAX expansion, inline counts and finding-aid hover
style. Native production integration and performance remain deployment-time checks.

Deploy a coherent theme revision, including the controller, helper, views and
stylesheets together. No new dependency, database schema change or search-index
rebuild is required for these collection presentation changes. Normal browser
Reload picks up changed CSS/JS through the existing content versions.

After an authorized deployment, check a flat collection, a deeply nested branch,
an overlapping-membership example and an empty branch. In both media preferences,
verify counts, access restrictions, paging, sorting, Tiles/List, Back and the two
mode-switch links. Compare the finding aid using its distinct count semantics.
Check a series with directly attached items and empty boxes/folders underneath it:
only the series should count those items; empty descendants must stay at **(0)**.
Check normal/hover link styling and narrow-screen layouts. Keep native collection
HTML and whole-page caches disabled; source pushes alone do not deploy anything.
