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
| Home-page writing | `views/Front/front_page_html.php`, `assets/pawtucket/js/recent-writing.js`; companion TADLFeeds `/local_history_posts.json` |
| Object media | `helpers/object_detail_media.php`, `views/bundles/representation_viewer_html.php` |
| Image downloads | `helpers/image_downloads.php`, `controllers/ImageDownloadController.php`, `views/Details/image_download_binary.php`, `views/mediaViewers/viewerWrapper.php`, `assets/pawtucket/js/image-downloads.js` |
| Enlarged viewer help | `views/Details/viewer_help_html.php` |
| Object metadata | `views/Details/ca_objects_default_html.php`, `detail_field_helpers.php` |
| Collection details | `views/Details/ca_collections_default_html.php` |
| Authority details | `views/Details/authority_detail_helpers.php`, `authority_detail_html.php`, entity/place/occurrence detail templates |
| Galleries | `views/Gallery/index_html.php`, `set_info_html.php`, `detail_html.php`, `set_item_rep_html.php`, `set_item_info_html.php` |
| Styling | `assets/pawtucket/css/theme.css`, with the existing `main.css` foundation |
| Asset cache versions | `helpers/asset_versions.php`, shared header and standalone Lightbox presentation |
| Collection finding aids | `controllers/CollectionFindingAidController.php`, `helpers/finding_aid.php`, `conf/finding_aid.conf`, PDF/binary detail views; `docs/FINDING_AIDS.md` |
| Regression checks | `tests/*_test.php` |

Resolve abbreviated view filenames relative to the directory named in their row.
Use `rg` to find the relevant helper or CSS selector rather than depending on old
line numbers. Native application behavior can be inspected in the nearby
`pawtucket2/app/` reference tree without editing it.

## Behavior and implementation contracts

### Collection finding aids

Collection details now offer **Download Finding Aid** for the selected collection,
with populated collection metadata and unique accessible objects from it and its
readable descendants. Inventory includes objects without media, independent of
the header preference, and preserves all record, bundle and Pawtucket ACL checks.
There is no native relationship cap or shared PDF cache. Recorded home/related
storage locations are shown only when readable; they are not asserted to be
current physical locations. The PDF uses the existing Dompdf dependency with
remote resources/PHP/JS disabled. See `docs/FINDING_AIDS.md` for field mappings,
source boundaries, real-render verification and pending archives-team decisions.
The theme endpoint is `/CollectionFindingAid/Download/collection_id/<id>`;
`FindingAid` is a reserved bundled-plugin prefix in native URL parsing. The renamed
controller and collection link must be deployed together. Optional native
dispatcher verification is documented in `docs/FINDING_AIDS.md`.

### Asset cache versions

- `tadlAssetLoadHTML()` wraps the native asset loader in the shared page header
  and standalone Lightbox presentation. Each readable local theme CSS/JS URL gets
  `v=` plus the first 16 hexadecimal characters of its SHA-256 content hash.
- Changed contents automatically produce a new URL on the next page render,
  including when rsync preserves modification times. Normal browser Reload is
  sufficient after deployment. No manual version bump, asset build step, core
  patch or permanent browser-cache disabling is needed.
- Unchanged assets keep stable URLs. Hashes are computed once per referenced
  file per render, without a persistent hash cache that could become stale.
  Native priorities, configured `asset_suffix`, query parameters and fragments
  remain intact; application/external assets, media, fonts and inline code are
  untouched. Missing/unreadable assets retain native output.
- All twelve standalone suites and changed PHP lint passed. The local reference
  `AssetLoadManager` also rendered the versioned tags with synthetic configuration.
  A synthetic browser preview served CSS with a one-year immutable cache: normal
  Reload loaded a changed stylesheet with preserved timestamps, while the
  unchanged stylesheet retained its URL. Production behavior remains a
  deployment-time check.

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
- Shared Tiles results use a responsive grid with equal-height cards within each
  row. Long titles remain fully visible without allowing later cards to float
  into gaps. Each AJAX/infinite-scroll page owns its grid; controls and pagination
  remain outside it. Desktop/tablet/phone columns remain three/two/one, with two
  columns in the narrower authority layout at 992–1199px.
- Collection details omit the rectangular Back/Previous/Next blocks on desktop
  and phones, and the main column uses the full available width. Collection
  hierarchy links, result pagination and browser Back remain available.
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
- Object pages omit the rectangular **Back/Previous/Next** navigation blocks on
  desktop and mobile. Media-switching arrows/counter and thumbnail controls remain.
  The desktop layout has no empty right navigation column.
