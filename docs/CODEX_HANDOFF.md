# TADL Pawtucket handoff

Updated: 2026-10-19. This document carries the recent coding context to another
Codex chat or workstation. Read it alongside `AGENTS.md` and current source.

## Checkpoint and product direction

The review fixes below follow checkpoint `51b11d8` on `main` at
`git@github.com:tadl/tadl-pawtucket2-theme.git`, checked on 2026-10-08. That checkpoint
fixed collection counts corrupted by shared model instances. Use `git status`
and `git log` for the current revision. The original checkpoint was `e3ae5b4`.

This is the theme for the public TADL Local History Collection at
https://archives.tadl.org/. The user is now adding more object media in
Providence and iterating on Pawtucket presentation. Preserve thumbnail-rich
results, clear record context, ordinary navigation, and a generous desktop
media viewer. Use existing TADL visual conventions and accessible controls.

Changes belong here rather than in upstream CollectiveAccess. The surrounding
workspace has reference checkouts of Pawtucket and Providence at local tag
`2.0.10`; that is a local source fact, not a verified current production version.

## 2026-10-16: WCAG 2.2 AA theme pass

The shared desktop/mobile primary menu order is About, Browse, Collections,
Gallery, Advanced Search and Contact, followed by My account when signed in.

Read [Accessibility](ACCESSIBILITY.md) for the source audit, all 55 A/AA criterion
entries, coverage boundaries and remaining manual/content/third-party work.
This is a source remediation pass; no deployment or AA conformance claim was made.

The shared shell now has a focusable native main landmark, stronger focus/input
contrast, valid preference semantics and distinct pagination landmarks. Empty
identifier links are omitted, thumbnails have useful names, extra result details
use native disclosures, and Browse/Filters/dialog closes use native buttons.
Contact errors expose field associations and a focused summary. Account controls
carry appropriate autocomplete; password confirmation labels and cookie radios
are repaired. Gallery AJAX retains focus and announces selection; featured items
announce the visible range. The PDF.js template no longer imposes positive
tabindex values, and presentation mode permits zoom.

Editable lightboxes now offer Move earlier/later alongside dragging. Both use
the existing native reorder endpoint/CSRF/access checks through
`assets/pawtucket/js/lightbox-order.js`; save failures restore the prior DOM order
and announce the failure. Preserve this asset, its configuration entry, the
`data-order-*` container attributes and `Lightbox/order_buttons_html.php` together.
Read-only sets do not initialize reordering or expose move buttons.

`tests/accessibility_test.php` renders actual components and the shared shell
with synthetic native boundaries. `tests/accessibility_browser_test.js` uses
native static jQuery/Bootstrap/panel/sortable assets; it exercises axe, 320px and
desktop reflow, text sizing/spacing, keyboard controls, AJAX focus, modal return,
cookie selection and reorder rollback without a database or external requests.
The ordinary suite count is now 36. CI adds pinned Playwright/axe browser checks;
local tool configuration and pending acceptance work are in the guide.

The new `css/accessibility.css` is intentionally loaded after existing theme
rules. Its reduced-motion override includes native CSS transitions; the shared
JavaScript also respects that preference for jQuery animations. Existing data,
access filtering, permissions, submitted field names and source deployment policy
remain in force. No account, catalogue, server mail or production cache state was
changed during the accessibility work.

## Completed work and useful commits

| Commit | Result |
| --- | --- |
| `db90470` | Sentence-case finding-aid link with normal detail-link styling and hover underline |
| `0a9321d` | Inline unique descendant counts in both collection hierarchy columns |
| `9181332` | Flat descendant contents by default, explicit hierarchy switch and collection detail URL state |
| `f110dce` | Imported document-text HTML converted to escaped readable plain text |
| `89ce186` | Collection narrative and facts grouped into adaptive metadata columns |
| `4135465` | Native Read more / Read less disclosures for long detail metadata |
| `1bbfabd`, `57c1e46`, `a154914` | Accessible PDF text with extraction/transcription/pdf_text precedence |
| `9a021f8`, `69e5b45` | Complete finding-aid hierarchy with counts and no individual object inventory |
| `5e96940` | Crawler directives and separately installed root robots policy |
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

### 2026-10-09 deployment cache policy

`support/deploy-theme.sh` now holds the tracked deployment implementation; the
enclosing workstation `deploy` helper delegates with its local target. Routine
deploys preserve Redis/file application caches, sessions and temporary files.
The previous recursive `app/tmp` deletion did not clear Redis and is removed.
CSS/JS content versions and native configuration mtime checks handle ordinary
theme updates. As of 2026-10-14, Apache reload/configtest are removed from routine theme deploys.
OPcache timestamp validation is a deployment prerequisite; transfer failures
stop completion. Tests and documentation match this copy-only workflow.
See [Deployment](DEPLOYMENT.md) for the operator workflow and maintenance scope.
No deployment or production cache purge was performed for this source change.

### 2026-10-09 About page copy

`views/About/Index.php` contains the supplied replacement About text, with one
page-level heading, five section headings and the original paragraph breaks.
The FAQ placeholder links to `Front/Index#tadl-faq-heading` through the native
URL helper, preserving application subdirectory/clean-URL configuration. The
materials selection policy link uses the supplied TADL URL. The obsolete About
copy is replaced; the Site Pages template in `templates/about.tmpl` is separate.
The `tadl-about-content` wrapper shares FAQ link rules: dark blue, regular weight,
always underlined, with the same hover underline and visible keyboard focus.
The rules apply only to content, preserving navigation and other page controls.
Bold markup within links also inherits regular weight; ordinary bold answer text
remains bold.

### 2026-10-09 FAQ answer presentation

FAQ answer links are underlined in their resting state, use the theme's dark
blue, and retain hover and keyboard-focus cues. A FAQ-only purifier configuration
inherits native sanitization settings and strips inline `style` attributes without
changing stored content; semantic bold and italic formatting remain intact.
Paragraphs, lists, headings and quotations have consistent spacing. Category heading rules
target direct children so headings within answers do not look like categories.
No content edits, query changes or additional JavaScript are required. See
[Accounts and FAQ](USER_FEATURES.md) for the presentation contract.

Verification includes the portable FAQ suite and the installed HTMLPurifier,
plus browser previews of the supplied answer markup and synthetic rich text at
390 and 1280 pixels. Links within spans inherit the link color, semantic emphasis
survives, disclosures remain keyboard operable, and link focus is visible.

### 2026-10-08 theme review fixes

- Retained PDF and focal-point models are independent instances, with regressions
  for multiple PDFs, a blank/denied last PDF and A → B → A focal-point lookups.
- `helpers/record_access.php` applies batched public/deleted, native type/source,
  bundle and Pawtucket ACL checks to eligibility, thumbnails and finding-aid
  object counts. Unreadable ancestors prune descendant media candidates.
  Finding aids no longer load every object model to count it.
- Collection thumbnail fallback includes public secondary images when a private
  primary remains linked. Candidate queries return scalar IDs and read one
  untested media descriptor per unresolved card; primary preference, direct versus
  descendant priority and deterministic order remain intact.
- All six advanced forms normalize visible control IDs and associated widget
  scripts while preserving native names and values. Labels target unique controls.
- Mobile branding uses separate flex items for the toggle/logo and permits the
  tagline to wrap. Bootstrap events synchronize `aria-expanded`. The native AJAX
  viewer/lightbox panel adds modal semantics, background isolation, keyboard focus
  trapping and opener restoration without replacing native callbacks or Escape.
- Image exports use private, source-versioned conversion caching, locking,
  expiry and finite execution limits. Source authorization is repeated before
  every cache hit. See [Image downloads](IMAGE_DOWNLOADS.md) for configuration,
  filesystem requirements and the external-tool timeout boundary.
- Theme search/browse views no longer save or fetch rendered result HTML from
  ExternalCache. A zero lifetime can mean persistent storage in native cache
  backends; config values alone did not disable these writes.
- Collection introductory text replaces the placeholder. Gallery metadata uses
  shared detail units, empty-field suppression, long-field disclosures and
  sentence-case actions.

Verification uses 29 portable PHP suites, including Node.js panel lifecycle
regressions, and a synthetic browser preview with native jQuery/Bootstrap/panel
assets at 390, 768, 1024 and 1280 pixels. This does not establish production
deployment, live catalog permissions or full-page performance.

## Source map

