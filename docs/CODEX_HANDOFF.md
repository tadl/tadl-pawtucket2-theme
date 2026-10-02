# TADL Pawtucket handoff

Updated: 2026-10-02. This document carries the recent coding context to another
Codex chat or workstation. Read it alongside `AGENTS.md` and current source.

## Checkpoint and product direction

The implementation checkpoint is `e3ae5b4` on `main`, pushed to
`git@github.com:tadl/tadl-pawtucket2-theme.git`. The worktree was clean before
adding these documents. Documentation commits follow that checkpoint; use
`git status` and `git log` for the current state.

This is the theme for the public TADL Local History Collection at
https://archives.tadl.org/. The user is now adding more object media in
Providence and iterating on Pawtucket presentation. Preserve thumbnail-rich
results, clear record context, ordinary navigation, and a generous desktop
media viewer. Use existing TADL visual conventions and accessible controls.

Changes belong here rather than in upstream CollectiveAccess. The surrounding
workspace has reference checkouts of Pawtucket and Providence at local tag
`2.0.10`; that is a local source fact, not a verified current production version.

## Completed work and useful commits

| Commit | Result |
| --- | --- |
| `0ca1b0c` | Larger object-detail media layout on wide desktops |
| `3434c70` | Representation titles hidden on object details |
| `7c4cc78` | Collection on its own title line; arrow and object title below |
| `2f36875` | Alphabetical collection entry pages and ordinary linked index pagination |
| `177bd66` | Multisearch carousels replaced by grouped previews and full-results links |
| `8723780`, `2301772` | Site-wide media preference, then cookie persistence and clean URLs |
| `37db355` | Featured-gallery sidebar button contrast fixed |
| `687e5a4` | Detail result loaders fixed; dynamic HTML safely serialized into JavaScript |
| `301e0ab`, `303e2bd` | Video-first object details with primary still covers and single-click playback |
| `bb4731f` | Clear person/organization/place/event details and contextual related-result headings |
| `d7ac779` | Active filter chips have visible, labeled remove controls |
| `bf26b59` | Featured gallery shown once; remaining galleries have a heading |
| `847e37f` | Gallery description/thumbnails aligned; object viewer has larger side arrows and counter |
| `e3ae5b4` | Related objects labeled once with identifiers; TGM terms link to their definitions |

These are source changes. No deployment was performed by Codex for the latest
implementation. The user deploys independently; confirm live state separately
before calling an issue deployed or still broken.

## Source map

| Area | Main entrypoints |
| --- | --- |
| Header, footer and toggle | `views/pageFormat/pageHeader.php`, `pageFooter.php`, `media_preference_toggle.php` |
| Preference endpoint/persistence | `controllers/MediaPreferenceController.php`, `helpers/media_preferences.php` |
| Eligibility and result adapters | `helpers/media_filter_helpers.php` |
| Collections | `conf/collections.conf`, `conf/browse.conf`, `views/Collections/`, `views/Browse/collection_thumbnail_helpers.php` |
| Browse/results/pagers | `views/Browse/browse_results_html.php`, `tadl_result_helpers.php`, `tadl_result_context_helpers.php`, image/list/refine subviews |
| Search previews | `conf/search.conf`, `views/Search/multisearch_results_html.php`, `tadl_search_results_subview_html.php` |
| Object media | `helpers/object_detail_media.php`, `views/bundles/representation_viewer_html.php` |
| Image downloads | `helpers/image_downloads.php`, `controllers/ImageDownloadController.php`, `views/Details/image_download_binary.php`, `views/mediaViewers/viewerWrapper.php` |
| Object metadata | `views/Details/ca_objects_default_html.php`, `detail_field_helpers.php` |
| Collection details | `views/Details/ca_collections_default_html.php` |
| Authority details | `views/Details/authority_detail_helpers.php`, `authority_detail_html.php`, entity/place/occurrence detail templates |
| Galleries | `views/Gallery/index_html.php`, `set_info_html.php`, `detail_html.php`, `set_item_rep_html.php`, `set_item_info_html.php` |
| Styling | `assets/pawtucket/css/theme.css`, with the existing `main.css` foundation |
| Regression checks | `tests/*_test.php` |

