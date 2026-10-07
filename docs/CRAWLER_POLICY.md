# Crawler policy

The theme discourages crawlers from generating downloads and traversing dynamic
searches while leaving public archive records discoverable. These are voluntary
crawler hints, not authentication or rate limits. Native access and ACL checks
still control downloads.

- Image download menus, native PDF/video/media toolbar actions, viewer controls,
  collection finding aids and result/lightbox export links use `rel="nofollow"`.
  Native callbacks and existing `rel` tokens remain intact.
  Advanced Search and Browse navigation links also use `nofollow`.
- Search, MultiSearch and Browse HTML pages use `noindex, follow`; links to public
  records remain eligible for discovery if a crawler visits those pages.
  Account/lightbox pages use `noindex, nofollow`.
- The theme's image, finding-aid, native representation and attribute/ZIP binary
  views send `X-Robots-Tag: noindex, nofollow`. Download headers, bytes and access
  checks otherwise retain their existing behavior. Native search/lightbox export
  generators are covered by the crawl restrictions rather than a core patch.
- `support/site-root/robots.txt` blocks dynamic search/browse, download, viewer
  data and account routes in both clean and `/index.php/` URL forms. Public
  `/Detail/objects/...`, collection/authority records, `/Collections`, `/Gallery`,
  CSS/JS and `/media/` previews remain crawlable.

`noindex` is a page or HTTP response directive, not a valid link `rel` value and
not a supported `robots.txt` directive. A crawler blocked by `robots.txt` cannot
read that URL's `noindex` response. The combined policy aims to reduce crawling;
it does not guarantee removal of URLs already indexed. If that later becomes a
requirement, temporarily allow those URLs to be crawled so their `noindex` can be
read, or use the search engine's removal tools.

See Google's [robots response directives](https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag),
[nofollow guidance](https://developers.google.com/search/docs/crawling-indexing/qualify-outbound-links)
and [noindex crawl requirements](https://developers.google.com/search/docs/crawling-indexing/block-indexing).

## Install the root policy

Normal theme rsync installs the link and response changes, but does **not** update
the site's root `robots.txt`. After deploying the theme, install that file once on
the Pawtucket server; repeat this step when the policy changes. These commands
assume Pawtucket is served at the origin root from `/var/www/pawtucket2`. Review
the replacement against any additional site-specific rules first:

```sh
cd /var/www/pawtucket2
diff -u robots.txt themes/tadl/support/site-root/robots.txt
```

A differing file makes `diff` exit 1, which is expected. Back up the existing file
before replacing it (keep the backup outside the public web root):

```sh
cp -p /var/www/pawtucket2/robots.txt "/root/pawtucket-robots-$(date -u +%Y%m%dT%H%M%SZ).txt"
install -m 0644 /var/www/pawtucket2/themes/tadl/support/site-root/robots.txt /var/www/pawtucket2/robots.txt
```

No cache purge or Apache restart is required for the static file. Check the public
response, which must be HTTP 200 and plain text:

```sh
curl -i https://archives.tadl.org/robots.txt
```

Check an actual permitted download URL with a GET (these endpoints do not support
HEAD). This downloads the selected file and may generate a derivative, so choose
a small test record:

```sh
curl -sS -D - -o /dev/null 'https://archives.tadl.org/ImageDownload/Download/object_id/OBJECT_ID/representation_id/REPRESENTATION_ID/format/jpg'
```

Expect `X-Robots-Tag: noindex, nofollow` alongside the existing attachment headers.
On search/browse HTML, inspect the `<head>` for the robots meta tag. Ordinary
public record pages must not gain a `noindex` directive. For a subdirectory
installation, prefix the app-route rules with that directory; the policy still
belongs at the origin's `/robots.txt`.

## Local verification

`php tests/crawler_policy_test.php` checks preserved native link callbacks and
`rel` values, page policies, actual download HTTP headers/bytes and URL matching
for the replacement robots file. `tests/image_download_test.php` additionally
checks the actual object/gallery/viewer menus across image, PDF and video media.