| Area | Main entrypoints |
| --- | --- |
| Header, footer and toggle | `views/pageFormat/pageHeader.php`, `pageFooter.php`, `media_preference_toggle.php` |
| Preference endpoint/persistence | `controllers/MediaPreferenceController.php`, `helpers/media_preferences.php` |
| Eligibility and result adapters | `helpers/media_filter_helpers.php` |
| Collections | `conf/collections.conf`, `conf/browse.conf`, `views/Collections/`, `views/Browse/collection_thumbnail_helpers.php` |
| Browse/results/pagers | `conf/browse.conf`, `helpers/browse_navigation.php`, `views/pageFormat/browseMenu.php`, `views/Browse/browse_results_html.php`, `tadl_result_helpers.php`, `tadl_result_context_helpers.php`, image/list/refine subviews |
| Search previews | `conf/search.conf`, `views/Search/multisearch_results_html.php`, `tadl_search_results_subview_html.php` |
| Accounts/lightboxes | `helpers/user_features.php`, `controllers/AccountProfileController.php`, `assets/pawtucket/js/user-features.js`, shared header, native LoginReg/Lightbox views, object detail and browse result views; `docs/USER_FEATURES.md` |
| Home-page FAQ | `templates/faq_entry.tmpl`, `conf/templates.conf`, `helpers/home_faq.php`, `views/Front/faq_html.php`; `support/activate-faq.php`, `helpers/faq_setup.php`, `docs/FAQ_SETUP.md`; Providence Site Pages content |
| About page | `views/About/Index.php`; theme-owned copy with a link to the home-page FAQ section |
| Home-page writing | `views/Front/front_page_html.php`, `assets/pawtucket/js/recent-writing.js`; companion TADLFeeds `/local_history_posts.json` |
| Object media | `helpers/object_detail_media.php`, `views/bundles/representation_viewer_html.php` |
| Image downloads | `helpers/image_downloads.php`, `helpers/image_download_cache.php`, `controllers/ImageDownloadController.php`, `views/Details/image_download_binary.php`, `views/mediaViewers/viewerWrapper.php`, `assets/pawtucket/js/image-downloads.js`; `docs/IMAGE_DOWNLOADS.md` |
| Bulk record visibility | `helpers/record_access.php`; native restriction/ACL adapters shared by eligibility and thumbnails; finding aids no longer query objects |
| Crawler policy | `helpers/crawler_policy.php`, shared header and download views; `support/site-root/robots.txt`, `docs/CRAWLER_POLICY.md` (root file installed separately) |
| Enlarged viewer help | `views/Details/viewer_help_html.php` |
| Detail metadata and long-field disclosures | `views/Details/detail_field_helpers.php`, shared detail templates and scoped theme CSS |
| Document text | `helpers/document_text.php`, object detail view and scoped theme CSS; `docs/DOCUMENT_TEXT.md` |
| Collection details and browser | `views/Details/ca_collections_default_html.php`, `controllers/CollectionContentsController.php`, `helpers/collection_contents.php`, hierarchy views/helpers under `views/Collections/`; `docs/COLLECTIONS.md` |
| Authority details | `views/Details/authority_detail_helpers.php`, `authority_detail_html.php`, entity/place/occurrence detail templates |
| Family objects | `helpers/family_contents.php`, `controllers/FamilyContentsController.php`, authority detail links and shared result helpers; `docs/FAMILIES.md` |
| Galleries | `views/Gallery/index_html.php`, `set_info_html.php`, `detail_html.php`, `set_item_rep_html.php`, `set_item_info_html.php` |
| Styling | `assets/pawtucket/css/theme.css`, with the existing `main.css` foundation |
| Thumbnail focus | `helpers/thumbnail_focus.php`, `assets/pawtucket/js/thumbnail-focus.js`, shared result/collection views; `support/detect-thumbnail-faces.php`, `support/thumbnail-faces/`; `docs/THUMBNAIL_FOCUS.md` |
| Object thumbnail fallback | `helpers/object_thumbnails.php`, Browse Tiles/List views and multisearch previews |
| Shareable thumbnail tools | `support/package-thumbnail-faces.py`, `support/thumbnail-faces/share/`; allowlisted runtime export to a sibling `thumbnail-faces/` directory/ZIP, with independent setup/integration guides, snippets, license and tests |
| Asset cache versions | `helpers/asset_versions.php`, shared header and standalone Lightbox presentation |
| Collection finding aids | `controllers/CollectionFindingAidController.php`, `helpers/finding_aid.php`, `conf/finding_aid.conf`, PDF/binary detail views; `docs/FINDING_AIDS.md` |
| Regression checks | `tests/*_test.php` |

Resolve abbreviated view filenames relative to the directory named in their row.
Use `rg` to find the relevant helper or CSS selector rather than depending on old
line numbers. Native application behavior can be inspected in the nearby
`pawtucket2/app/` reference tree without editing it.

## Behavior and implementation contracts

### Browse ordering and detail navigation

The first `sortBy` entry in `conf/browse.conf` defines a fresh native browse's
default. Places and Events now use ascending **Name**, People and Organizations
use ascending **Identifier**, as do Families; Collections/Objects retain Name/Identifier
respectively. Subjects retains object Title ordering after choosing a term.
Entity identifier sorting relies on the catalogue's identifier convention;
People also retains the native surname/forename **Name** option. Its surname
letter bar appears only when that Name sort is selected, as required by the
native controller.

`helpers/browse_navigation.php` reads each configured default for list-menu and
detail-page Browse entry links. Explicit sort/direction parameters supersede a
legacy saved Relevance sort on entry. Subject entry links still clear the prior
term, and the dedicated Collections index retains its own configuration.
Existing Options choices and paging URLs are preserved. Native sorting happens
before media filtering/pagination; result-context storage preserves that order,
so detail **Prev / Next** follows it. Search and MultiSearch relevance defaults
remain unchanged. An already-open detail context keeps its existing order until
the visitor enters a browse again.
Object details intentionally omit **Browse objects**, showing only native
**Prev / Next** when available. Direct object visits without result context omit
the empty toolbar. Other detail types retain their Browse destination.

Verification: portable navigation/menu/default-map checks plus the actual native
configuration parser; media eligibility/context, authority, document-text,
account and results suites cover the integration boundaries. No deployment or
production record changes were performed for this source change.

### Families and related-member objects

`conf/browse.conf` exposes **Families**, restricted to the configured `fam`
entity type (including native subtypes), with Identifier ascending and Tiles as
the default. Menu and detail Browse families links explicitly request that view
so saved List choices do not override entry defaults. Family details use
**Browse families** and **About this family**.
Unknown entity types keep their own labels and native Prev/Next, without the
old invalid `Browse/Index` fallback. Entity relationship headings include families.

`tadlFamilyEntityScopes()` batches roots and both directions of
`ca_entities_x_entities`, including family objects and objects attached to directly
related `ind`/`org` entities and their subtypes. There is no recursive traversal
through other families or members' relationships. Access/deletion, native
type/source restrictions and levels, relationship bundles, and Pawtucket ACLs
apply before constructing numeric native search terms.

`FamilyContents/Objects/entity_id/ID` recalculates the scope on each GET and
delegates object sorting/access/deduplication/exports to the native SearchController.
Empty or denied scopes use an explicit zero-hit query. Paging, sort/view and
export links retain the family ID; result headings name the family. The endpoint
sends private/no-store and noindex headers. The separately installed root robots
policy includes it.

In Only items with media mode, families qualify through the same scope and the
existing permission-aware object/representation media checks. Filtering precedes
counts, pagination and Prev/Next storage. All items includes empty families.
Family details explain the combined scope. No stored relationships, access flags
or primary media are changed. See [Families](FAMILIES.md) for verification.
Verification passed 31 application suites (the independently edited deploy script
was excluded), native browse configuration parsing, a native Lucene syntax check
of the family OR query, changed PHP lint and diff whitespace. Full application
search execution and production rollout remain separate from these synthetic checks.

### Long detail fields

`tadlDetailExpandableValue()` in `views/Details/detail_field_helpers.php` supplies
a preview and native **Read more / Read less** disclosure for values over 600
visible Unicode characters. Plain text uses a word-boundary excerpt of up to
320 characters and a six-line clamp. Rich text uses balanced DOM excerpts through
`detail_preview_helpers.php`, preserving visible links, emphasis and paragraph/list
formatting. Link labels remain complete even when they cross the prose boundary.
Previews omit later DOM nodes instead of clipping hidden focusable links; cloned
IDs and active widgets/scripts are omitted. The full native value stays unchanged
inside the disclosure, with no additional queries or changed access filtering.