Resolve abbreviated view filenames relative to the directory named in their row.
Use `rg` to find the relevant helper or CSS selector rather than depending on old
line numbers. Native application behavior can be inspected in the nearby
`pawtucket2/app/` reference tree without editing it.

## Behavior and implementation contracts

### Typography

- Interior pages have an **18px minimum** for readable text, including object
  descriptions/metadata, result titles/captions, galleries, navigation, forms,
  download menus and footer text. Larger headings retain their existing sizes.
- `pageHeader.php` adds `tadl-interior` to the body outside the `Front`
  controller. This sets inherited `--tadl-text-min: 18px`; small sizes in the
  theme's `main.css` and `theme.css` use `max()` with that floor. Bootstrap
  controls/small text have scoped overrides, and object units use 1.6 line height.
  Keep the root rem scale unchanged and retain the landing page's established
  sizes. The zero fallback preserves its smaller supporting text.
- Desktop/mobile synthetic previews checked object descriptions and other
  representative text at 18px, with no horizontal overflow at 390px. Landing
  typography samples matched their previous computed font sizes and line heights.
  PDF export templates keep their independent print styles.

### Media preference

- **Only items with media** is the default; **All items** is a browser preference.
  It currently lives beneath the header search box.
- `tadlMediaPreference` is a host-only, HttpOnly, SameSite=Lax cookie lasting
  one year, scoped to the application path and Secure on HTTPS. Session state
  is a fallback. Ordinary links do not append `media=only` or `media=all`.
- The setting endpoint requires POST and a valid CSRF token; it validates the
  mode and redirects only to a safe path within this installation.
- Objects and collection lists are filtered before theme counts and pagination.
  A collection qualifies through accessible object media in itself or any
  descendant. Images, video, audio, PDFs and supported embedded media qualify;
  private, deleted or metadata-only representations do not.
- Preserve native record access and ACL filtering. This preference is not
  authorization, and direct object/collection detail URLs remain accessible.
  People, places and other authority result records retain their native behavior.
- Related result contexts must save the displayed IDs for Previous/Next.
  The preference is visitor-specific; a shared URL uses its recipient's setting.
- Native facet counts include all records, so media-only pages omit those counts.
  Downloads use native unfiltered results and are available in All items mode.
- `conf/collections.conf` keeps `cache_timeout = 0`; whole-page content caching
  is disabled in `conf/app.conf`. Native cache keys omit this setting/access
  context. Do not re-enable caches without addressing that limitation.

### Results and authority pages

- Collection entry lists sort by name. Do not assume every child hierarchy or
  export sort is alphabetical: those configuration settings are separate.
- Collection index pagination uses normal links with page/view state. Browse
  retains native keys, criteria, sorting and result contexts; do not strip them
  just because URLs look complicated. Browser Back and direct page links matter.
- Collection detail's initial object search is an AJAX response, which normally
  omits the full results header. Its loader now requests `tadl_collection_controls=1`
  so object-search AJAX includes a compact **Tiles/List** control and top pager.
  The normal bottom pager remains. Both use the media-filtered count and native
  key, view, sort, direction and page sizes; their links open full search pages.
  The flag affects only this explicit AJAX object-search response, avoiding
  duplicate headers on full pages and other AJAX result blocks. Loader and
  rendered-header regressions cover the first page and both views. A synthetic
  browser preview verified page 2, List, Back and no overflow at 390px.
- Multisearch has up to six previews per category, sensible text layouts for
  non-image records, counts and ordinary links to full results. It is not a carousel.
- Result headings identify search/browse context and authority names. Active
  criteria are removable with visible controls and accessible labels.
- The shared search/browse **Options** menu omits Lightbox bulk-add and selection
  actions. Sorting, Start Over and eligible downloads remain available. This is
  a menu change; per-item Lightbox controls and the account Lightbox remain intact.
