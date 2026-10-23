# TADL theme for Pawtucket2

A public catalogue theme for [CollectiveAccess Pawtucket2](https://github.com/collectiveaccess/pawtucket2), built for the Traverse Area District Library's [Local History Collection](https://archives.tadl.org/). It brings together archival collection browsing, image-rich search results, linked people and places, document text, and finding aids.

This repository contains the complete `tadl` theme. Install it in an existing Pawtucket application; it does not include Providence, Pawtucket itself, a database, or a metadata installation profile. Other institutions can use it as a starting point, but will need to adapt the branding, catalogue field mappings, and institutional links described below.

## What it can do

| Feature | Visitor experience |
| --- | --- |
| Responsive catalogue | Compact logo/header, image tiles and list views with square file-format tags stacked to the right of object titles, consistent 3:2 search/grid thumbnails, collapsible filter sidebars, mobile navigation, and expandable long descriptions that preserve links and formatting. |
| Collection browsing | A flat list of everything in a collection and its readable descendants, with an optional hierarchy view, bold titles for branches containing digitized media, alphabetical ordering, sorting, and pagination. Subsequent item pages omit repeated descriptive metadata. |
| Featured galleries | Large, uncropped photos beside object metadata, with Previous/Next controls, a thumbnail strip, and media-viewer/download actions beneath the photo. The layout stacks on phones. |
| Connected records | Browse people, organizations, families, places, and events. Place results include descendant places; family results include objects linked to directly related people and organizations. |
| Media preference | A compact dropdown beneath search shows the current choice: **All items** or **Only items with media**. The choice is remembered and applies before result counts and pagination. New visitors start with All items. |
| Display preferences | Paged collection, Browse and Search views start with 18 tiles or 25 list items. Grids of collection records offer nine-item steps up to 90 and remember their own page size; other result grids go up to 72. List offers 25, 50 or 100. View, separate page sizes and flat/hierarchy choices are remembered. “All” applies only to a small result set and remembers the safe default for the next set. |
| Sorting | **Sort by Title** (or the current field) opens a compact list of clickable fields. The active field has a direction tag on the right: ↑ ascending (first to last), ↓ descending (last to first). Click it again to reverse the order. Sorting also works without JavaScript. |
| Search | Header, advanced, and query-builder searches use GET so links and browser Back work without a form-resubmission prompt. Native facets filter results by object type and other configured catalogue fields. Object filter counts follow the active media preference. |
| Record navigation | Details opened from results offer **Back to people**, **Back to places**, **Back to collections**, or the corresponding source before **Previous / Next**. Return links preserve the originating page, filters, sort, display and page size, including after moving between records. Direct visits retain a general Browse link where available. Object buttons sit above the metadata, aligned right on desktop and centered on phones, with contextual tooltips and accessible labels. |
| Media and downloads | Native image, PDF, audio, and video viewers with compact Close buttons; original and generated image download choices; accessible secondary-image thumbnails when the primary representation is unavailable. PDF title controls sit above the document toolbar. Available formats depend on Pawtucket's media processing configuration. |
| Document text | A copyable text section for accessible PDFs, using extracted text first and imported transcription fields as fallbacks. |
| Finding aids | View or download a descriptive collection PDF with its complete readable hierarchy and each level's recorded extent, description, dates, creators, and other public metadata. Individually catalogued objects are not required. |
| Staff accounts | Native login, profiles and password reset, plus staff-only shortcuts to objects and archival units in Providence. Anonymous visitors see no login link. Item sets are disabled pending a future public researcher-account feature. |
| Editable FAQ | Publish categorized questions and rich-text answers through Providence Site Pages, without redeploying the theme for content changes. |
| Thumbnail focus | Filled image crops respect Providence's **Set center** setting. Optional local face detection supplies suggestions where staff have not chosen a center. |
| Contact and recovery | A contact form with named Reply-To addresses and distinct subject references, optional Cloudflare Turnstile, and helpful error pages with search and browse links. |

Catalogue result lists are rendered in the initial HTML. Search, browse, collection contents, related-item previews and gallery previews do not need a second request to load their cards. Paging and the collection hierarchy browser use ordinary links. Tiles/List, collection view and page-size preferences persist between views; basic result navigation also works without JavaScript. Media viewers and account/editing dialogs still use the native interactive features.

Catalogue result exports to PDF, Excel, and PowerPoint are disabled, including direct requests to old export URLs. Search, Browse, and collection/place/family result pages reject export requests with HTTP 410 before running the result query. Finding-aid and individual-media downloads remain available. All legacy Lightbox routes, including add forms and exports, return HTTP 404.

Where a Search or Browse page has filters, a sidebar button hides them and lets results fill the available width. **Filters** beside Tiles/List reopens the sidebar. Selected filters stay above the results. The sidebar starts open on desktop and closed on phones, and remembers an explicit choice with the other display preferences. Without JavaScript, the filter links remain visible.
Additional facet values use separate **View X more / View less** buttons. Large lists offer **View all X** to open an alphabetized filter chooser with letter shortcuts, scrollable choices, and keyboard access.

File-format tags describe the accessible original representations attached to an object, such as JPEG, TIFF, or PDF. They can show multiple formats for one object. The **Object type** filter instead uses the catalogue's classification, such as Still image or Document; it does not infer an object's type from its files.

The shared interface includes keyboard navigation, visible focus, reduced-motion support, and responsive reflow. See the [accessibility guide](docs/ACCESSIBILITY.md) for coverage and remaining manual checks; installing the theme does not establish whole-site WCAG conformance.

## Requirements before installing

- Start with a working **Providence and Pawtucket installation**, with Pawtucket connected to the intended shared database and media storage. Confirm the standard theme can display your records and derivatives first.
- The tested combination is **Pawtucket 2.0.10 and PHP 8.3**. The theme uses PHP 8.1+ APIs, but your complete installation must also satisfy your Pawtucket release's PHP and Composer dependency requirements. Other releases may require adaptation.
- Install Pawtucket's own dependencies and media-processing tools normally. The theme uses native libraries, including Dompdf, and PHP extensions including **DOM/XML, mbstring, fileinfo, and zlib**. It has **no separate Composer install, npm build, or frontend build step** for ordinary use.
- The web PHP user must be able to read the theme, media originals and derivatives, and write to the application's normal cache paths and PHP temporary directory. Generated downloads use private temporary files. The theme source itself does not need to be writable by PHP.
- Have access to Pawtucket's private `setup.php` and theme directory. Use a test installation to adapt the theme before making it your public catalogue.
- Check your installation profile's metadata and type codes, and publish the records and representations intended for visitors. The theme does not create fields, import records, generate media derivatives, or convert your catalogue to TADL's schema.

**No Redis server, extra database index, core patch, or separate Providence plugin installation is required to use the theme.** Python, OpenCV, and a face-detection model are needed only for optional automatic thumbnail focus. Additional feature requirements are listed below.

## Install and enable

### 1. Clone into the themes directory

From your Pawtucket application root:

```sh
cd /path/to/pawtucket2
git clone https://github.com/tadl/tadl-pawtucket2-theme.git themes/tadl
```

Keep the directory named `tadl` for the documented setup. Copy the complete repository when deploying, including `plugins/`; its views, helpers, controllers, configuration, plugins, and assets work together. Pawtucket automatically discovers the included theme plugin. It does not need to be copied into Providence or installed separately.

### 2. Select the theme in setup.php

In Pawtucket's existing private `setup.php`, change its theme mapping to:

```php
$_CA_THEMES_BY_DEVICE = [
    '_default_' => 'tadl',
];
```

Edit the existing mapping before the final `app/helpers/post-setup.php` bootstrap; retain the rest of your application settings. If you have device-specific theme entries, update those too if you want this responsive theme on every device. Theme selection is made here, rather than by adding a `theme` option to `app.conf`.

Reload Pawtucket. The theme can now render against your existing catalogue, subject to the field mappings and content setup below. Selecting it does not require a database migration or an installation-profile import.

### 3. Configure your catalogue and choose extra features

Adapt the branding, field/type mappings, contact address, and institutional links in the next section before making the site public. Enabling the theme does not provision the following services, content, or optional integrations:

| Feature | Requirements beyond cloning and selecting the theme |
| --- | --- |
| Homepage slideshow and galleries | Populate public native sets with readable objects/media and the configured set codes/types. Large gallery photos need existing `large` image derivatives. Missing featured content is omitted; smaller gallery previews are used when no large derivative exists. |
| Finding-aid PDFs | Pawtucket's installed Dompdf dependency, native PDF.js assets, a readable collection hierarchy, and metadata mappings for your profile. No separate PDF service is needed. Missing metadata is omitted; a missing PDF renderer prevents PDF generation. See [finding aids](docs/FINDING_AIDS.md). |
| Generated JPG and single-image PDF downloads | A working native image processor that can read the original format and write JPEG, readable originals, and writable PHP temporary storage; PDF conversion also uses Dompdf. Unsupported conversions cannot be generated. The default private conversion cache is created automatically; custom durable storage needs separate provisioning outside all web roots. See [image downloads](docs/IMAGE_DOWNLOADS.md). |
| Document text | Text already extracted into representation `media_content` or imported into the configured transcription fields. Configure native extraction tools and process/reprocess media separately; OCR and search indexing are separate application tasks. With no usable text, the section is omitted. See [document text](docs/DOCUMENT_TEXT.md). |
| Editable FAQ | An explicit one-time **Providence database setup** registers the template and editor placements, followed by staff-created, published Site Pages. Inspect the existing Site Pages editor and take the required private backup before applying the setup. Cloning alone creates no FAQ editor or questions. See [FAQ activation](docs/FAQ_SETUP.md). |
| Contact mail | Your own recipient, sender, and working application mail transport. Verify actual delivery; installing the theme does not configure mail. Optional Turnstile additionally needs a registered widget, private keys/hostname allowlist, PHP cURL with working TLS, browser/server HTTPS access to Cloudflare, and compatible CSP rules. See [contact setup](docs/CONTACT_FORM.md). |
| Automatic thumbnail focus | A separate Python 3.11+ environment with the pinned OpenCV dependencies, verified YuNet model/license, PHP CLI with `proc_open`, existing JPEG derivatives, and a writable durable cache outside the application and web roots. Run the detector explicitly; no processing service or scheduler is installed. Manual **Set center** works without this setup. See [thumbnail focus](docs/THUMBNAIL_FOCUS.md). |
| Shared result-data caching | Uses Pawtucket's configured cache backend; Redis is optional. This optimization requires the `mysqli` database driver; unsupported contexts use native computation. The optional MySQL 8 covering index is a separate administrator-applied schema change, not an installation step. See [performance setup](docs/PERFORMANCE.md). |
| Site-wide crawler policy | Review, merge, and install the supplied `robots.txt` at the site root separately. A theme directory cannot install the root policy. See [crawler setup](docs/CRAWLER_POLICY.md). |

## Make it your own

The shipped site copy, logos, email recipient, external feeds, and Providence shortcut belong to TADL. Replace them before opening another institution's site to visitors. Keep your customizations in your own fork or branch so upgrades can be reviewed and merged.

| What to adapt | Where to look |
| --- | --- |
| Site identity, navigation, footer, and social metadata | [Shared header](views/pageFormat/pageHeader.php), [footer](views/pageFormat/pageFooter.php), and graphics under `assets/pawtucket/graphics/`. |
| Homepage and About text | [Homepage](views/Front/front_page_html.php) and [About view](views/About/Index.php). The About route uses this view; `templates/about.tmpl` is a separate Site Pages template. |
| Colors, typography, and layout | [Theme CSS](assets/pawtucket/css/theme.css), [accessibility CSS](assets/pawtucket/css/accessibility.css), and [asset configuration](conf/assets.conf). |
| Featured homepage objects | [Front configuration](conf/front.conf) and the `front_page_set_code` in [app configuration](conf/app.conf). Both ship with `frontPage`; create/populate that native set or change the code. Without usable featured media, the slideshow is omitted. |
| Recent writing and research links | The homepage contains TADL article cards and a research-request link. [Recent-writing JavaScript](assets/pawtucket/js/recent-writing.js) fetches a TADL feed and validates TADL URLs. Replace the feed, URL validation, fallback cards, and related [CSP origins](conf/headers.conf), or remove that section and its asset. |
| Fields, facets, labels, and sorts | [Browse](conf/browse.conf), [search](conf/search.conf), [detail](conf/detail.conf), and [collection](conf/collections.conf) configuration, plus templates under `views/Details/` and `views/Search/`. |
| Finding-aid metadata | [Field mappings](conf/finding_aid.conf) and [PDF view](views/Details/finding_aid_pdf_html.php), including its institutional identification. |
| Contact recipient | [Contact configuration](conf/contact.conf) ships with TADL's address. Replace it and configure your application's outgoing mail before enabling public submissions. |
| Staff editor shortcuts | [User-feature helper](helpers/user_features.php) contains TADL's Providence hostname, `Screen49` object-editor route, and default-screen collection-editor route. Adapt these to your installation. |
| Gallery sets | [Gallery configuration](conf/gallery.conf) expects set type `public_presentation`. Use a matching type and published sets, or adapt the setting. |
| Security headers and outside services | Review [headers.conf](conf/headers.conf) for the providers your site actually uses. Private application credentials belong in server configuration, never in this repository. |

### Match your catalogue schema

CollectiveAccess installation profiles vary. In this theme, People, Organizations, and Families use entity type codes `ind`, `org`, and `fam`. These appear in browse configuration and in [family-content helpers](helpers/family_contents.php); changing only a display label will not change their behavior.

In [collection configuration](conf/collections.conf), `landing_page_collection_type = collection` identifies the top-level archival collection type. Match that value to your profile so collection-root traversal and hierarchy navigation find the intended base collection. Child containers, series, and folders remain separate archival units beneath it.

Metadata examples include `ca_collections.adminbiohist`, `date.dates_value`, `ca_objects.transcription`, and `ca_objects.pdf_text`. Review the configured bundles and detail templates against your own profile. Remap fields you use and remove displays you do not need. Some helpers skip missing optional fields, but that does not automatically translate the whole theme to a different profile.

Native record access, representation access, bundle permissions, and Pawtucket ACLs still determine what visitors can read. **All items** includes records without media; it does not make private records public. Publish the intended records and representations through your normal catalogue workflow.

For the public **Object type** filter, the intended entries in Providence's `object_types` list must also be enabled and public. If the filter is missing, check those entries and the native `ca_objects.type_id` facet in Pawtucket's application `app/conf/browse.conf`. Only types with matching accessible objects are offered. Publishing a type entry does not change access on its objects or representations.

## Set up optional features

### FAQ content

The FAQ uses native Providence Site Pages, with one `faq_entry` page per question. A one-time setup registers the supplied template and required editor placements. Follow [FAQ activation](docs/FAQ_SETUP.md), which provides an inspection command, explicit apply step, backup requirements, and upgrade instructions.

After setup, staff edit category, question, answer, order, and public access in **Providence → Manage → Content Management → Site pages**. Published, complete entries appear on the homepage; no entries means no FAQ section. Answer HTML is sanitized and editor-supplied inline styles are removed while paragraphs, links, and semantic formatting remain.

### Staff accounts and deferred item sets

Bookmark `/LoginReg/LoginForm` beneath your Pawtucket base URL. Existing accounts can sign in even though the anonymous navigation has no login link. Self-registration is disabled by default; the public catalogue remains open.

Signed-in users get **My account**, with profile and logout actions. Lightboxes are disabled for everyone: add buttons and menu entries are removed, and direct Lightbox requests return 404. A future public researcher-account feature may introduce **item sets**; changing a configuration flag alone does not enable it. Staff Providence shortcuts on object and collection details are limited to native full-access accounts; Providence has its own login session and permissions. See [accounts and user features](docs/USER_FEATURES.md).

### Galleries

Create a public set of the type configured by `gallery_set_type` in `conf/gallery.conf` (shipped as `public_presentation`), add readable objects and representations, and arrange the items in the set. The homepage's featured set is omitted from the gallery index by default; `omit_front_page_set_from_gallery` controls that choice.

Gallery photos use the selected representation's existing `large` image derivative when available, falling back to Pawtucket's normal preview otherwise. They fill the image area without cropping or stretching, with a height limit for tall images. The theme does not generate larger derivatives during page loads; their resolution comes from your media-processing configuration. Visitors can open the media viewer for closer inspection or follow the photo to its object record.

### Contact mail and Turnstile

Configure the recipient in `conf/contact.conf` and your application's mail transport/sender. Submitted, validated email and name become Reply-To; messages receive distinct subject references.

Without Turnstile keys, the form uses the native arithmetic check, or native reCAPTCHA if already configured. For Turnstile, register your hostnames and set the site key, secret, and allowed hostnames in private Pawtucket `setup.php`. Both keys and a valid hostname allowlist are required; partial configuration blocks anonymous sending. The theme includes both the browser widget and server-side validation. Follow the [contact-form guide](docs/CONTACT_FORM.md) for exact settings and verification; do not commit keys to theme configuration.

### Focal points and local face detection

Providence's representation **Set center** works without installing another service. Staff choices take precedence over automatic suggestions, and thumbnails retain `object-fit: cover`.

For automatic suggestions, follow [thumbnail focus](docs/THUMBNAIL_FOCUS.md) to install a separate Python environment and the OpenCV YuNet model, configure durable storage outside `app/tmp`, and run the batch script. Detection uses a local model, makes no image-processing API calls, and does not identify people. It runs as a maintenance task, never during page loads. The [standalone package guide](support/thumbnail-faces/share/README.md) explains how to reuse just this feature in another theme.

### PDF text and finding aids

Document text requires at least one accessible attached PDF. The theme prefers populated `ca_object_representations.media_content`, then `ca_objects.transcription`, then `ca_objects.pdf_text`. Complete text loads when its disclosure opens, with a normal page link as a fallback. It displays existing text; it does not run OCR, import transcriptions, or configure your search index. A private paired PDF does not expose text on a TIFF-only public object. See [document text](docs/DOCUMENT_TEXT.md).

Finding aids use Pawtucket's supplied Dompdf dependency and the mappings in `conf/finding_aid.conf`. **View finding aid** opens the top-level collection's aid from any level of its hierarchy in the existing PDF.js document viewer, where visitors can download the PDF or choose an equivalent structured HTML version with **Read as text**. The aid describes the complete readable hierarchy using each archival unit's own populated metadata, independent of the visitor's media preference. Extents and descriptions come from staff cataloguing; the PDF has no catalogue-object counts or automatic object inventory. See [finding aids](docs/FINDING_AIDS.md) to adapt field mappings and verify exports.

### Crawler policy

Download links discourage following, binary downloads send indexing directives, and search/browse pages request exclusion from search indexes. The supplied [robots.txt](support/site-root/robots.txt) is a separate site-root installation: review and merge it with your existing policy using the [crawler guide](docs/CRAWLER_POLICY.md). A theme clone does not install that root file. These directives guide cooperative crawlers; they are not access controls.

## How it works

The theme extends Pawtucket through its native theme mechanism. PHP views provide presentation, theme controllers handle additional routes, and helpers share access-aware queries and rendering behavior. It uses the application's models, search engine, authentication, media derivatives, and bundled libraries; no Providence or Pawtucket core patch is needed for theme installation.

Native detail-page view tracking is disabled (`record_detail_views = 0` in `conf/detail.conf`). This avoids a view-counter database write that can block public pages during Providence imports. It also disables the native recently-viewed list; the theme uses neither statistic. Web-server access logs still record requests. See [performance](docs/PERFORMANCE.md) before opting back into native tracking.

| Directory | Purpose |
| --- | --- |
| `conf/` | Native theme configuration, display mappings, routes, assets, and feature settings. |
| `views/` | Page templates, result layouts, detail pages, viewer wrappers, and PDF presentation. |
| `controllers/` | Theme endpoints for collection/place/family contents, finding aids, downloads, preferences, and account handling. |
| `helpers/` | Shared filtering, permission checks, hierarchy traversal, text processing, asset versions, and media behavior. |
| `plugins/` | Automatically discovered native theme hooks for related collection/place results; deploy with the rest of the theme. |
| `assets/pawtucket/` | CSS, browser JavaScript, fonts, and graphics. |
| `templates/` | Native Site Pages templates, including the editable FAQ entry. |
| `support/` | Explicit maintenance/setup tools, deployment helper, and optional detector. |
| `docs/` and `tests/` | Feature guides and synthetic regression checks. |

Eligible records are filtered before theme counts and pagination, with batched lookups where appropriate. Keep `do_content_caching = 0` in `conf/app.conf` and collection `cache_timeout = 0`: rendered pages depend on the visitor's media preference and permissions, which native shared HTML cache keys do not fully represent. Other application caching can continue normally. PDF bytes and media-descriptor validity have separate content-keyed native caches. Media validity uses batches of up to 500 representations per cache lookup. Current descriptor fingerprints and visibility are checked on every request; unchanged descriptor validity can remain warm for a day.

Media cache buckets also work across overlapping searches and sort orders. Object grids and lists fetch primary thumbnails for the displayed page in batches, with native permission checks and fallback images preserved. Returning to a distant result jumps to its page; a missing or outdated return target cannot expand one response into thousands of cards. These optimizations require no additional service, database index or cache purge.

Anonymous object browsing also shares result IDs, ordering, supported filter choices, media eligibility and adjusted counts through the application's existing cache backend, including Redis when configured. Entries expire after an hour and are reused only after checking current catalogue changes, visibility flags, labels, hierarchy, relationships and media fingerprints. Pages and session controls are generated normally. Restricted sessions retain native computation. Full-text queries still execute against the current search index; large object searches and collection contents can reuse media eligibility and adjusted counts.

The result-data cache requires MySQL through the `mysqli` driver and can be disabled with `disable_tadl_result_cache = 1` in `conf/app.conf`. After direct SQL metadata maintenance that bypasses native change logs, increment `tadl_result_cache_epoch` there. No Redis flush is needed. An optional MySQL 8 covering index avoids reading every media descriptor just to fingerprint it; the theme detects it and otherwise uses ordinary native hashing. See [performance and optional database setup](docs/PERFORMANCE.md).

## Updating and deploying

Review upstream changes and merge them into your maintained fork or branch. Deploy the complete theme revision, preserving your private configuration and persistent detector data. For a clean, unmodified checkout tracking this repository, an update is:

```sh
git -C /path/to/pawtucket2/themes/tadl pull --ff-only
```

The theme includes Browse and Detail controller overrides based on Pawtucket 2.0.10. When upgrading Pawtucket itself, review compatibility of those overrides, the native APIs, and the configuration mappings before deploying the application upgrade.

Local CSS and JavaScript URLs include content hashes, so visitors can use normal Reload after an update. Routine theme deployment preserves application caches and sessions and does not require clearing Redis, deleting `app/tmp`, or restarting Apache. PHP updates rely on your web runtime checking file timestamps: review `opcache.validate_timestamps` and `opcache.revalidate_freq`. Installations with timestamp checks disabled need their own bytecode-invalidation procedure.

The supplied `support/deploy-theme.sh` is an rsync helper for the `/var/www/pawtucket2/themes/tadl` layout. Review its target path and exclusions before using it elsewhere; it is not a general application installer. See [deployment](docs/DEPLOYMENT.md) and the [post-deployment checks](docs/PRODUCTION_CHECKS.md).

## Feature guides

| Guide | Details |
| --- | --- |
| [Collections](docs/COLLECTIONS.md) | Flat and hierarchy views, counts, result controls, and URL state. |
| [Families](docs/FAMILIES.md) | Related-member objects and media/access filtering. |
| [Finding aids](docs/FINDING_AIDS.md) | Descriptive PDF structure, metadata at every hierarchy level, field mappings and access. |
| [Audit remediation](docs/AUDIT_REMEDIATION.md) | Review findings, source fixes, verification and deployment boundaries. |
| [Performance](docs/PERFORMANCE.md) | Cache freshness, optional MySQL index, measurement, and rollback. |
| [Performance improvements](docs/PERFORMANCE_IMPROVEMENTS.md) | Completed optimizations, measured gains, validation, experiments, and deployment record. |
| [Document text](docs/DOCUMENT_TEXT.md) | Extraction/transcription fallbacks, formatting, permissions, and indexing boundaries. |
| [Image downloads](docs/IMAGE_DOWNLOADS.md) | Export formats, native processing requirements, private conversion cache, and limits. |
| [Accounts](docs/USER_FEATURES.md) | Staff login, profiles, disabled item sets, staff tools, and FAQ presentation. |
| [FAQ activation](docs/FAQ_SETUP.md) | Repeatable Providence editor/template registration and upgrade preservation. |
| [Contact form](docs/CONTACT_FORM.md) | Mail settings, Reply-To, subject references, and Turnstile. |
| [Thumbnail focus](docs/THUMBNAIL_FOCUS.md) | Manual focal points, offline detection, durable storage, and reusable package. |
| [Accessibility](docs/ACCESSIBILITY.md) | Interface coverage, browser checks, and manual acceptance work. |
| [Crawler policy](docs/CRAWLER_POLICY.md) | Indexing directives and separate root robots setup. |

## Development and verification

The standalone regression suites use synthetic data and do not require an application database. They are checked with PHP 8.3 CLI (DOM, PDO SQLite, JSON, mbstring, fileinfo, zlib, and `proc_open`), Bash, Node.js, and Python 3.11+. Run them from the theme root:

```sh
(
  for test_file in tests/*_test.php; do
    php "$test_file" || exit 1
  done
)
```

These are development tools, not extra requirements for serving the ordinary theme. GitHub Actions runs syntax checks, the standalone suites, and browser accessibility checks. See the [accessibility guide](docs/ACCESSIBILITY.md) for the separate local browser-test setup.

Before making a customized site public, check collection and authority results, both media preferences, narrow-screen navigation, and access restrictions with representative records. Verify staff login, password reset, disabled Lightbox routes, contact delivery, and media downloads in a controlled environment. The [production checklist](docs/PRODUCTION_CHECKS.md) covers these application-level checks; standalone tests cannot confirm your mail, media tools, catalogue mappings, or server configuration.

The sample contribution form is disabled (`conf/contribute.conf`, `enabled = 0`), including direct named-form routes. To accept contributions, configure an institution-specific mapping, review upload/access policies, and explicitly enable both the submission interface and contribution controller.
