# tadl-pawtucket2-theme

Standalone source repository for the `tadl` Pawtucket2 theme used for TADL local history.

For Codex or a new workstation, start with [AGENTS.md](AGENTS.md) and the
[current handoff](docs/CODEX_HANDOFF.md). The handoff explains the source map,
recent changes, design decisions and all thirty-four standalone regression suites.
It uses relative paths and does not require an application database for those tests.

Feature guides:

| Guide | Covers |
| --- | --- |
| [Collections](docs/COLLECTIONS.md) | Flat and hierarchy views, navigation, inline counts, configuration and rollout checks |
| [Families](docs/FAMILIES.md) | Family browsing, directly related member items and access/media filtering |
| [Finding aids](docs/FINDING_AIDS.md) | PDF contents, count semantics, field mappings and export permissions |
| [Document text](docs/DOCUMENT_TEXT.md) | PDF extraction, imported transcription fallbacks and plain-text display |
| [Image downloads](docs/IMAGE_DOWNLOADS.md) | Full-resolution exports, private conversion cache, permissions and limits |
| [Accounts and FAQ](docs/USER_FEATURES.md) | Bookmarked login, staff tools, lightboxes and content editing |
| [FAQ activation](docs/FAQ_SETUP.md) | Repeatable Providence template/editor setup and upgrade preservation |
| [Thumbnail focus](docs/THUMBNAIL_FOCUS.md) | Manual focal points and durable local face-detection suggestions |
| [Crawler policy](docs/CRAWLER_POLICY.md) | Download/search directives and separate root robots installation |

Deployment is a separate, explicitly authorized step. A source commit/push does
not authorize running the enclosing deployment helper or restarting production.
The versioned `support/deploy-theme.sh user@host` contains the deployment steps;
the enclosing helper delegates to it with the operator's local target. Routine
deployment copies the theme without reloading Apache; PHP OPcache timestamp
validation must be enabled.
It leaves application caches, sessions and `app/tmp` intact, regardless of whether
the application uses Redis or file caching. See [Deployment](docs/DEPLOYMENT.md).

Download links discourage crawler following, binary downloads send `noindex`
response headers, and search/browse pages stay out of search indexes. A replacement
root `robots.txt` covers clean and `index.php` routes while keeping public records
and thumbnails crawlable. The root file requires a separate installation after
theme deployment; see [Crawler policy](docs/CRAWLER_POLICY.md).

Rollout after authorization:

1. Verify, commit and push a coherent theme revision to the source remote.
2. Review the target and its local configuration, then deploy the complete revision
   through the established operator workflow. Preserve server-local overrides and
   persistent thumbnail-focus data.
3. Follow the target's PHP/runtime cache policy. Cache clearing and service restarts
   are separate operational steps; this source repository does not authorize them.
4. Run the feature's post-deployment checks. Root `robots.txt`, FAQ activation and
   the thumbnail detector runtime have separate documented installation steps.

Asset content versions handle browser CSS/JS caching; clearing `app/tmp` is not
the browser cache-busting mechanism. Exceptional cache maintenance is separate
from theme deployment. Before an explicitly authorized temporary-directory purge,
follow the legacy thumbnail-cache preservation/migration instructions in the
[thumbnail guide](docs/THUMBNAIL_FOCUS.md).

Theme CSS and JavaScript URLs include automatic content versions. After deploying
changed files, visitors can use normal Reload to get the new assets; no hard
refresh or manual version bump is needed. Unchanged files retain their URLs and
remain cacheable. The shared header and standalone lightbox presentation use
`helpers/asset_versions.php` around the native asset loader. This versions local
theme CSS/JS only, preserving native load order and any configured `asset_suffix`.

Long detail metadata fields show a short preview with **Read more / Read less**.
This includes collection descriptions and scope/content, object descriptions,
authority biographies and other shared text fields, plus media captions. Short
fields remain fully visible. Native disclosures work without JavaScript, retain
the complete formatted text and links, and preserve existing access checks.
Collapsed rich-text excerpts preserve links and emphasis. Long linked metadata
lists show five complete entries with their relationship roles and line breaks;
the remaining entries are available under Read more. Rich excerpts contain only
visible nodes, so CSS never clips a link that can still receive keyboard focus.

