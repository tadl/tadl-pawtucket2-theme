# tadl-pawtucket2-theme

Standalone source repository for the `tadl` Pawtucket2 theme used for TADL local history.

For Codex or a new workstation, start with [AGENTS.md](AGENTS.md) and the
[current handoff](docs/CODEX_HANDOFF.md). The handoff explains the source map,
recent changes, design decisions and all nineteen standalone regression suites.
It uses relative paths and does not require an application database for those tests.

Deployment is a separate, explicitly authorized step. A source commit/push does
not authorize running the enclosing deployment helper or restarting production.

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

Collection details offer **Download Finding Aid**, an initial collection PDF with
metadata and a complete accessible-object inventory, including records without
media. Field mappings and open archives-team decisions are documented in
[Finding aids](docs/FINDING_AIDS.md).

Result thumbnails keep the filled cover layout and respect Providence's **Set
center** focal points. An optional offline OpenCV batch tool supplies face-based
suggestions without changing those staff selections or processing images on page
loads. `--all --apply` processes every eligible public attached image in one run;
suggestions live outside `app/tmp` and survive its routine clearing. See
[Thumbnail focus](docs/THUMBNAIL_FOCUS.md) for installation and operation.

Existing accounts can use the bookmarked `/LoginReg/LoginForm` page. Signed-in
users get **My account** navigation and **Add to lightbox** actions; anonymous
visitors get no login links. Self-registration is disabled for the initial pilot.
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
People, places, and other authority results retain their normal behavior. Native
facet counts cover all items, so media-only views omit those counts. Result
downloads are available in All items mode; they use the native unfiltered result.
Keep `collections.conf`'s `cache_timeout = 0`: native collection-child HTML cache
keys do not include this preference or the current access mask.
Whole-page content caching is also disabled in `app.conf` because rendered pages
include the visitor's selected preference.

Run the standalone synthetic regression checks with PHP and PDO SQLite:

```sh
for test_file in tests/*_test.php; do
  php "$test_file" || exit 1
done
```