- Object image and PDF actions sit in a centered row below the media/counter, before
  annotations and thumbnails, matching gallery actions below their image.
  The former left action column is removed so desktop media can use that width.
  Download menus overlay the content below their buttons without changing the
  action-row height or thumbnail positions, on desktop and phones.
  Keep native **Media viewer**/compare callbacks; omit the image's
  Lightbox action and replace its original-download link with one native HTML
  disclosure menu. Video toolbar behavior and account Lightbox remain unchanged.
  PDF previews use the same visible **Media viewer** button and a **Download PDF**
  link to the native original-download endpoint, only when the native toolbar
  supplies that link. The PDF viewer and complete document stay native; PDFs do
  not use the single-image JPG/PDF conversion endpoint. Previously PDF preview
  actions retained the hidden native toolbar because only images were normalized.
- **Media viewer** and **Download** share the same regular font and black text.
  The viewer's visible label, accessible name and tooltip use the shorter label.
- `views/Details/image_actions_script.php` moves the current toolbar node into
  `tadlObjectMediaActions`, preserving callbacks. It excludes controls already
  in that target when finding a new slide's toolbar. The representation bundle calls
  it after each slide change so downloads follow the selected image, and clears
  image actions on video slides. Repeated ready callbacks preserve single-image
  controls. Without JavaScript the toolbar stays below the image, outside it.
- Image downloads use **TIFF (to print)** when the original is TIFF, plus
  **JPG (to share)** and **PDF**. The enlarged TileViewer has the same menu; preserve both
  `id` and native `object_id` navigation URLs and replace its native download
  form while keeping viewer navigation/close controls.
- The enlarged viewer uses an accessible Download icon immediately above Rotate
  in the native TileViewer control column. The wrapper moves the existing menu
  after native initialization; normal object/gallery page buttons stay labeled.
  Object/gallery image top bars show the currently viewed object's preferred
  title and identifier, rather than its representation label/filename. Long
  titles use a single-line ellipsis with the complete text in a hover title;
  the identifier, representation navigation and Close retain their space. Each
  overlay request resolves its current object, including both ID URL forms.
  Its tooltip uses the native jQuery UI tooltip styling and positioning. Fixed
  padding reserves its toolbar space so opening the menu cannot shift Rotate,
  Fit or Help. The zoom buttons have opaque dark squares, centered on the slider,
  with visible white symbols even when native handlers change their opacity.
  The slider track is inset from both buttons so its handle clears them at the
  minimum and maximum zoom positions.
  `conf/assets.conf` loads `image-downloads.js` site-wide. Its delegated capture
  handler closes open download menus on outside clicks, including canvas clicks
  and AJAX-loaded content. Escape closes menus before the viewer; Tab remains a
  focus key within download menus rather than invoking TileViewer's Tab shortcut.
- Native TileViewer zoom links existed but their Font Awesome 4 icon names did
  not render under Font Awesome 5. Scoped CSS makes the native +/− controls visible,
  restores Pan/Overview icons and keeps the zoom slider within narrow viewports.
  Help describes public image navigation/download controls and actual shortcuts;
  it omits annotation-editing tools unavailable in the public viewer.
- JPG uses the original image dimensions, never the display-size derivative.
  BMP originals remain eligible for viewing and JPG/PDF exports, including the
  `image/bmp`, `image/x-bmp` and `image/x-ms-bmp` metadata aliases. Synthetic BMP
  bytes and real ImageMagick conversion are covered by the image-download suite.
  No representation is automatically hidden by file type.
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
  Object details omit the separate **Download as PDF** metadata-report link
  below the viewer; PDF is offered through the image download menu. The lower
  tools container appears only when Comments/Tags or Share is enabled.
  Collection PDF links remain available.
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
- Gallery detail images retain their aspect ratio with a 650px height limit.
  The image stage owns the centered side arrows; its separate action row follows
  below. The viewer and open Download menu grow in normal flow, keeping controls
  clear of the thumbnails on desktop and phones.

### Related-object and vocabulary fixes

