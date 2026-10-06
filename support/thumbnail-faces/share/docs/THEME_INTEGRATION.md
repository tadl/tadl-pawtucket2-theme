# Integrate an existing Pawtucket theme

This is a thumbnail decorator. Keep your existing record search, primary-media
selection, links, alt text, placeholders and native access/ACL checks. Do not copy
the TADL theme's whole result templates or collection queries to adopt it.

## Files to add

Copy the six runtime files listed in [INSTALL.md](INSTALL.md) into your custom
theme with the same relative paths. `thumbnail_focus.php` looks for
`../conf/thumbnail_focus.conf` relative to itself; the PHP batch command loads
`../helpers/thumbnail_focus.php` and `support/thumbnail-faces/detect.py` relative
to itself. Keep those paths together.

## Register the JavaScript: `conf/assets.conf`

With the usual package named `pawtucket`, merge the entry from
[snippets/assets.conf](../snippets/assets.conf) into `themePackages`:

```text
themePackages = {
    pawtucket = {
        # Keep existing entries here.
        thumbnailFocus = js/thumbnail-focus.js,
    }
}
```

Append `pawtucket/thumbnailFocus` to your existing
`themeLoadSets -> _default` list. Do not paste a second top-level block that
overwrites the existing asset configuration. For another package name, adjust
both the asset directory and reference accordingly. Your page header must still
print `AssetLoadManager::getLoadHTML($this->request)` (or your wrapper around it).
Register the JS once; no jQuery or browser model download is needed.

## Decorate thumbnail HTML: result views

For a view under `views/Browse` or `views/Search`, load the helper once:

```php
require_once(__DIR__.'/../../helpers/thumbnail_focus.php');
```

Then wrap the thumbnail HTML before your existing link/output. For native Tiles,
in **`views/Browse/browse_results_images_html.php`**, change each thumbnail link
construction from:

```php
$vs_rep_detail_link = caDetailLink($this->request, $vs_thumbnail, '', $vs_table, $vn_id);
```

to:

```php
$vs_rep_detail_link = caDetailLink($this->request, tadlFocusThumbnail($vs_thumbnail), '', $vs_table, $vn_id);
```

For native List, in **`views/Browse/browse_results_list_html.php`**, change:

```php
$vs_rep_detail_link = caDetailLink($this->request, $vs_image, '', $vs_table, $vn_id);
```

to:

```php
$vs_rep_detail_link = caDetailLink($this->request, tadlFocusThumbnail($vs_image), '', $vs_table, $vn_id);
```

Keep the preceding `$qr_res->get(...)` / `getMediaTag(...)` calls and their
`checkAccess` options intact. Both views have object and authority-image branches;
decorate the selected tag at output in each branch. Placeholders/unrecognized
images pass through unchanged. In a theme inheriting these views, copy just the
needed view from your installed version into the custom theme before editing it.

Native Search commonly renders the shared Browse subviews; inspect your own
`views/Search` overrides rather than assuming that every route shares them.
Related-object tiles on Detail pages may also use these result views. No change
to object-detail media viewers or full-size gallery images is necessary.

## Collections and custom preview views

In **`views/Collections/index_html.php`** (if it renders image tags), load the same
helper and wrap the existing access-filtered image at output:

```php
print tadlFocusThumbnail($vs_image);
```

If a shared collection thumbnail helper already decorates the tag, print its
result normally. Default-theme collection indexes may have no images at all;
this tool does not create collection image selection or card layouts.

For custom search previews, apply `tadlFocusThumbnail()` where native media HTML
is assigned. Prefer the native `tags` entry returned by `getPrimaryMediaForIDs`
when available. If your view constructs an `<img>` from a native media URL,
continue HTML-attribute escaping the URL and preserve alt/lazy attributes, then
wrap the complete tag. The helper accepts image HTML, not a bare URL.

The TADL installation provides concrete integration examples at these files:

