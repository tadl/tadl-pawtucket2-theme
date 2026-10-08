# tadl-pawtucket2-theme

Standalone source repository for the `tadl` Pawtucket2 theme used for TADL local history.

For Codex or a new workstation, start with [AGENTS.md](AGENTS.md) and the
[current handoff](docs/CODEX_HANDOFF.md). The handoff explains the source map,
recent changes, design decisions and all twenty-six standalone regression suites.
It uses relative paths and does not require an application database for those tests.

Deployment is a separate, explicitly authorized step. A source commit/push does
not authorize running the enclosing deployment helper or restarting production.

Download links discourage crawler following, binary downloads send `noindex`
response headers, and search/browse pages stay out of search indexes. A replacement
root `robots.txt` covers clean and `index.php` routes while keeping public records
and thumbnails crawlable. The root file requires a separate installation after
theme deployment; see [Crawler policy](docs/CRAWLER_POLICY.md).

Deploy flow after authorization:

1. make changes in this repo
2. commit and push to GitHub
3. rsync the theme to the Pawtucket2 server theme directory
4. clear `app/tmp`
5. restart Apache

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

Collection contents keep paging, sorting and Tiles/List controls on the collection
detail URL. The overview groups description/source and scope/content in the
center, with creators, dates, extent, language, subjects, rights and related
records in the right column. Columns stack on phones and empty columns are
omitted. A compact results toolbar uses the available page width.
Collection browse eligibility fetches media descriptors only until each collection
qualifies; thumbnail queries select one primary image per card instead of rendering
every attached object image. No additional service, database index or shared page
cache is required.

Object result tiles, list rows and multisearch previews prefer the primary
representation. When it is unavailable (for example, a paired JPEG made private),
they fall back to an accessible image derivative using the native representation
model's access, ACL and bundle checks. This does not change catalogue primary links,
access flags or files; TIFF representations use their generated JPEG thumbnails.

Object details offer a collapsed **Document text** section below the media and
metadata when an accessible PDF has text. Use populated
`ca_object_representations.media_content` first, then `ca_objects.transcription`,
then `ca_objects.pdf_text`. Private paired PDFs do not qualify a TIFF object for
this section; all sources empty produces no heading. Imported HTML is converted
to readable plain text with paragraph/line breaks retained, so Scripto wrappers
and formatting tags are not displayed. See
[Document text](docs/DOCUMENT_TEXT.md) for permissions, source selection and
verification.

Collection details offer **Download Finding Aid**, an initial collection PDF with
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
preference, defaulting to **Only items with media**. The toggle posts to the theme's
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
**Browse > People, Organizations, Places and Events** also follows the preference:
in Only items with media mode, each record needs at least one accessible related
object with usable digital media. All items mode includes records without media.
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

Run the standalone synthetic regression checks with PHP/PDO SQLite, Node.js and
Python 3.11+:

```sh
for test_file in tests/*_test.php; do
  php "$test_file" || exit 1
done
```