Detail field headings render only alongside populated display values. The shared
field helper treats HTML-only markup, encoded nonbreaking spaces and invisible
Unicode whitespace as empty; numeric `0` remains populated. Relationship headings
on object, collection and gallery records use the same rendered-value check,
with native access filtering preserved. Authority metadata uses the shared helper.
Relationship labels across Detail pages follow the linked record name in
parentheses, with a separating space and natural wrapping. The shared authority
helper/CSS applies this to people, organizations, places and events, including
collapsed relationship lists; absent roles produce no empty parentheses. Link
and role text use the surrounding metadata size and normal weight, with the role
inheriting the metadata text color. Native directional names and access checks
remain unchanged. Synthetic desktop and 390px browser previews verified inline
roles, matching 18px/normal-weight text and natural wrapping without overflow.
Authority sidebars also use the shared Detail metadata styling: uppercase dark
field/relationship headings, normal weight, 18px values, 12px field spacing and
the same muted links as object records. The redundant visible About heading is
replaced by the section's accessible name. Desktop sidebars have no extra left
border/padding, and relationships have no individual row separators; the mobile
section separator and expandable relationship groups remain.
Individual entities (the `ind` type and its descendants) show **Occupation** with
each optional occupation date in parentheses. Native structured `occupation`
attributes preserve name/date pairing; empty names are skipped and the bundle's
read permission, current record access, display labels and no-default option
remain enforced. **Birth date** and **Death date** read populated leaves from
`individual_dates`: current `individual_dates_birth`/`individual_dates_death`,
with older `individual_birthdate`/`individual_deathdate` as fallbacks. Do not
read the entire dates container: its delimiter-only value can be `;`, which
looks populated to the general rich-text helper. Generic `date.dates_value`
remains supported as **Dates**. Empty dates produce neither headings nor
punctuation. The authority suite covers these fields, paired occupations,
escaping, permissions, individual subtypes and older date aliases; desktop and
phone-width synthetic previews verified populated/empty layouts.
Browse/search resolves deferred facets and applies the media preference before
rendering facet headings. When no facets remain, omit the entire **Filter by**
panel and its toggle and give results the full row width. Preserve the Subjects
directory's empty-state message and editable search-form labels.

Object-detail relationships use the full metadata-column width. Any map follows
below them, so an absent map does not reserve half the sidebar or force related
record labels to wrap early.

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

## Home-page local-history writing

**Start with the Right Path** retains its heading and archive introduction but
has no resource tiles. Its introduction spans the panel's full content width;
the previous 980px header limit and tile spacing were removed. The quick links
above it, recent writing below it and research-request section remain.

**Recent Local History Writing** progressively replaces its two fallback cards
with the latest two entries from
`https://feeds.tools.tadl.org/local_history_posts.json?limit=2`. `View More` retains
the source's type 295/tag 414 filters. The companion `TADLFeeds` source repository
adds a separate cache/job using those fixed filters and the existing 15-minute
background refresh pattern; news `/posts.json` remains independent. Deploy that
app first: its existing `feeds:refresh` postdeploy warmup includes this feed.
Deploying or pushing theme source does not deploy TADLFeeds.

The client loads through `conf/assets.conf` but fetches only when the home-page
grid exists. It omits credentials/referrers, limits the request to eight seconds,
renders titles as text, and accepts article/image URLs only on HTTPS
`www.tadl.org` under `/posts/` and `/sites/`. It preserves fallback cards on errors,
empty/malformed data or disabled JavaScript. A cold-cache 503 gets one retry after
60 seconds. The feed refreshes on subsequent visits, rather than scraping Drupal
on the Pawtucket request path. `tests/recent_writing_test.php` checks the actual
home template and client with synthetic browser/feed boundaries; desktop/mobile
preview uses synthetic feed entries.

All eleven theme suites and changed PHP/JavaScript checks passed for this change.
The companion TADLFeeds suite passed 30 tests/128 assertions and eager-load checks;
a read-only live scrape verified two posts from the exact filtered source. Those
checks do not establish production deployment or the new endpoint's availability.

## Subjects

Catalog subjects are related `ca_list_items` vocabulary records through
`ca_objects_x_vocabulary_terms`, as reflected in the migration mapping. They
are separate from the optional `lcsh_terms` and `lctgm` attributes.

- Object details show **Subjects** directly after Description. Each term links
  to a fresh `Browse/subjects` with the native `term_facet` criterion. This
  replaces the lower **Related Terms** block. Labels are escaped, duplicate IDs
  are removed, and native record access, bundle permissions and Pawtucket ACLs
  are enforced before display. Native locale-selected label arrays supply
  `name_plural`, falling back to `name_singular`; a term with only a plural label
  must not disappear. The facet template uses the same preference.