Repeated linked native `<unit>` fields use entry previews: five complete
line-separated associations or list items, including their roles. A few long
entries stay fully visible. This applies to object/collection creators, publishers,
related records, place vocabulary terms, external links and gallery metadata.
Narrative fields and media captions retain prose excerpt behavior even when they
contain links or line breaks. Short/empty fields retain their existing behavior.

The shared `tadlDetailField()` and first-available-field renderer use this across
collections, objects, people/organizations, places and events. Media captions
also use it. Specialized subject-term lists and authority relationship groups
retain their existing presentation; document text already has its own disclosure.
Object descriptions now use the shared native control in place of the legacy
readmore plugin. No new script or dependency is required.

Scoped CSS keeps the preview above the control, removes it when expanded using
`:has()`, switches the control text and supplies visible keyboard focus. Older
browsers without `:has()` may retain the preview above expanded text; full text
remains available. Native `details` hides collapsed links from keyboard navigation.
The association-preview repair was verified with synthetic metadata at 1280px
and 390px: five visible working record links, complete roles, prose emphasis,
native Enter/Space expansion, focus retention, omitted-link tab order and no
horizontal overflow. The shared unit/gallery regressions retain native access
options and complete full values. Production deployment remains an operator step.

### Object document text

Object details have a collapsed **Document text** section after both media and
metadata columns. A readable attached PDF is required for every source. Use
populated accessible PDFs' `ca_object_representations.media_content` first, then
`ca_objects.transcription`, then `ca_objects.pdf_text`. A missing, empty or
restricted source falls through to the next; all unavailable produces no section.
Private paired PDFs do not qualify a TIFF object. Native record
access, deletion, type/source/bundle permissions and Pawtucket ACLs remain enforced;
text/labels are escaped and line/page breaks retained. Multiple PDFs follow native
rank/primary order with an ID tie break. Imported `transcription`/`pdf_text` HTML
is stripped for display, preserving paragraph/line/list/table-row breaks and
decoding entities before output escaping. Markup-only metadata counts as empty.
Extracted PDF text and representation labels retain literal brackets; stored
catalogue values are unchanged. Native `details` needs no new JavaScript,
controller, extraction process or external service. The migrated Scripto source
is `transcription`; the separate `pdf_text` field is the final display fallback.
See `docs/DOCUMENT_TEXT.md`. The candidate helper passed a read-only installed-model
check for PDF extraction preceding populated transcription, in addition to
synthetic checks for all three sources and permissions. No theme deployment or
catalogue mutation was performed for this change.

### Crawler hints and root robots policy

Media controls/download/export links use `nofollow` without altering callbacks or
existing `rel` tokens. Search/MultiSearch/Browse HTML uses `noindex, follow`; account
pages use `noindex, nofollow`. Theme binary download views send `X-Robots-Tag`.
Public record pages and preview media remain indexable. The tracked replacement
`support/site-root/robots.txt` covers clean and `index.php` routes for dynamic
searches, downloads, viewer data and sessions. Theme rsync does not install this
at the site root: see `docs/CRAWLER_POLICY.md` for backup/install/verification.
No production deployment or root-file replacement was performed for this change.
These are cooperative crawler hints, not enforced request throttling. A robots
disallow prevents crawlers from reading a noindex directive on that URL; the policy
reduces crawl work rather than guaranteeing removal of already indexed URLs.

### Staff object editor shortcut

`tadlProvidenceObjectLink()` in `helpers/user_features.php` renders **View in
Providence** beneath the object title in the metadata column. It requires a
logged-in Pawtucket session and native `ca_users::isStandardUser()` (full-access,
`userclass = 0`); public, deleted and other account classes get no link markup.
The displayed object's positive numeric ID is used in the fixed Providence
`ObjectEditor/Edit/Screen49/object_id/ID` route on `collections.tadl.org`. The
link opens a new tab with `noopener noreferrer` and an accessible new-tab hint.
It requires no media and leaves Providence authentication/permissions in charge.
Keep whole-page caching disabled for this session-dependent control.

### Profile saves

Profile GET remains `/LoginReg/profileForm`; both the normal and AJAX forms now
POST to the theme's `/AccountProfile/profileSave`. Deploy the controller and view
together. Native login/reset, CSRF and `ca_users` password policy stay in charge.
The endpoint loads the authenticated user ID, permits only configured profile
preferences and native public-use group invitations, and ignores submitted IDs
or privilege fields. Unchanged email never triggers a username collision check
or login rename. Changed public-account email checks other usernames while
excluding the current ID; staff contact-email changes preserve staff usernames.

Upstream profileSave modified `request->user` before completing validation;
native request close saves that model even on an error. The theme validates first
and stages edits on a separate model, refreshing the request user only after
success. Validation/setter failures cannot leak phone/preference edits through
request close. A group write failure after profile success gets distinct, honest
feedback. Native external password adapters are not wrapped in a cross-service
transaction. Forms escape field/group errors and omit obsolete undefined sum data.
See `docs/USER_FEATURES.md` for limitations and live verification boundaries.

### Collection finding aids

**Download finding aid** exports the selected unit and all readable descendants
as a descriptive PDF. As of 2026-10-19, every hierarchy level uses the same
`collection_fields` mapping: extent, description, dates, creator relationships,
scope/content, language, provenance, rights and other configured public metadata.
Missing fields are omitted. A folder without individually catalogued objects can
still describe its complete physical contents. Root metadata appears once in the
overview; descendants retain their actual parent and relative depth.
The recorded level follows each unit's title in normal-weight parentheses,
including the selected root, rather than appearing as a separate metadata row.
Missing/restricted levels have no suffix or empty parentheses.

The export no longer counts or queries linked objects. Overall totals, directly
linked item counts and empty-object messages are removed. Collection-page browser
counts are unchanged. The PDF remains independent of media preference, browser
mode, pagination and configured browser display depth.

Record/type/source access, bundle permissions and Pawtucket ACLs apply at every
level. Creator names have independent entity and label checks and use only the
configured creator role; arbitrary relations are not treated as creators.
Language List labels match collection-detail behavior through the narrowly scoped
`display_language_labels` option; record/bundle restrictions still apply.

Recorded rank determines sibling order, with natural names/IDs as tie-breakers;
`hierarchy_sort = name` explicitly restores natural title order. Complete branches
stay together, including identically named siblings. There is no shared PDF cache
or object-count query dependency. Long metadata flows across PDF pages with
headings kept with the beginning of their fields. Body text is 10.5pt.

See `docs/FINDING_AIDS.md` for mappings, privacy boundaries, checks and remaining
review-spec work (including tagged PDFs, bookmarks and parent context). The
endpoint remains `/CollectionFindingAid/Download/collection_id/<id>`; `FindingAid`
is a native bundled-plugin prefix. Configuration, helper and views deploy as one
revision, using existing Dompdf with remote resources/PHP/JS disabled. No schema
change, dependency installation, cache purge or service restart is needed.

Verification: all 36 standalone suites, changed PHP lint and diff checks passed.
The native configuration parser accepted all 29 mappings and options. Real
Dompdf/native dispatcher checks passed; Poppler rendering and extracted text
verified an 11-page synthetic PDF with 18 descriptive units, metadata-only folders
and all 30 paragraphs of a long description. No catalogue counts or restricted
text appeared. Production output remains a deployment-time check.

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

- Navigation collapses through **1024px**. At those widths the search form and
  media preference appear only inside the open menu; the desktop search wrapper
  must not use Bootstrap's `hidden-xs`, which forces it visible above 767px.
  Expanded navigation has no fixed maximum height or internal scrollbar, and
  top-level links and open dropdowns flow vertically throughout that breakpoint.
  My account uses the same borderless, transparent, indented submenu styling
  throughout collapsed navigation, including the 768–1024px tablet range.
  Native Bootstrap collapse animations and accessible expanded state remain intact.
  Browser checks covered 390, 767, 768, 900, 1024, 1025 and 1280px, including
  account/Browse expansion and keyboard opening/closing. This is source-only.
- The shared `.tadl-main-nav` has its own 18px text size/floor, including
  dropdown choices. Homepage and interior navigation therefore match in normal,
  hover, focus and open states at desktop/mobile widths. Do not let the menu
  inherit Front's smaller body size or scope its dropdown sizing only to
  `.tadl-interior`; landing-page content retains its separate established scale.
- Media-preference buttons have their own **18px minimum**, matching the
  collapsed-menu controls when displayed in the desktop header, including Front.
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

- **All items** is the default when neither the cookie nor session has a valid
  saved preference. Existing **Only items with media** selections remain intact.
  The browser preference currently lives beneath the header search box.