- Object browse, regular search and advanced search offer **Date**, using the
  same `ca_objects.date.dates_value` field displayed on object details and native
  date-range sorting. Date defaults to ascending; the normal descending control
  remains available. Existing default sort choices are unchanged.
- Entity types distinguish people and organizations; places and events have
  explicit headings. The shared authority layout gives related objects priority
  and collapses long relationship groups instead of leaving tall empty columns.
- Detail loaders use native URLs, serialized JavaScript values, and useful
  empty/error states. Do not insert HTML returned by `caBusyIndicatorIcon()`
  directly into a quoted JavaScript string.

### Object media and gallery navigation

- Browse/search use the primary representation for their thumbnail.
- Without an explicit `representation_id`, object detail selects the first
  accessible, rendered playable video when one exists. A valid attached/rendered
  requested representation takes precedence; an invalid explicit ID falls back
  to slide 0. Most records have no video and retain normal behavior.
- An accessible primary still image can cover the video until one click starts
  playback. Keep only one central play control, keyboard activation, and the
  native player fallback. Do not assume every moving-image record has a still.
- Selection/poster decisions use accessible representations and actual rendered
  playback media; metadata type alone is insufficient. Preserve native scripts,
  slide order, wrapper IDs, viewer callbacks and initialization.
- Multi-representation objects use side arrows and a position counter. Changing
  slides stops active media; thumbnails remain functional. Avoid colliding with
  native callback/count variable names.
- Object image actions sit in a left column below **Previous/Back**, outside the
  image. On phones they appear below navigation and above the image; opening
  Download pushes the image down. Gallery image actions sit below their image.
  Keep native **Open media view**/compare callbacks; omit the image's
  Lightbox action and replace its original-download link with one native HTML
  disclosure menu. Video toolbar behavior and account Lightbox remain unchanged.
- `views/Details/image_actions_script.php` moves the current toolbar node into
  `tadlObjectMediaActions`, preserving callbacks. The representation bundle calls
  it after each slide change so downloads follow the selected image, and clears
  image actions on video slides. Repeated ready callbacks preserve single-image
  controls. Without JavaScript the toolbar stays below the image, outside it.
- Image downloads use **TIFF (to print)** when the original is TIFF, plus
  **JPG (to share)** and **PDF**. The enlarged TileViewer has the same menu; preserve both
  `id` and native `object_id` navigation URLs and replace its native download
  form while keeping viewer navigation/close controls.
- JPG uses the original image dimensions, never the display-size derivative.
  Existing JPEG originals stream unchanged. Other image originals convert on
  demand through CollectiveAccess `Media` to quality-90 JPEG with a white
  background, without scaling. Native ImageMagick selects the first frame/page;
  TIFF downloads preserve the complete original file. No TIFF is synthesized for
  other source formats. Originals and permanent derivatives are unchanged.
- PDF embeds that full-resolution JPEG on one letter-size page, with half-inch
  margins and orientation matching the image. It contains the selected image,
  rather than an object metadata report; multi-page TIFFs use the same first
  frame/page as JPG. Pawtucket's bundled `Dompdf` dependency renders the PDF with
  remote resources, PHP and JavaScript disabled. Its scratch files use the same
  private request-local workspace and shutdown cleanup as image conversions.
- The download endpoint rechecks object/representation access, ACLs, attachment,
  bundle visibility, the native download policy and permission for `original`.
  Private temporary conversions are removed at request shutdown. Byte MIME and
  converted JPEG dimensions are validated; a failed conversion returns 503,
  never another format disguised with a `.jpg` suffix. Production requires PHP
  fileinfo and a native image processor capable of reading originals/writing JPEG.
- The object title puts its collection on the first line and the arrow/object
  label below. Representation filenames/titles are hidden; intentional media
  captions remain a separate field.
- The gallery index features the first gallery once and lists the others under
  **More galleries**. Gallery detail keeps native AJAX item partials and direct
  thumbnail-link fallbacks. Thumbnails align beneath the media column; the gallery
  description belongs beneath the information column.