Collection contents keep paging, sorting and Tiles/List controls on the collection
detail URL. By default, the list includes the selected collection and all readable
descendants, with objects shown once even when attached to multiple subcollections.
Collections with readable children offer **View collection hierarchy** below
**Download finding aid**, restoring the collection browser and directly attached
items; **View all collection items** returns to the flat list. Sorting, paging,
display controls and the media preference preserve that choice. The catalogue
hierarchy and finding aid remain unchanged.

In hierarchy mode, both browser columns show inline counts such as **Series (42)**.
Each count includes unique visible objects throughout that branch, respects the
media preference and access restrictions, and shows **(0)** for visible empty branches.
The [collection guide](docs/COLLECTIONS.md) explains the two modes, URL state,
count semantics and configuration. The **Download finding aid** link uses sentence
case and normal detail-link styling, including an underline on hover.

The overview groups description/source and scope/content in the
center, with creators, dates, extent, language, subjects, rights and related
records in the right column. Columns stack on phones and empty columns are
omitted. A compact results toolbar uses the available page width.
Collection browse eligibility fetches media descriptors only until each collection
qualifies; thumbnail queries read one usable image per card instead of rendering
every attached object image. A public secondary image can supply the thumbnail
when the object's primary is private. Candidate IDs pass batched native
type/source/bundle and Pawtucket ACL checks before media descriptors are read;
unreadable ancestors prune collection branches. No additional service or database
index is required. Search/browse result HTML is rendered per request and never
stored in a shared cache.

Object result tiles, list rows and multisearch previews prefer the primary
representation. When it is unavailable (for example, a paired JPEG made private),
they fall back to an accessible image derivative using the native representation
model's access, ACL and bundle checks. This does not change catalogue primary links,
access flags or files; TIFF representations use their generated JPEG thumbnails.
The enlarged viewer keeps keyboard focus inside the dialog and returns it to its
opener on close. Mobile navigation avoids logo overlap and updates its expanded
state for assistive technology. Advanced-search labels target unique native
controls; submitted field names and widget initialization remain intact.

Object details offer a collapsed **Document text** section below the media and
metadata when an accessible PDF has text. Use populated
`ca_object_representations.media_content` first, then `ca_objects.transcription`,
then `ca_objects.pdf_text`. Private paired PDFs do not qualify a TIFF object for
this section; all sources empty produces no heading. Imported HTML is converted
to readable plain text with paragraph/line breaks retained, so Scripto wrappers
and formatting tags are not displayed. See
[Document text](docs/DOCUMENT_TEXT.md) for permissions, source selection and
verification.

Collection details offer **Download finding aid**, an initial collection PDF with
metadata, a unique accessible-object total and counts for each collection or
subcollection. The PDF lists the complete readable hierarchy, indented by level
with naturally sorted siblings, including empty subcollections. Counts include
records without media; individual object entries
are omitted. Field mappings and open archives-team decisions are documented in
[Finding aids](docs/FINDING_AIDS.md).

Result thumbnails keep the filled cover layout and respect Providence's **Set
center** focal points. An optional offline OpenCV batch tool supplies face-based
suggestions without changing those staff selections or processing images on page
loads. `--all --apply` processes every eligible public attached image in one run;
suggestions live outside `app/tmp` and survive its routine clearing. See
[Thumbnail focus](docs/THUMBNAIL_FOCUS.md) for installation and operation.

To share the face detection and focal-point integration with another Pawtucket
site, export the standalone source package with Python 3.11+:

```sh
python3 support/package-thumbnail-faces.py \
  --output-dir=../thumbnail-faces --archive=../thumbnail-faces.zip
```

It includes the exact runtime scripts/helpers, portable tests, license notices,
detailed setup instructions and a file-by-file theme integration guide/snippets.
The output paths must be new. The exporter uses an explicit source allowlist,
includes hashes/provenance and leaves the theme's installed paths unchanged. See
the [package guide source](support/thumbnail-faces/share/README.md).