| TADL file | Integration point |
| --- | --- |
| `views/Browse/browse_results_images_html.php` | Tile thumbnail passed to `caDetailLink` |
| `views/Browse/browse_results_list_html.php` | List thumbnail passed to `caDetailLink` |
| `views/Browse/collection_thumbnail_helpers.php` | Decorates native collection/object fallback tags before returning them |
| `views/Collections/index_html.php` | Prints those already-decorated collection images |
| `views/Search/tadl_search_results_subview_html.php` | Custom object/collection preview image assignments |
| `conf/assets.conf` | Registers/loads `pawtucket/thumbnailFocus` |
| `assets/pawtucket/css/theme.css` | Existing card-specific cover layout |

These larger TADL-specific views/queries are deliberately not bundled; the
supplied snippets contain everything needed to decorate your own equivalent
views. The package remains independent of TADL result filtering or navigation.

## Preserve the filled crop: theme CSS

Keep your existing thumbnail box dimensions and `object-fit: cover`. The JavaScript
sets `object-position` only for `img[data-tadl-focus]` whose computed object-fit is
cover. If your theme does not already have a bounded image box, adapt
[snippets/thumbnail.css](../snippets/thumbnail.css) in your own stylesheet, for
example `assets/pawtucket/css/theme.css`:

```css
.your-card-image {
    width: 100%;
    height: 210px;
    object-fit: cover;
    object-position: 50% 50%;
}
```

Use your actual image selector and responsive dimensions. Do not apply a global
`img` rule to detail media, gallery viewers, logos or icons. Avoid overriding
object-position with `!important`. The JS uses natural image dimensions and the
current rendered box, repositions on load/resize and observes AJAX-added nodes.
It does not set `object-fit`, change image sources or replace your card layout.

## Staff centers and supported derivatives

Providence **Set center** values are normalized coordinates in the original
image's `_CENTER` metadata. An explicitly saved `(0.5, 0.5)` is a staff choice,
not an absent point. It wins over face boxes. Save the record after choosing it.

Original coordinates apply only when the chosen derivative preserves the original
aspect ratio (within 2% rounding tolerance). An already cropped derivative lacks
the transform needed to map the original center, so that derivative keeps its
existing crop; automatic suggestions never substitute for a staff choice. Use
aspect-preserving `medium`/`small` derivatives for best results. This feature does
not regenerate them or recover faces already cropped out by media processing.

The helper recognizes native filenames such as
`123_ca_object_representations_media_42_medium.jpg`, loads the representation and
checks that its current version URL matches the tag's path. A stale, unrelated,
custom-renamed or non-representation URL is left alone. CDN hosts work when the
native path remains the same; rewritten paths/srcset/picture variants need a
reviewed adapter rather than guessing metadata from a filename. It does not
handle arbitrary remote image URLs.

Automatic cache keys are per derivative and include detector generation,
filename/magic/checksum/dimensions and original checksum. Batch processing covers
`medium`/`small` JPEGs; decorating another native version can use a staff point but
will not find automatic boxes unless that version was processed. Multiple faces
guide the crop together when they fit; if a group cannot fit the box, it remains
a filled crop centered on the group. No-face, missing or invalid cache data retains
normal centered cover.

## Cached HTML and verification

If your theme caches rendered cards/result pages, previously cached image tags
will lack new metadata until the cache expires or is refreshed through your usual
deployment/maintenance procedure. Consider appropriate expiry or cache-generation
changes when applying new staff centers or detection results. Do not introduce
shared caches that bypass your site's access/ACL context.

Check the browser's rendered tag for `data-tadl-focus`, confirm the JS loaded,
and check computed `object-position` at desktop/mobile sizes. Test manual center,
multiple faces, no faces, placeholders and AJAX pagination. Verify existing
record links, representation selection and restricted-media behavior remain
unchanged. Run the package tests before deploying your integration. Test a small
collection before a full-catalogue batch.