- `tadlMediaPreference` is a host-only, HttpOnly, SameSite=Lax cookie lasting
  one year, scoped to the application path and Secure on HTTPS. Session state
  is a fallback. Ordinary links do not append `media=only` or `media=all`.
- The setting endpoint requires POST and a valid CSRF token; it validates the
  mode and redirects only to a safe path within this installation.
- Objects and collection lists are filtered before theme counts and pagination.
  A collection qualifies through accessible object media in itself or any
  descendant. Images, video, audio, PDFs and supported embedded media qualify;
  private, deleted or metadata-only representations do not.
- **Browse > People, Organizations, Families, Places and Events** follows the same
  preference through related objects. `tadlMediaAuthorityBrowseTable()` maps
  `people`/`organizations`/`families` to `ca_entities`, `places` to `ca_places`, and the
  configured Events route `occurrences` to `ca_occurrences`, including AJAX.
  `tadlAuthorityMediaEligibleIDs()` qualifies only records with a nondeleted related
  object and representation in the visitor's access mask, readable media bundles,
  native object/representation Pawtucket ACLs and a usable original or embed.
  Directly attached authority media alone does not qualify. It fetches ID pairs in
  batches of 500 authority records and reuses collection eligibility's lazy
  descriptor decoding, stopping when each record qualifies. Eligibility caches
  are limited to the request and scoped by table/user/access; no persistent cache
  or index is added. SQL identifiers come from a fixed native-table allowlist.
  Tiles/List counts and pagination use filtered IDs, as does the authority detail
  Previous/Next result context. Native facet counts are suppressed on these browses
  in media-only mode. All items mode remains unfiltered.
  Families additionally use the permitted directly related people/organization
  scope described above. Places qualify through readable descendants as well as
  direct relationships; People, Organizations and Events remain direct.
- Preserve native record access and ACL filtering. This preference is not
  authorization, and direct object/collection detail URLs remain accessible.
  Authority Search and MultiSearch routes retain native behavior; this
  related-object filter applies only to the configured authority Browse routes.
- Related result contexts must save the displayed IDs for Previous/Next.
  The preference is visitor-specific; a shared URL uses its recipient's setting.
- Native facet counts include all records, so media-only pages omit those counts.
  Downloads use native unfiltered results and are available in All items mode.
- `conf/collections.conf` keeps `cache_timeout = 0`; whole-page content caching
  is disabled in `conf/app.conf`. Theme Tiles/List and result-block HTML cache
  calls are removed entirely because native zero-lifetime cache semantics are
  backend-dependent. Native result-ID caching remains untouched. Do not add shared
  HTML caches without fully including the visitor's permissions and preferences.

### Results and authority pages

See [Collections](COLLECTIONS.md) for the current visitor behavior, flat versus
hierarchy count semantics, configuration and post-deployment checks. The notes
below retain implementation details and historical verification evidence.

- Collection browse performance: `tadlMediaEligibleIDs()` first fetches only
  collection/representation ID pairs, then decodes one untested descriptor per
  unresolved collection until it qualifies. Shared representations are decoded
  once per call; inaccessible/deleted, metadata-only and unsupported media still
  fail eligibility. Preserve filtering before counts, paging and result contexts.
  No persistent eligibility cache or new database index was introduced.
- `tadlGetCollectionImages()` uses native directly attached collection primary
  media first, then usable object images directly in that collection, then
  descendant images only for unresolved cards. Public secondary images can replace
  unavailable primaries. Scalar candidate IDs pass bulk native permission checks
  before retrieving media blobs, with stable hierarchy/name/primary/rank/relation
  order. Object/relationship selectors remain enforced for direct object images;
  access/deletion checks and focal-point rendering remain intact.
  Tiles, List and multisearch previews use this helper. The Collections index
  retains its existing descendant selection through the same bounded SQL.
  The direct-object fallback previously rendered all linked images and repeatedly
  loaded the same collection model, keeping only the last tag; its thumbnail may
  change now that selection follows a deterministic first-image order.
- Read-only native checks on 2026-10-05 returned identical eligible IDs before/
  after the optimization. The eligibility helper took approximately 2.05s versus
  0.69s; a nine-card thumbnail lookup took 1.63s versus 0.06s. These are helper
  timings on the production database with proposed source evaluated in a separate
  CLI process, not full-page timings after deployment. No installed source,
  database records, indexes or production services were changed. The standalone
  suites include large synthetic collections, bounded descriptor/media rows,
  invalid descriptors, primary precedence and actual Tiles/List routes. Public
  page timing and authenticated navigation remain deployment-time checks.
- Collection entry lists sort by name. The detail-page Collection Browser now
  defaults to the preferred collection name rather than Providence's manual rank.
  `views/Collections/hierarchy_helpers.php` normalizes access/media-filtered sibling
  IDs with case-insensitive natural ordering at every rendered level (Drawer 2
  before Drawer 10). Both the initial browser and recursive AJAX child list use it.
  Explicit non-name configuration sorts are still respected. Hierarchy structure,
  exclusions, media/access filtering, expansion/history and direct links stay native.
  The finding-aid/export sort is separate. Rendered synthetic hierarchy tests cover
  numeric labels, recursive order, filtered siblings, ties and history links.
  All twenty suites and changed PHP lint passed; a read-only native collection
  check confirmed result ordering. This hierarchy change has not been deployed.
- Collection index pagination uses normal links with page/view state. Browse
  retains native keys, criteria, sorting and result contexts; do not strip them
  just because URLs look complicated. Browser Back and direct page links matter.
  The Collections index repeats its pager at the top beside the count and
  Tiles/List controls, with a flexible introduction column and stacked controls
  on phones. Both pagers use the same helper/page state and disappear for a
  single page; adding the top pager requires no additional catalogue query.
- Collection details place the title and compact record navigation in a shared
  header, with the finding-aid link beside populated metadata
  in an adaptive overview. `tadl-collection-fields` groups description, its source
  and scope/content in the center; `tadl-collection-facts` groups creators, dates,
  extent, language, vocabulary/subject terms, rights/copyright and relationships
  in the right column. Empty columns are omitted, and columns stack on phones.
  Existing access filtering, long-field disclosures and native links are retained.
  The contents toolbar
  aligns **Collection items**, the filtered count, **Options**, **Tiles/List** and
  pagination. Metadata and tools stack on narrower screens.
- Collection details default to a flat list of the selected collection's objects
  plus every readable descendant's objects. Native search unions memberships, so
  an object linked to several descendants appears once. A readable hierarchy
  offers **View collection hierarchy** beneath the finding-aid link; explicit
  `collection_view=hierarchy` restores the existing browser and directly attached
  items. **View all collection items** returns to flat mode. Unknown/missing mode
  defaults to flat; switching scope resets paging and retains sort/display state.
  Leaf collections omit the switch. Catalogue hierarchy and finding aids are unchanged.
  Both view-switch links use a decorative eye icon hidden from assistive technology;
  the finding-aid download retains its document icon.
- Both hierarchy browser columns append **(42)** inside the collection title link
  (or nonlinked title), without `records` or a forced line break. Counts include
  unique visible objects in the selected branch and all readable descendants,
  including levels deeper than `max_levels`; empty visible branches show **(0)**.
  `tadlCollectionContentsCounts()` reuses the flat-list traversal/access policy,
  executes one fresh native object browse per browser response, applies the theme
  media filter, and fetches memberships in batches of 500 collections. It rolls
  each object up its ancestor chain once, so overlapping memberships do not inflate
  parent counts. No per-object model loads or per-title searches are needed.
  Rendering also prunes titles outside that readable branch. Counts are request-local;
  PDF finding aids now use descriptive metadata without object counts. Deploy the helper,
  both hierarchy views and CSS together. No schema change is required.
  The 2026-10-08 count change passed all 27 standalone suites, changed-file PHP
  lint and synthetic desktop/phone browser checks of both columns, long titles,
  selected-link styling and AJAX expansion. Native production integration remains
  a deployment check; this change has not been deployed.
- Collection traversal retains independent model instances, including loader and
  recursive-browser roots. Native `Datamodel::getInstance(..., true)` returns one
  mutable cached instance, not a fresh initialized model. Reusing it for saved
  graph nodes overwrote IDs, parent pointers and permission state, causing parent
  objects to appear in the last empty box's count and corrupting flat traversal.
  The collection scope and rendered hierarchy tests now model that native cache
  behavior. A synthetic 404-item parent with empty boxes/folders reproduced the
  incorrect child count before the fix; both media preferences now keep the
  parent's 404 and the empty descendants' zeros, without mutating the page model.