### Related-object and vocabulary fixes

The related-object template already establishes the current related record with
`<unit relativeTo="ca_objects.related">`. Its inner label must therefore use
`^ca_objects.preferred_labels.name`, not
`^ca_objects.related.preferred_labels.name` (which traverses relationships again).
The latter produced multiple title strings inside each link. Keep distinct
target IDs and relationship types; identical titles do not prove duplicate records.
The links now include each record's own identifier when present, with
`%htmlEncode=1` on the identifier.

TGM values use the native LCSH attribute renderer. Its parse behavior can append
a duplicate bracketed label and leave a malformed stored URI. Native `asHTML`
does not produce the right TGM link. `tadlObjectThesaurusTerms()` instead reads
native `text` and `n` values with `returnWithStructure`, yielding
`[objectID][attributeID]['lctgm']`. Pair them by attribute ID, enforce bundle
permissions, escape the label and build a fixed
`https://id.loc.gov/vocabulary/graphicMaterials/` link only for `tgm` plus digits.
Invalid/missing IDs stay plain text. Do not use the stored URI as an arbitrary
link, flatten the value arrays, patch core, or repair catalog data as a side effect.

## Subjects

Catalog subjects are related `ca_list_items` vocabulary records through
`ca_objects_x_vocabulary_terms`, as reflected in the migration mapping. They
are separate from the optional `lcsh_terms` and `lctgm` attributes.

- Object details show **Subjects** directly after Description. Each term links
  to a fresh `Browse/subjects` with the native `term_facet` criterion. This
  replaces the lower **Related Terms** block. Labels are escaped, duplicate IDs
  are removed, and native record access, bundle permissions and Pawtucket ACLs
  are enforced before display.
- Objects advanced search adds **Subjects** using the existing `term` search
  access point. The reference `app/conf/search_indexing.conf` maps it to both
  `ca_list_item_labels.name_singular` and `name_plural`; no index configuration
  or catalog data is changed. The separate Library of Congress field remains.
- **Browse → Subjects** returns objects with the native vocabulary facet. The
  `tadl_subjects` facet group limits this entry to subjects. `#!merge` extends
  the native facet configuration, preserving other facets. Subjects also appear
  as a filter on ordinary object browse and search whenever available.
- The subject list starts visible, appears above results on phones, and retains
  native expansion, criterion links, paging and media filtering. Text follows
  the interior 18px baseline.

Ten standalone suites and PHP lint passed on PHP 8.5.10. The native reference
configuration parser verified preservation of all eight other object facets.
A synthetic desktop/mobile browser preview verified visible subject links,
list expansion, 18px text and no horizontal overflow at 390px. Production
subject relationships, indexed search hits and installed configuration remain
unverified until deployment and a live check; source changes do not deploy them.

## Local verification on the laptop

The ten committed tests are portable and use synthetic boundaries. They do not
bootstrap CollectiveAccess or need its database; the media test uses SQLite in
memory. No Composer/npm install is needed for these standalone checks.

Requirements: PHP 8+ CLI with DOM, PDO SQLite, JSON and fileinfo; `proc_open` enabled;
Node.js available on PATH. The theme header additionally needs mbstring, though
these standalone suites do not. The original handoff checks passed on PHP 8.5.7 and
Node 24.13.0. Those are observed workstation versions, not a required production
upgrade.

From the theme repository:

```sh
php --version
node --version
php -r 'foreach (["dom", "pdo_sqlite", "json", "mbstring", "fileinfo"] as $extension) { echo $extension, ": ", extension_loaded($extension) ? "yes" : "no", PHP_EOL; }'
(
  for test_file in tests/*_test.php; do
    php "$test_file" || exit 1
  done
)
```