- Objects advanced search adds **Subjects** using the existing `term` search
  access point. The reference `app/conf/search_indexing.conf` maps it to both
  `ca_list_item_labels.name_singular` and `name_plural`; no index configuration
  or catalog data is changed. The separate Library of Congress field remains.
- **Browse → Subjects** opens a complete alphabetical list of available subject
  vocabulary choices, with no object cards, result options or object paging.
  Selecting a term starts a clean native `term_facet` object browse; its results
  retain media filtering, Tiles/List and paging. **Browse all subjects** returns
  to the index. The menu clears previous criteria so it always opens the index.
  The list uses three columns on desktop, two on tablets and one on phones.
  Choices derive from native accessible catalogue facets, independently of the
  media preference, as on other authority filters; no unfiltered counts appear.
  Empty facets show an empty-state message instead of all objects. Previously,
  the unselected route showed all objects, including records without subjects.
  The `tadl_subjects` facet group limits this entry to subjects. `#!merge` extends
  the native facet configuration, preserving other facets. Subjects also appear
  as a filter on ordinary object browse and search whenever available.
- Selected-subject results keep the refine sidebar visible, above results on
  phones, with native expansion and criterion links. Directory and results text
  follow the interior 18px baseline.

Ten standalone suites and PHP lint passed on PHP 8.5.10. The native reference
configuration parser verified preservation of all eight other object facets.
A synthetic desktop/mobile browser preview verified the subject directory,
18px links and no horizontal overflow at 390px. The reference configuration and
display-template parsers verified the facet merge and plural/singular fallback.
Production subject relationships, indexed search hits and installed configuration remain
unverified until deployment and a live check; source changes do not deploy them.

## Local verification on the laptop

The thirteen committed tests are portable and use synthetic boundaries. They do not
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
| `tests/finding_aid_test.php` | Selected collection export, descendants, access/ACL/bundle restrictions, complete unique inventories over native caps, field mappings, locations, escaped PDF text and safe failure; optional real Dompdf |
| `tests/asset_versions_test.php` | Stable/changed CSS and JS URLs, preserved timestamps, native loader options, subdirectory/absolute theme URLs, escaping, inline-code preservation and path boundaries |
| `tests/media_preferences_test.php` | Eligibility SQL, filtered result adapter, result rendering |
| `tests/media_preference_controller_test.php` | Cookie options, POST/CSRF, redirect validation |
| `tests/collection_detail_scripts_test.php` | Collection detail loader JavaScript and empty/populated field/relationship headings |
| `tests/object_detail_media_test.php` | Media selection, viewer controls and callbacks |
| `tests/object_detail_video_poster_test.php` | Covers, playback, player fallback and access |
| `tests/authority_detail_test.php` | Authority types/layout, related groups, access and loaders |
| `tests/result_context_heading_test.php` | Results context, headings, removable criteria, empty flat/deferred/media-filtered facets and Subjects browse/refine rendering |
| `tests/object_detail_metadata_test.php` | Empty/populated rich-text fields and fallback values; structured TGM pairing, safe links, escaping and permissions |
| `tests/image_download_test.php` | Toolbar/bundle/overlay menus, visible native PDF actions, viewer icon placement, outside-click/Escape/Tab handling, current image action relocation, gallery AJAX navigation and media callbacks, download policy/ACL/attachment checks, TIFF/JPEG/BMP/PDF bytes, BMP MIME aliases, conversion validation and failure handling |
| `tests/subjects_test.php` | Subject relationship rendering, escaping, native browse links, bundle access and Pawtucket ACL filtering |
| `tests/recent_writing_test.php` | Actual home fallback/View More filters; client refresh, URL/text safety, cold-cache retry and feed-error fallback |

All eight original suites passed during handoff preparation, as did PHP lint.
The nine suites passed after the image-download change on PHP 8.5.10 and Node
24.19.0. Desktop/mobile synthetic browser preview verified enlarged controls,
mouse/keyboard disclosure and wrapping. Production conversion remains unverified.
For future changes, begin with the relevant tests and lint changed files:

```sh
php -l path/to/changed_file.php
git diff --check
```

All ten suites passed after the 2026-10-02 viewer-control changes, including 238
image-download assertions. Changed PHP lint, new JavaScript syntax and the native
asset configuration parser passed. A synthetic local preview using the actual
native TileViewer engine verified desktop/390px controls, visible working zoom,
help opening/closing and scrolling, menu dismissal on canvas clicks, and
Enter/Tab/Escape download navigation. No production deployment was performed.

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