- The loader uses `/CollectionContents/Objects/collection_id/<id>` with the chosen
  `collection_view`. Its controller supplies `tadl_collection_controls=1` and the
  selected `tadl_collection_id` to the native SearchController. Sorting uses the
  shared Options partial. View, sort/order
  and both pagers link to `/Detail/collections/<id>` with ordinary result-state
  parameters, retaining the collection overview on every page. Reload, bookmarks
  and browser Back use those URLs. The loader validates view/sort/direction/offset
  and rebuilds the object search from accessible collection IDs; incoming search/key,
  refinement or advanced-search parameters cannot replace it. The helper walks
  descendants by level with parameterized parent/access/deletion queries in batches
  of 500 parents, then native model record/type/source, hierarchy/ca_objects bundle
  and Pawtucket ACL checks. Inaccessible ancestors prune their branches. Traversal
  has no fixed depth or related-item cap and guards cycles. Numeric OR terms stay
  server-side; an empty scope searches the impossible object ID 0, never `*`.
  Object search, access/ACL restrictions, sorting and exports remain native, with
  theme media filtering before counts, paging and result contexts. Other search/
  browse and authority AJAX routes stay native. Cache keys include collection and
  mode; the preference toggle preserves mode. Deploy the controller, helper and
  template together; no schema/index rebuild or additional dependency is required.
- Collection result responses send `Cache-Control: private, no-store` and
  `X-Robots-Tag: noindex, follow`. The replacement root robots policy also excludes
  `/CollectionContents` (clean and index.php routes); installing that root file
  remains a separate operator step, as documented in `docs/CRAWLER_POLICY.md`.
- Rendered regressions cover later pages, both views, sort/order and invalid loader
  state. Desktop/tablet/phone synthetic browser previews check layout and dropdown
  interactions; production navigation remains a deployment-time check.
  The 2026-10-08 flat/hierarchy change passed all 27 standalone suites and changed
  PHP lint. Synthetic desktop/390px phone previews verified keyboard switching,
  hierarchy paging, sorting, the reverse link and Tiles/List controls without
  horizontal overflow. No production deployment was performed.
- Multisearch has up to six previews per category, sensible text layouts for
  non-image records, counts and ordinary links to full results. It is not a carousel.
- Shared Tiles results use a responsive grid with equal-height cards within each
  row. Long titles remain fully visible without allowing later cards to float
  into gaps. Each AJAX/infinite-scroll page owns its grid; controls and pagination
  remain outside it. Desktop/tablet/phone columns remain three/two/one, with two
  columns in the narrower authority layout at 992–1199px.
- Object, collection, person, organization, place and event Detail pages use
  `tadlDetailNavigation()` beside their headings: native context **Prev / Next**
  links plus the corresponding Browse action, with no Back button. Object details
  are the exception: only Prev/Next, with no toolbar when both are unavailable.
  The toolbar aligns right and wraps beneath the heading when space is limited;
  it reserves no outer navigation columns. Other detail types retain Browse on
  direct visits with no neighbors.
  Object media keeps its existing width; collection results stay full-width.
- Place hierarchy uses a linked native hierarchy unit with an explicit ` > `
  separator and encoded labels. Native access filtering remains in charge.
  The previous scalar-tag delimiter rendered concatenated names and a trailing
  separator; a read-only installed-model check confirmed the corrected output.
- Result headings identify search/browse context and authority names. Active
  criteria are removable with visible controls and accessible labels.
- The shared search/browse **Options** menu omits Lightbox bulk-add and selection
  actions. Sorting, Start Over and eligible downloads remain available. This is
  consistent with result tiles/list rows, which omit individual Lightbox buttons
  for everyone. Signed-in users can add objects from object details and use
  **My lightboxes**. Result-card and result-block HTML is rendered per request.
- Object browse, regular search and advanced search offer **Date**, using the
  same `ca_objects.date.dates_value` field displayed on object details and native
  date-range sorting. Date defaults to ascending; the normal descending control
  remains available. New object keyword searches start with Relevance; object
  browse entry links retain Identifier ascending.
- Entity types distinguish people and organizations; places and events have
  explicit headings. The shared authority layout gives related objects priority
  and collapses long relationship groups instead of leaving tall empty columns.
- Detail loaders use native URLs, serialized JavaScript values, and useful
  empty/error states. Do not insert HTML returned by `caBusyIndicatorIcon()`
  directly into a quoted JavaScript string.

### Object media and gallery navigation

- Browse/search object tiles, list rows and multisearch previews prefer the
  primary representation. If its thumbnail is unavailable, `tadlObjectThumbnail()`
  uses native `getRepresentations()` with the current request's access mask,
  preserving ACL and bundle checks. It chooses a usable image derivative with
  primary/rank/representation-ID precedence, skipping icons and queued media.
  Unrestricted primary tags avoid loaded-object and media lookup queries;
  restricted representation contexts use the permission-filtered inventory. Neither catalogue
  links nor access values are changed. This handles a private primary JPEG whose
  public paired TIFF still has generated JPEG thumbnails.
  Read-only native validation on 2026-10-07 selected the public TIFF derivative
  for the affected ticket record. Installed theme source was not changed by that
  validation; deployment remains separate.
- Cover thumbnails now carry Providence `_CENTER` points into browser crop geometry.
  Staff selections take priority over optional offline YuNet face-box suggestions.
  No whole-image fallback or per-page inference is used; default cover remains when
  no focal metadata is available. AJAX results and resized cards are recalculated.
  See `docs/THUMBNAIL_FOCUS.md` for scope, runtime installation, bounded maintenance
  batches and durable-cache behavior. The local runtime was installed on the
  server; the user successfully processed the initial collection proof of concept.
  The follow-up CLI adds `--all` for every public attached image representation,
  including nonprimary and uncollected media, streaming keyset pages to one local
  detector in a single invocation. `--limit` remains optional in all mode; the
  existing collection mode retains its primary-only selection and default limit.
  `conf/thumbnail_focus.conf` shares the durable directory between rendering and
  maintenance (default `/var/cache/tadl-thumbnail-faces`). Provision ownership
  and preserve old JSON before any deployment that clears `app/tmp`; apply can
  also migrate valid surviving legacy results. Detection has no HTTP/API calls.
  SQL selection, migration/purge survival, retry and lock tests pass. The global
  scan and durable-storage change have not been deployed/applied in production.
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
  Authorized JPG/PDF conversions are reused from a private, versioned cache;
  every request rechecks source permissions, including Pawtucket-only ACLs. The
  cache expires unused exports after seven days and serializes identical conversions.
  See `docs/IMAGE_DOWNLOADS.md` for execution limits and operational requirements.
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
Entities show repeatable **Address** values in the same sidebar for people,
organizations and families. Structured native `ca_entities.address` reads retain
bundle permissions, access filtering and no-default behavior. Address lines,
locality and country are followed by that row's optional type and dates; empty
addresses are omitted and all stored text is escaped before adding line breaks.
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
Deferred/hierarchical facets render their already resolved, access/media-filtered
choices immediately through `views/Browse/refine_facet_values_html.php`. Load the
native hierarchy into a hidden staging container and replace those choices only
when a successful response contains links. Empty/script-only responses and
request failures retain the usable native filter links; populated responses keep
their native hierarchy click handlers. This prevents a lone **Places** heading
when the hierarchy endpoint returns no choices despite a populated facet.
The results-context suite covers these response states, and synthetic browser
checks with the installed jQuery verified fallback links and hierarchy handlers.
Deployment and authenticated live filtering remain deployment-time checks.

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

**Start with the Right Path** is removed entirely as of 2026-10-09, including its
heading, introduction and panel wrappers. Recent writing now follows the intro
and quick links directly. The FAQ and research-request sections remain.

**Recent blog posts** progressively replaces its two fallback cards
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

The thirty-five committed tests are portable and use synthetic boundaries. They do not
bootstrap CollectiveAccess or need its database; the media test uses SQLite in
memory. No Composer/npm install is needed for these standalone checks.

Requirements: PHP 8+ CLI with DOM, PDO SQLite, JSON and fileinfo; `proc_open` enabled;
Node.js, Bash and Python 3.11+ available on PATH. The package suite uses Python to export
and verify a standalone source snapshot. Shared text-field tests and the theme
header also need mbstring. The original handoff checks passed on PHP 8.5.7 and
Node 24.13.0. Those are observed workstation versions, not a required production
upgrade.

From the theme repository:

```sh
php --version
node --version
python3 --version
php -r 'foreach (["dom", "pdo_sqlite", "json", "mbstring", "fileinfo"] as $extension) { echo $extension, ": ", extension_loaded($extension) ? "yes" : "no", PHP_EOL; }'
(
  for test_file in tests/*_test.php; do
    php "$test_file" || exit 1
  done
)
```

| Test | Coverage |
| --- | --- |
| `tests/collection_hierarchy_test.php` | Initial/recursive hierarchy rendering, natural name order, inline descendant counts in both columns, one search per response, numeric labels, access/media-filtered siblings, stable ties and history/direct links |
| `tests/collection_thumbnail_test.php` | Actual SQL and Tiles/List rendering, bounded media rows, stable selection, access/deletion, native collection primary precedence and direct/descendant fallbacks |
| `tests/object_thumbnail_test.php` | Primary preference, native access/ACL/deletion delegation, deterministic fallback, usable derivatives and actual object Tiles/List/multisearch rendering |
| `tests/thumbnail_focus_test.php` | Native image-tag preservation, manual focal priority, invalid/stale cache rejection, responsive cover geometry and multiple faces |
| `tests/thumbnail_detector_test.php` | Actual CLI/SQL with synthetic catalogues, global keyset scans, public/shared/nonprimary selection, bounded runs, durable migration/purge survival, locks and retries |
| `tests/faq_setup_test.php` | Read-only setup plans, targeted native template registration, additive editor placements, private pre-change backup, no-op reruns, preserving unrelated configuration and transactional rollback |
| `tests/profile_save_test.php` | Own-email/staff-public login behavior, collisions, separate user staging, request-close persistence, validation/setter/model failures, native password delegation, POST/session/CSRF boundaries, public-use groups and direct/AJAX profile form error escaping |
| `tests/contact_mail_test.php` | Actual contact controller/form/mail templates and Turnstile verifier; named Reply-To, unique subjects, validation/POST/CSRF, legacy CAPTCHA, two-host Turnstile success/failures, partial setup, retry guidance and CSP override; optional native header merge check; synthetic HTTP and captured mail only |
| `tests/user_features_test.php` | Anonymous/authenticated navigation and result actions, staff-only Providence shortcut in actual object detail HTML, valid object IDs/new-tab safety, native login POST/CSRF, registration policy, password-manager fields and cache separation |
| `tests/home_faq_test.php` | Published/readable Site Pages, drafts, incomplete entries, locale, rank, escaped questions and purified answers; optional real HTMLPurifier |
| `tests/finding_aid_test.php` | Selected-unit export; full nested/uncatalogued/wide hierarchy; rank/name ordering and duplicate-named branches; access/ACL/bundle and creator privacy; language labels; configurable metadata at every level; no object models, queries, counts or entries; escaped text and safe PDF failures; optional real Dompdf/native dispatcher |
| `tests/asset_versions_test.php` | Stable/changed CSS and JS URLs, preserved timestamps, native loader options, subdirectory/absolute theme URLs, escaping, inline-code preservation and path boundaries |
| `tests/theme_deploy_test.php` | Actual deployment shell with synthetic SSH/rsync boundaries, source paths with spaces, cache preservation, transfer failure handling and no remote shell/service operations |
| `tests/production_hardening_test.php` | Native Gallery guard shim, public/missing/private/user set boundaries, real HTTP error headers/statuses and safe export diagnostics |
| `tests/media_preferences_test.php` | Eligibility SQL, lazy collection/authority descriptor fetching, related-object access/deletion/ACL/bundle checks, People/Organizations/Places/Events Tiles/List counts/paging/detail context, table cache isolation, route scope, invalid-media exhaustion, filtered result adapter and result rendering |
| `tests/browse_navigation_test.php` | Configured logical browse defaults, actual single/multiple-target menu and detail Browse links, native Prev/Next preservation, Subjects entry reset, configurable directions and optional native configuration parsing |
| `tests/family_contents_test.php` | Actual SQLite family/member scopes, bidirectional/subtype/deduplicated relationships, one-hop limits, root/member access/type/source/bundle/ACL checks, batched wide families, endpoint validation/current scope and native search delegation |
| `tests/place_contents_test.php` | Actual SQLite descendant scopes and object relationships, private/deleted/ACL/type branch pruning, a 6,000-descendant branch, bounded queries, native search replacement and controller scope/paging |
| `tests/media_preference_controller_test.php` | Cookie options, POST/CSRF, redirect validation |
| `tests/collection_detail_scripts_test.php` | Collection detail loader JavaScript, empty/populated field/relationship headings, long-field controls and narrative/facts column placement |
| `tests/collection_contents_test.php` | Actual helper/controller, SQLite hierarchy/membership queries, deep/wide branches, unique descendant rollup counts, media preference, scope/access/deletion/ACL/bundle pruning, cycles, duplicate memberships, bounded queries and canonical native search delegation |
| `tests/object_detail_media_test.php` | Media selection, viewer controls and callbacks |
| `tests/object_detail_video_poster_test.php` | Covers, playback, player fallback and access |
| `tests/authority_detail_test.php` | Authority types/layout, related groups, access and loaders |
| `tests/result_context_heading_test.php` | Results context, headings, removable criteria, empty flat/deferred/media-filtered facets, hierarchy-response fallback and Subjects browse/refine rendering |
| `tests/object_detail_metadata_test.php` | Empty/populated rich-text fields and fallback values; structured TGM pairing, safe links, escaping and permissions |
| `tests/detail_read_more_test.php` | Shared long-field previews and native disclosures, complete rich values/links, short/empty fields, Unicode boundaries, safe preview/labels, permission-preserving reads and long/short media captions; actual collection integration is also covered by collection_detail_scripts_test.php |
| `tests/document_text_test.php` | media_content/transcription/pdf_text precedence and permission fallbacks, empty/missing fields and imported HTML, private pairs, native record/bundle/Pawtucket ACL boundaries, safe text/labels, Unicode/line/page breaks, multiple-PDF ordering and actual full-width detail template placement |
| `tests/image_download_test.php` | Toolbar/bundle/overlay menus, visible native PDF actions, viewer icon placement, outside-click/Escape/Tab handling, current image action relocation, gallery AJAX navigation and media callbacks, download policy/ACL/attachment checks, TIFF/JPEG/BMP/PDF bytes, BMP MIME aliases, conversion validation and failure handling |
| `tests/crawler_policy_test.php` | Media link rel/callback preservation, public versus search/account metadata, actual binary HTTP headers/bytes, clean/index.php robots rules and public record/asset/media allowances |
| `tests/thumbnail_package_test.php` | Source allowlist/hashes, safe existing-output refusal, ZIP contents and independent exported PHP/Python tests; additionally requires Python 3.11+ |
| `tests/subjects_test.php` | Subject relationship rendering, escaping, native browse links, bundle access and Pawtucket ACL filtering |
| `tests/recent_writing_test.php` | Actual home fallback/View More filters; client refresh, URL/text safety, cold-cache retry and feed-error fallback |
| `tests/record_access_test.php` | Real batched SQLite permission SQL, native type/source restrictions and per-type levels, public/deleted access, media bundles, Pawtucket-only ACLs, private ancestor pruning and restricted primary fallback |
| `tests/ui_consistency_test.php` | All six actual advanced forms, unique IDs and labels, preserved widget references/native submitted names, gallery metadata and Node.js `user_features_js_test.js` panel lifecycle/focus/ARIA regressions |

The review fixes passed all 29 portable PHP suites, syntax checks for 237 PHP
files and the four theme JavaScript files, and `git diff --check`. Synthetic
browser checks verified header geometry at phone/tablet/desktop widths, unique
advanced-search label targets, dropdown expanded state, viewer Tab/Shift+Tab,
Escape and restored opener focus. The installed ImageMagick/bundled Dompdf
download check also passed with synthetic images; no production changes
are made by these checks.

Historical checkpoint: all 27 standalone suites passed for `db90470` on
2026-10-08, along with changed PHP lint and `git diff --check`. Synthetic browser
checks verified flat/hierarchy navigation, inline counts in both columns at
desktop/phone widths, and the finding-aid link's normal and hover styles. These
checks do not establish production deployment or performance.

Historical verification: all eight original suites passed during handoff preparation, as did PHP lint.
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

Optionally verify browse configuration syntax with an installed native parser,
without bootstrapping the application or connecting to a database:

```sh
TADL_TEST_CONFIGURATION=/path/to/pawtucket/app/lib/Configuration.php \
  php tests/browse_navigation_test.php
```

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
- The native account/lightbox pilot is now wired into the theme. Anonymous login
  links remain hidden; use the bookmarked `/LoginReg/LoginForm`. Registration is
  disabled. Signed-in users get account navigation and additions on object details;
  thumbnail/list results omit Lightbox buttons for everyone.
  Bulk additions remain omitted until they respect filtered results. See
  `docs/USER_FEATURES.md` for native features, access caveats and live checks.