Existing accounts can use the bookmarked `/LoginReg/LoginForm` page. Signed-in
users get **My account** navigation and **Add to lightbox** on object details.
Result tiles and list rows omit those buttons for everyone. Anonymous visitors
get no login links. Self-registration is disabled for the initial pilot.
Signed-in staff (native full-access accounts) also get **View in Providence**
beneath the object title; it opens that object in a new tab on the collection
management site. Public-access accounts never receive this link. Providence
retains its own login session and editor permissions.
The homepage FAQ scaffold uses Providence Site Pages for content editing after a
one-time registration. See [Accounts and FAQ setup](docs/USER_FEATURES.md) and
[Repeatable FAQ activation](docs/FAQ_SETUP.md).

The header's **Only items with media | All items** control is a site-wide browser
preference, defaulting to **All items** when no valid preference is saved. Existing
cookie/session choices, including **Only items with media**, remain selected.
The toggle posts to the theme's
`MediaPreference/Set` controller and redirects back to the current search or page
without adding preference parameters to URLs. It works without JavaScript. A
host-only, HttpOnly, SameSite=Lax cookie remembers the choice for one year; its
path follows the application root and it uses Secure on HTTPS. Existing session
preferences remain a fallback. Bookmarks and shared links use the visitor's current
preference; Back keeps normal page/search navigation and restored pages refresh
to apply the current preference. Object and collection
lists are filtered before pagination; collections qualify through accessible
object media in the collection itself or any descendant collection. Images,
video, audio, PDFs, and supported embedded media qualify. Private, deleted, and
metadata-only representations do not.

This controls display, not authorization. Direct detail URLs remain accessible.
**Browse > People, Organizations, Families, Places and Events** also follows the preference:
in Only items with media mode, each record needs at least one accessible related
object with usable digital media. All items mode includes records without media.
Families qualify through objects linked directly to the family or to directly
related people and organizations. Family details show that combined object list,
deduplicated through native search. Family browsing defaults to Tiles and includes
empty families in All items mode. Unknown entity types omit an invalid Browse
destination rather than linking to `Browse/Index`.
Place details include objects linked to the place or any readable descendant,
without requiring duplicate catalogue relationships. Media-only place browsing
uses the same descendant scope; private/deleted branches stay excluded.
Filtering precedes counts, paging and detail navigation, preserves native sort
order, and uses batched relationship
queries with shared media decoding. Media attached directly to an authority
record alone does not qualify it. Authority Search and MultiSearch results retain
their normal behavior. Native facet counts cover all items, so media-only views
omit those counts. Result
downloads are available in All items mode; they use the native unfiltered result.
Keep `collections.conf`'s `cache_timeout = 0`: native collection-child HTML cache
keys do not include this preference or the current access mask.
Whole-page content caching is also disabled in `app.conf` because rendered pages
include the visitor's selected preference.

Browse defaults to ascending names for Places, Events and Collections, and
ascending identifiers for People, Organizations, Families and Objects. Entity identifier
order follows the catalogue's naming convention; the People **Name** option also
supports surname/forename ordering. Menu and detail-page Browse links request
these defaults explicitly, including for sessions with an older saved sort.
Options can still change the sort; paging and detail **Prev / Next** preserve the
displayed result order. Keyword searches retain their relevance defaults.
Object details show only **Prev / Next**, without a Browse objects button; direct
visits without result context omit the empty navigation toolbar.
Header, advanced and query-builder searches use GET, allowing normal browser
Back navigation without a form-resubmission prompt. Advanced forms retain their
native fields, boolean operators and relationship restrictions.

Run the standalone synthetic regression checks with PHP/PDO SQLite, Node.js and
Python 3.11+:

```sh
for test_file in tests/*_test.php; do
  php "$test_file" || exit 1
done
```

Production hardening keeps exception details and request parameters out of public
error pages, returns 404 for failed dispatch/missing records and 500 for unexpected
failures, and retains native server-side exception logging. Full-page 404s include
unknown-URL fallbacks, with a labeled search form and links to browse collections,
advanced search and home. They retain the normal site navigation when available.
Gallery entrypoints use a theme controller shim to require published sets of the configured gallery
type before invoking the native controller; user lightboxes remain behind native
ownership/sharing checks. Export failures log safe IDs, format and code location
through PHP's server error log, without exception messages or request payloads.

GitHub Actions runs PHP 8.3 syntax checks and all standalone suites on pushes and
pull requests, with read-only repository permissions and no production access.
See [Production verification](docs/PRODUCTION_CHECKS.md) for the remaining
controlled account, email and export smoke checks.