| Test | Coverage |
| --- | --- |
| `tests/media_preferences_test.php` | Eligibility SQL, filtered result adapter, result rendering |
| `tests/media_preference_controller_test.php` | Cookie options, POST/CSRF, redirect validation |
| `tests/collection_detail_scripts_test.php` | Collection detail loader JavaScript |
| `tests/object_detail_media_test.php` | Media selection, viewer controls and callbacks |
| `tests/object_detail_video_poster_test.php` | Covers, playback, player fallback and access |
| `tests/authority_detail_test.php` | Authority types/layout, related groups, access and loaders |
| `tests/result_context_heading_test.php` | Results context, headings, removable criteria and Subjects browse/refine rendering |
| `tests/object_detail_metadata_test.php` | Structured TGM pairing, safe links, escaping and permissions |
| `tests/image_download_test.php` | Toolbar/bundle/overlay menus, current image action relocation, download policy/ACL/attachment checks, TIFF/JPEG/PDF bytes, conversion validation and failure handling |
| `tests/subjects_test.php` | Subject relationship rendering, escaping, native browse links, bundle access and Pawtucket ACL filtering |

All eight original suites passed during handoff preparation, as did PHP lint.
The nine suites passed after the image-download change on PHP 8.5.10 and Node
24.19.0. Desktop/mobile synthetic browser preview verified enlarged controls,
mouse/keyboard disclosure and wrapping. Production conversion remains unverified.
For future changes, begin with the relevant tests and lint changed files:

```sh
php -l path/to/changed_file.php
git diff --check
```

Optional full PHP lint, with ripgrep installed:

```sh
rg --files -g '*.php' -0 | xargs -0 -n 1 php -l
```

The JavaScript suites accept `TADL_TEST_NODE` if Node is not named `node` on PATH.
Test-specific helper/bundle overrides are for targeted checks; normal runs use
the files in this checkout.

The image-download suite uses a synthetic native `Media` boundary by default.
With ImageMagick already installed, optionally exercise actual TIFF/PNG-to-JPEG
conversion through that boundary:

```sh
TADL_TEST_MAGICK=/path/to/magick php tests/image_download_test.php
```

This checks real conversion bytes/dimensions,
but does not bootstrap the installed CollectiveAccess processor or database.

Optionally use an existing Pawtucket Composer autoloader to test actual bundled
Dompdf output, without bootstrapping the application or connecting to a database:

```sh
TADL_TEST_COMPOSER_AUTOLOAD=/path/to/pawtucket/vendor/autoload.php \
TADL_TEST_MAGICK=/path/to/magick php tests/image_download_test.php
```

This path verifies a single PDF page, letter paper, and embedded original pixel
dimensions. The normal suite uses a synthetic PDF renderer and also checks
renderer failures. The optional suite ignores PHP 8.5 deprecations from `vendor/`
while keeping theme warnings strict. Real Dompdf 2.0.8/ImageMagick checks passed
locally; Poppler inspection and rendering confirmed one page with the unchanged
JPEG pixel dimensions. Desktop, tablet and 390px mobile previews verified controls
outside the image, menu expansion, no horizontal overflow, and download URLs
updating when the selected representation changes. Production remains unverified.

Full browser QA still needs a configured local application with a safe database
and media setup, or an intentionally constructed synthetic local preview. Merely
copying the theme/reference tree does not supply that environment. Inspect the
live site read-only when useful, but do not treat it as a deployed preview of
unpublished source. Old `/tmp` fixtures, screenshots and chat state are not
included in a workspace rsync and are not required by the committed tests.

## Deferred decisions

- The user may move the media toggle out of the header later, or restrict
  viewing records without media to logged-in researchers. That permission and
  account-preference design is not implemented. It needs server-side enforcement;
  hiding the toggle or trusting its cookie is not access control.
- Login/registration UI and mechanism were explicitly deferred. Native lightbox
  functionality exists, but do not redesign authentication or add account
  capabilities merely to finish a layout task.
- Continue iterative responsive/browser QA as real media is added. Do not
  collapse distinct related records just because their titles match.
- Production deployment and final live smoke checks are separate user-controlled
  steps. Review the target and current configuration before any authorized deploy.

No further feature implementation is requested by this handoff itself.