- FAQ template/editor activation was authorized and completed in production on
  2026-10-05 using the focused setup script. The native selector offers **FAQ entry**,
  all three content fields are registered, and sort order/locale were added to the
  existing editor. Reapplying is a no-op; no content was created/published. The FAQ
  stays hidden until staff publish complete entries. See `docs/FAQ_SETUP.md` for
  repeatable setup, backups, upgrade preservation and live verification limits.
  Routine content edits need no theme deploy; public researcher sessions do not
  grant FAQ editing permissions.
- Continue iterative responsive/browser QA as real media is added. Do not
  collapse distinct related records just because their titles match.
- Production deployment and final live smoke checks are separate user-controlled
  steps. Review the target and current configuration before any authorized deploy.

No further feature implementation is requested by this handoff itself.

## 2026-10-14 production hardening

- `views/system/fatal_error_html.php` uses `helpers/public_errors.php` and a small
  standalone themed stylesheet. It never renders exception messages, stack traces,
  request parameters or file paths; missing/denied Gallery sets and native dispatch
  failures return 404, unexpected exceptions return 500, and explicit error statuses
  are preserved. Error responses are no-store/noindex and have a restrictive CSP.
  The ordinary native Error view also hides diagnostics and provides a home link.
- `controllers/GalleryController.php` is a guard shim, not a fork of upstream.
  Before requiring the native controller it requires an access-1, non-deleted,
  readable set of the configured gallery type. Numeric pages and native AJAX actions
  use the same guard. Native lightbox ownership/sharing and all stored set access
  remain unchanged. Keep user and gallery set types distinct.
- JPG/PDF/finding-aid failures use `helpers/export_diagnostics.php`: bounded numeric
  IDs, allowed format, exception class and basename/line go to PHP's server log;
  exception messages, full paths and request data are never included. Existing
  safe 503 responses remain.
- Routine deployment is rsync only, with no cache purge, Apache reload or configtest.
  It requires OPcache timestamp validation for PHP file changes. The existing user
  edit removing reloads is now incorporated in source, tests and documentation.
- `.github/workflows/checks.yml` runs PHP 8.3/Node 24 standalone checks, with pinned
  official actions and read-only permissions. It has no deployment credentials.
- `tests/production_hardening_test.php` covers actual Gallery guard/shim behavior,
  real local HTTP error statuses/headers and safe diagnostic logging using synthetic
  fixtures. [Production checks](PRODUCTION_CHECKS.md) distinguishes these from
  the controlled authenticated/live-export pilot.

The root robots policy was updated separately on 2026-10-14 after a private backup;
its public HTTP response matched the source policy including all four new contents
route exclusions. No Apache reload, cache clear or theme deployment was performed
for that root-file update. The authenticated pilot still requires designated
disposable accounts; it has not been performed by the standalone suites.

## 2026-10-16 404 recovery page

The native `DefaultController` returns only "Page is not available" when an
unknown URL has no Site Page or theme view. It bypasses `system/error_html.php`.
The shared header now calls `tadlPrepareNotFoundPage()` after native dispatch,
before PageFormat prepends the header: HTML status-404 responses have only their
default body segment replaced, preserving status, notifications and the site shell.
Successful pages, redirects, JSON, AJAX and downloads are untouched. No native
controller or upstream patch is needed.

`views/system/error_content_html.php` supplies one shared recovery component for
those pages, the ordinary error view and standalone exception 404s. It offers a
labeled native GET MultiSearch form, Collections index, Advanced Search and home.
Native URL helpers preserve clean URLs and application subdirectories; standalone
failures without a request use root-aware `index.php` URLs. No failed URL or user
input is reflected. Server-error pages keep a simple home recovery link.

The scoped `errorcss` asset is registered only for full-page 404s and receives the
existing content version. Standalone errors load the same stylesheet directly.
Their CSP permits same-origin forms while continuing to block scripts and framing.
Page titles identify the 404, and no-store/noindex protections remain. The
production-hardening suite covers fallback decoration, preserved response segments,
non-page exclusions, URL modes, labels and standalone HTTP behavior. All 33 suites
passed locally. Browser QA with the shared component and captured public shell
verified phone/desktop layout, visible keyboard focus, no horizontal overflow and
an actual synthetic search submit; this does not bootstrap a live application.
Deployment remains a separate operator step with no cache purge or Apache reload.

## 2026-10-16 archivist feedback: entity addresses and place descendants

Entity addresses now use the structured field described above. Place details use
`PlaceContents/Objects/place_id/ID` for both inline related items and the full
results link. Each GET recomputes the selected place's complete readable branch
and resolves object relationships through batched SQL, with native object sorting,
deduplication, access, exports and media filtering retained. Paging/view/sort links
retain the root place and headings identify it.

`tadlReadableHierarchyBranches()` shares the existing batched collection branch
boundary with places, using the native hierarchy ID and nested-set bounds plus
parent-chain validation. Unreadable/deleted/private ancestors prune their whole
branch; a denied hierarchy bundle retains direct items only. Place media-only
browsing uses these same scopes before counts, paging and detail navigation.
No relationships, search indexes or production records are changed. Ordinary
keyword search keeps the native index behavior. The root robots source adds
`PlaceContents` exclusions; installing that root file remains a separate step.

Live examples requested: an entity whose public address is missing and a gallery
whose image link fails. The user will obtain both from the archivists. Keep this
follow-up open for post-deployment confirmation even after synthetic checks pass.

The gallery report was reproduced with an anonymous session: opening a gallery,
loading its item/info partials and then following its correct object URL returned
404, while a direct object visit succeeded. Native `ResultContext` includes
`GalleryController.php` from Detail to call a static return-to-results helper.
The theme's guard previously treated that inclusion as a Gallery dispatch with
no set ID. `tadlGuardGalleryRequest()` now guards only actual Gallery requests;
the public gallery type/access/deletion/readability checks remain intact for
numeric pages and AJAX actions. The hardening suite exercises both paths.

All six advanced-search forms and the query builder now submit GET. The header
search already used GET and now declares it explicitly. Advanced templates call
`tadlAdvancedSearchForm()` to change only the native opening form's method after
native compilation, preserving action URLs, field arrays, booleans, relationship
restrictions, hidden form metadata and widget scripts. The discovery pass still
emits the native form tag. Pawtucket reads advanced values through `$_REQUEST`,
so GET reaches the same native query builder. Result URLs and browser Back use
GET without a form-resubmission prompt. The current public object form produces
roughly 2.5 KB of encoded parameters for a short keyword search; no infrastructure
change or new query parser is involved. Ordinary very long URL limits still apply.
Account, contact, lightbox and preference forms retain their POST/CSRF behavior.
Verification: all 34 standalone suites passed. A synthetic browser preview of the
actual templates verified address wrapping at desktop/390px, GET search submission
and Back from a result to the same search without a resubmission prompt. A local
reference-tree check confirmed identical native advanced-query expressions,
boolean handling and saved form values for equivalent GET/POST inputs. These
checks do not deploy the theme or replace the pending archivist example checks.

### Large place branches: avoid compound Lucene queries

The initial implementation generated one `place_id` OR term per readable place.
Large branches hit PHP's 30-second execution limit in the native Lucene parser;
adding a database index cannot fix parsing time. The native hierarchy and
object/place relationship indexes are already present, and read-only inspection
confirmed that the relationship query itself completes well below a second.

`helpers/place_contents.php` now reads distinct object IDs from the relationship
table in batches of at most 500 places. Both place branches and objects pass
the shared record/type/source/bundle/Pawtucket ACL boundary. The automatically
discovered theme plugin `plugins/tadlPlaceContents/tadlPlaceContentsPlugin.php`
uses native `hookReplaceSearch` only for `PlaceContents/Objects`: a constant
wildcard plus an intrinsic object-ID result filter replaces the compound search.
Empty scopes use ID zero. Native configured type/base criteria, object access,
sorting, result context, renderer and exports remain in use. Unused facets are
not calculated. Result filters are not in native browse cache keys, so this
browse has its own context and always recomputes membership/permissions; it
never reloads caller-supplied browse keys. The short root reference is retained
only for native labels and navigation.

Deploy the complete theme including `plugins/`; no separate plugin activation
or upstream edit is required. The controller fails closed if the plugin is
unavailable. Other search/browse controllers are untouched. No database schema
change, cache purge, timeout increase or service restart is required.

