# TADL theme for Pawtucket2

A public catalogue theme for [CollectiveAccess Pawtucket2](https://github.com/collectiveaccess/pawtucket2), built for the Traverse Area District Library's [Local History Collection](https://archives.tadl.org/). It brings together archival collection browsing, image-rich search results, linked people and places, document text, finding aids, and researcher lightboxes.

This repository contains the complete `tadl` theme. Install it in an existing Pawtucket application; it does not include Providence, Pawtucket itself, a database, or a metadata installation profile. Other institutions can use it as a starting point, but will need to adapt the branding, catalogue field mappings, and institutional links described below.

## What it can do

| Feature | Visitor experience |
| --- | --- |
| Responsive catalogue | Image tiles and list views, consistent detail metadata, mobile navigation, and expandable long descriptions that preserve links and formatting. |
| Collection browsing | A flat list of everything in a collection and its readable descendants, with an optional hierarchy view, bold titles for branches containing digitized media, original-format tags on item cards, alphabetical ordering, sorting, and pagination. |
| Connected records | Browse people, organizations, families, places, and events. Place results include descendant places; family results include objects linked to directly related people and organizations. |
| Media preference | Visitors choose **All items** or **Only items with media**. The choice is remembered and applies before result counts and pagination. New visitors start with All items. |
| Search | Header, advanced, and query-builder searches use GET so links and browser Back work without a form-resubmission prompt. Detail Prev / Next follows the result order. |
| Media and downloads | Native image, PDF, audio, and video viewers; original and generated image download choices; accessible secondary-image thumbnails when the primary representation is unavailable. Available formats depend on Pawtucket's media processing configuration. |
| Document text | A copyable text section for accessible PDFs, using extracted text first and imported transcription fields as fallbacks. |
| Finding aids | View or download a descriptive collection PDF with its complete readable hierarchy and each level's recorded extent, description, dates, creators, and other public metadata. Individually catalogued objects are not required. |
| Researcher accounts | Native login, profiles, password reset, lightboxes, and sharing, plus staff-only shortcuts to objects and archival units in Providence. Anonymous visitors see no login link. |
| Editable FAQ | Publish categorized questions and rich-text answers through Providence Site Pages, without redeploying the theme for content changes. |
| Thumbnail focus | Filled image crops respect Providence's **Set center** setting. Optional local face detection supplies suggestions where staff have not chosen a center. |
| Contact and recovery | A contact form with named Reply-To addresses and distinct subject references, optional Cloudflare Turnstile, and helpful error pages with search and browse links. |

The shared interface includes keyboard navigation, visible focus, reduced-motion support, responsive reflow, and accessible alternatives to dragging lightbox items. See the [accessibility guide](docs/ACCESSIBILITY.md) for coverage and remaining manual checks; installing the theme does not establish whole-site WCAG conformance.

## Before you install

- Start with a working **Providence and Pawtucket installation**, with Pawtucket connected to the intended shared database and media storage. Confirm the standard theme can display your records and derivatives first.
- The theme is developed against **Pawtucket 2.0.10**, with automated checks running on **PHP 8.3**. Follow your Pawtucket release's PHP, extension, web-server, and media-processing requirements. Other releases may require adaptation.
- Install Pawtucket's own dependencies normally. The theme has **no separate Composer install, npm build, or frontend build step** for ordinary use.
- Have access to Pawtucket's private `setup.php` and theme directory. Use a test installation to adapt the theme before making it your public catalogue.
- Check your installation profile's metadata and type codes. The theme does not create fields or convert your catalogue to TADL's schema.

Python, OpenCV, and a face-detection model are needed only for the optional detector. Redis is not a theme requirement.

## Install and enable

### 1. Clone into the themes directory

From your Pawtucket application root:

```sh
cd /path/to/pawtucket2
git clone https://github.com/tadl/tadl-pawtucket2-theme.git themes/tadl
```

Keep the directory named `tadl` for the documented setup. Copy the complete repository when deploying; its views, helpers, controllers, configuration, and assets work together.

### 2. Select the theme in setup.php

In Pawtucket's existing private `setup.php`, change its theme mapping to:

```php
$_CA_THEMES_BY_DEVICE = [
    '_default_' => 'tadl',
];
```

Edit the existing mapping before the final `app/helpers/post-setup.php` bootstrap; retain the rest of your application settings. If you have device-specific theme entries, update those too if you want this responsive theme on every device. Theme selection is made here, rather than by adding a `theme` option to `app.conf`.

Reload Pawtucket. The theme can now render against your existing catalogue, subject to the field mappings and content setup below. Selecting it does not require a database migration or an installation-profile import.

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

Metadata examples include `ca_collections.adminbiohist`, `date.dates_value`, `ca_objects.transcription`, and `ca_objects.pdf_text`. Review the configured bundles and detail templates against your own profile. Remap fields you use and remove displays you do not need. Some helpers skip missing optional fields, but that does not automatically translate the whole theme to a different profile.

Native record access, representation access, bundle permissions, and Pawtucket ACLs still determine what visitors can read. **All items** includes records without media; it does not make private records public. Publish the intended records and representations through your normal catalogue workflow.

## Set up optional features

### FAQ content

The FAQ uses native Providence Site Pages, with one `faq_entry` page per question. A one-time setup registers the supplied template and required editor placements. Follow [FAQ activation](docs/FAQ_SETUP.md), which provides an inspection command, explicit apply step, backup requirements, and upgrade instructions.

After setup, staff edit category, question, answer, order, and public access in **Providence → Manage → Content Management → Site pages**. Published, complete entries appear on the homepage; no entries means no FAQ section. Answer HTML is sanitized and editor-supplied inline styles are removed while paragraphs, links, and semantic formatting remain.

### Accounts and lightboxes

Bookmark `/LoginReg/LoginForm` beneath your Pawtucket base URL. Existing accounts can sign in even though the anonymous navigation has no login link. Self-registration is disabled by default; the public catalogue remains open.

Signed-in users get **My account** and **Add to lightbox** on object details. Result thumbnails omit that button. Staff Providence shortcuts on object and collection details are limited to native full-access accounts; Providence has its own login session and permissions. See [accounts and user features](docs/USER_FEATURES.md) before changing account settings or enabling additional native workflows.

### Contact mail and Turnstile

Configure the recipient in `conf/contact.conf` and your application's mail transport/sender. Submitted, validated email and name become Reply-To; messages receive distinct subject references.

Without Turnstile keys, the form uses the native arithmetic check. For Turnstile, register your hostnames and set the site key, secret, and allowed hostnames in private Pawtucket `setup.php`. The theme includes both the browser widget and server-side validation. Follow the [contact-form guide](docs/CONTACT_FORM.md) for exact settings and verification; do not commit keys to theme configuration.

### Focal points and local face detection

Providence's representation **Set center** works without installing another service. Staff choices take precedence over automatic suggestions, and thumbnails retain `object-fit: cover`.

For automatic suggestions, follow [thumbnail focus](docs/THUMBNAIL_FOCUS.md) to install a separate Python environment and the OpenCV YuNet model, configure durable storage outside `app/tmp`, and run the batch script. Detection uses a local model, makes no image-processing API calls, and does not identify people. It runs as a maintenance task, never during page loads. The [standalone package guide](support/thumbnail-faces/share/README.md) explains how to reuse just this feature in another theme.

### PDF text and finding aids

Document text requires at least one accessible attached PDF. The theme prefers populated `ca_object_representations.media_content`, then `ca_objects.transcription`, then `ca_objects.pdf_text`. It displays existing text; it does not run OCR, import transcriptions, or configure your search index. A private paired PDF does not expose text on a TIFF-only public object. See [document text](docs/DOCUMENT_TEXT.md).

Finding aids use Pawtucket's supplied Dompdf dependency and the mappings in `conf/finding_aid.conf`. **View finding aid** opens the top-level collection's aid from any level of its hierarchy in the existing PDF.js document viewer, where visitors can download the PDF. The aid describes the complete readable hierarchy using each archival unit's own populated metadata, independent of the visitor's media preference. Extents and descriptions come from staff cataloguing; the PDF has no catalogue-object counts or automatic object inventory. See [finding aids](docs/FINDING_AIDS.md) to adapt field mappings and verify exports.

### Crawler policy

Download links discourage following, binary downloads send indexing directives, and search/browse pages request exclusion from search indexes. The supplied [robots.txt](support/site-root/robots.txt) is a separate site-root installation: review and merge it with your existing policy using the [crawler guide](docs/CRAWLER_POLICY.md). A theme clone does not install that root file. These directives guide cooperative crawlers; they are not access controls.

## How it works

The theme extends Pawtucket through its native theme mechanism. PHP views provide presentation, theme controllers handle additional routes, and helpers share access-aware queries and rendering behavior. It uses the application's models, search engine, authentication, media derivatives, and bundled libraries; no Providence or Pawtucket core patch is needed for theme installation.

| Directory | Purpose |
| --- | --- |
| `conf/` | Native theme configuration, display mappings, routes, assets, and feature settings. |
| `views/` | Page templates, result layouts, detail pages, viewer wrappers, and PDF presentation. |
| `controllers/` | Theme endpoints for collection/place/family contents, finding aids, downloads, preferences, and account handling. |
| `helpers/` | Shared filtering, permission checks, hierarchy traversal, text processing, asset versions, and media behavior. |
| `assets/pawtucket/` | CSS, browser JavaScript, fonts, and graphics. |
| `templates/` | Native Site Pages templates, including the editable FAQ entry. |
| `support/` | Explicit maintenance/setup tools, deployment helper, and optional detector. |
| `docs/` and `tests/` | Feature guides and synthetic regression checks. |

Eligible records are filtered before theme counts and pagination, with batched lookups where appropriate. Keep `do_content_caching = 0` in `conf/app.conf` and collection `cache_timeout = 0`: rendered pages depend on the visitor's media preference and permissions, which native shared HTML cache keys do not fully represent. Other application caching can continue normally.

## Updating and deploying

Review upstream changes and merge them into your maintained fork or branch. Deploy the complete theme revision, preserving your private configuration and persistent detector data. For a clean, unmodified checkout tracking this repository, an update is:

```sh
git -C /path/to/pawtucket2/themes/tadl pull --ff-only
```

Local CSS and JavaScript URLs include content hashes, so visitors can use normal Reload after an update. Routine theme deployment preserves application caches and sessions and does not require clearing Redis, deleting `app/tmp`, or restarting Apache. PHP updates rely on your web runtime checking file timestamps: review `opcache.validate_timestamps` and `opcache.revalidate_freq`. Installations with timestamp checks disabled need their own bytecode-invalidation procedure.

The supplied `support/deploy-theme.sh` is an rsync helper for the `/var/www/pawtucket2/themes/tadl` layout. Review its target path and exclusions before using it elsewhere; it is not a general application installer. See [deployment](docs/DEPLOYMENT.md) and the [post-deployment checks](docs/PRODUCTION_CHECKS.md).

## Feature guides

| Guide | Details |
| --- | --- |
| [Collections](docs/COLLECTIONS.md) | Flat and hierarchy views, counts, result controls, and URL state. |
| [Families](docs/FAMILIES.md) | Related-member objects and media/access filtering. |
| [Finding aids](docs/FINDING_AIDS.md) | Descriptive PDF structure, metadata at every hierarchy level, field mappings and access. |
| [Document text](docs/DOCUMENT_TEXT.md) | Extraction/transcription fallbacks, formatting, permissions, and indexing boundaries. |
| [Image downloads](docs/IMAGE_DOWNLOADS.md) | Export formats, native processing requirements, private conversion cache, and limits. |
| [Accounts](docs/USER_FEATURES.md) | Login, profiles, lightboxes, staff tools, and FAQ presentation. |
| [FAQ activation](docs/FAQ_SETUP.md) | Repeatable Providence editor/template registration and upgrade preservation. |
| [Contact form](docs/CONTACT_FORM.md) | Mail settings, Reply-To, subject references, and Turnstile. |
| [Thumbnail focus](docs/THUMBNAIL_FOCUS.md) | Manual focal points, offline detection, durable storage, and reusable package. |
| [Accessibility](docs/ACCESSIBILITY.md) | Interface coverage, browser checks, and manual acceptance work. |
| [Crawler policy](docs/CRAWLER_POLICY.md) | Indexing directives and separate root robots setup. |

## Development and verification

The standalone regression suites use synthetic data and do not require an application database. Run them from the theme root with PHP CLI (DOM, PDO SQLite, JSON, mbstring, fileinfo, and `proc_open`), Bash, Node.js, and Python 3.11+:

```sh
(
  for test_file in tests/*_test.php; do
    php "$test_file" || exit 1
  done
)
```

These are development tools, not extra requirements for serving the ordinary theme. GitHub Actions runs syntax checks, the standalone suites, and browser accessibility checks. See the [accessibility guide](docs/ACCESSIBILITY.md) for the separate local browser-test setup.

Before making a customized site public, check collection and authority results, both media preferences, narrow-screen navigation, and access restrictions with representative records. Verify login, password reset, lightbox ownership/sharing, contact delivery, and exports in a controlled environment. The [production checklist](docs/PRODUCTION_CHECKS.md) covers these application-level checks; standalone tests cannot confirm your mail, media tools, catalogue mappings, or server configuration.