All 34 standalone suites and changed-file PHP lint passed. Verification includes
an actual SQLite branch with 6,000 descendants and overlapping object links,
plus the existing privacy/media/pager regressions.
An anonymous native application probe, loading the proposed PHP into memory
without replacing installed files, verified the affected large branch's first
and second tile pages, list view, media-only results and inline AJAX rendering.
Those controller/render checks completed in approximately 0.4–0.9 seconds.
This is a server-side CLI probe, not a deployed HTTP response or a browser timing;
the live URLs still require post-deployment verification by the operator.

### Consistent object keyword result sorting

The header overview's object results use Relevance, while the old advanced
object configuration defaulted to Entity. Verification with a single keyword
confirmed identical complete object sets with different order, not a difference
in matching records.

Basic and advanced object result options now have identical labels, order,
field mappings and default directions: Identifier, Title, Date, Relevance and
Entity, all ascending. Title replaces advanced search's former Name label;
Entity remains available on both paths. Native date-range and related-name
sorting remain unchanged. The header preview still uses Relevance.

New advanced object form submissions and the overview's View all Objects link
explicitly pass `sort=Relevance` and `direction=asc`, overriding old saved sorts.
Later result-page Options, paging and browser Back retain their native sort
parameters/context. Non-object search routes retain their existing defaults,
and object Browse entries still start with Identifier ascending. Queries,
record access, media preferences and matching behavior are unchanged.

`tests/ui_consistency_test.php` verifies the actual rendered form's defaults
and matching sort configurations; `tests/media_preferences_test.php` verifies
object View all parameters without changing the other overview categories.
All 34 standalone suites and changed PHP lint passed. Native configuration
parsing and an anonymous native controller/render probe using the proposed
configuration only in process memory confirmed identical complete result sets
and order for the same keyword, with Relevance ascending on both paths despite
older saved sort settings. Installed files were not changed; deployment and
post-deployment HTTP confirmation remain separate operator steps.

### Contact form recipient

`conf/contact.conf` routes the Contact form and object Item Inquiry submissions
to `ask@tadl.org`. Native `ContactController` reads `contact_email`
through `caGetContactConfig()`. The placeholder recipient has been removed.
The homepage's Ask a Question action links to the native `Contact/Form` page,
using the same route as the navigation/footer Contact links.
Native configuration parsing was checked locally; email delivery has not been
tested. Deployment remains a separate operator step.

The email field enables native `use_as_from_address`, which despite its name
sets **Reply-To** rather than From in the current ContactController. Required
email validation runs before mail is sent; invalid addresses retain the form's
validation error and do not send a message. From remains the configured site
sender. This applies to Contact and Item Inquiry. Configuration/native-controller
checks use synthetic submissions and a captured mail call; no real email is sent.

`controllers/ContactController.php` now overrides the native controller to pair
the validated Reply-To email with the submitted `name`. The display name has
markup/entities and header control characters normalized, then the native mailer
receives an address-to-name map and handles MIME encoding/quoting. Recipient and
From remain configured values. Native form views, mail templates, required/email
checks, arithmetic and anonymous reCAPTCHA behavior remain. Send accepts POST
only and explicitly rejects a failed native CSRF check. The controller is derived
from Pawtucket's implementation; compare it with upstream ContactController when
upgrading. No upstream file or provider configuration is modified.

The subject template appends a fresh `CF-` reference generated from eight random
bytes for every submission, including Item Inquiry. This preserves the archive's
subject prefix while giving unrelated submissions different subjects for Gmail
conversation grouping. Names, email addresses and message text are not added to
subjects. Tests check fresh subject references and named Reply-To with captured
mail; live mailbox delivery/threading remains an operator verification step.
All 35 standalone suites and changed-file PHP/template lint passed. Local native
PHPMailer `preSend()` checks verified MIME-encoded named Reply-To and subject
headers, including quoted/Unicode names and header-control input, without invoking
a delivery transport. No deployment or live email send was performed.

The contact form's visible name label and the name label in notification emails
now say Your name; the submitted field remains `name`. The input uses its associated visible label
for its accessible name and supports browser name autofill.

Native contact CAPTCHA options are the `check_security` arithmetic check and
Google reCAPTCHA v2, enabled through `__CA_GOOGLE_RECAPTCHA_KEY__` and
`__CA_GOOGLE_RECAPTCHA_SECRET_KEY__` in private setup configuration. The
`registration_security` setting applies to account registration, not Contact.
The arithmetic view now honors `check_security` and is omitted when Turnstile is
enabled. `helpers/contact_turnstile.php` adds contact-only Cloudflare Turnstile
support, enabled by server-local `__TADL_TURNSTILE_SITE_KEY__`,
`__TADL_TURNSTILE_SECRET_KEY__` and `__TADL_TURNSTILE_HOSTNAMES__` constants in
private `setup.php`. See [Contact form setup](CONTACT_FORM.md) for widget creation,
two-host configuration, secret storage, rollback and operator checks. The intended
production hostnames are `archives.tadl.org` and `localhistory.tadl.org`; the latter
is a planned alias, not a verified current DNS destination.

With keys present, Turnstile replaces math and contact reCAPTCHA, retains the
signed-in exemption and verifies anonymous submissions after CSRF/field validation.
Partial setup fails closed for anonymous send. Siteverify requires strict success,
the `contact` action and an explicitly permitted hostname; fixed HTTPS, TLS checks,
bounded timeouts and no redirects keep the secret at Cloudflare. No form fields or
optional visitor IP are added to verification requests. Failures retain the form
and show retry guidance. The form omits the Having trouble email-help line, and
verification messages do not refer to it. The secret never enters view variables/HTML.
No changes to login/registration CAPTCHA, DNS or native core are included. Keys,
deployment and live delivery remain operator steps; standalone checks replace the
HTTP/mail boundaries and never transmit submissions.
All 35 standalone suites and changed-file PHP lint passed. A local browser preview
rendered Cloudflare's public test widget and checked phone widths without sending
mail or using production keys. Live verification/delivery on either hostname has
not been tested by Codex for this source change.

The subsequent missing-widget report was traced read-only to the live native
Content-Security-Policy: it omitted `challenges.cloudflare.com`, blocking the
embedded API script before a token field could be created. The tracked theme
`conf/headers.conf` now uses native `#!merge` to retain other security headers and
replaces both CSP script policies with the existing source list plus that exact
HTTPS origin. Existing child-frame behavior and frame-ancestor restrictions stay
unchanged. The default theme's `content_security_policy_include` setting is unused
by the current native header emitter, so it cannot fix this on its own. Contact
tests cover the policy and optionally native header parsing/merge preservation;
server-side token/host/action validation remains mandatory for anonymous mail.
The browser regression reproduced an empty container/no token under the native
policy, then rendered the public test widget and generated its token under the
merged policy. A read-only CLI probe on the production server confirmed HTTPS
Siteverify transport with Cloudflare's public dummy credentials (HTTP 200/test
success), without bootstrapping the application, using real keys or sending mail.
All 35 standalone suites passed; the optional native parser check also confirms
that the exact challenge origin is the only policy change. The fix still requires
the operator's normal theme deployment and a real anonymous submission check.

After the operator configured real keys, the widget reported Success. The remaining
console report includes a malformed native Feature-Policy, a root favicon 404,
Analytics request cancellations and diagnostics originating inside the Cloudflare
frame. `conf/headers.conf` now fixes the missing quote in `autoplay 'none'` and
omits unsupported `battery`/`document-domain` directives while preserving every
supported native restriction. The contact suite checks syntax and the exact native
merge delta. Cloudflare PAT/DNS probes can fail during successful challenges;
its iframe's `data:,` CSP/sandbox/font/GPU messages are not a reason to loosen the
parent site's CSP or iframe sandbox. An observed Analytics collection request
returned HTTP 204, with a separate canceled request; the user's pasted log alone
does not establish the cause of its Analytics failures. No real form was submitted.
The corrected header produced no Feature-Policy warnings in a local browser check.

The shared page header now explicitly links a theme-local SVG favicon, avoiding
the browser's implicit request for the missing root `/favicon.ico`. The icon reuses
the clocktower and clock-face paths from the existing `graphics/logo.svg` on the
theme's dark blue, without external assets or a root-file installation step. Local
browser checks confirmed rendering at 32 and 128 pixels. The UI suite checks the
header link and SVG parsing; all 35 suites and changed PHP lint passed. These
console-cleanup source changes are separate commits and are not deployed by Codex.
