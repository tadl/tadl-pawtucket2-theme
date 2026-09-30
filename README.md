# tadl-pawtucket2-theme

Standalone source repository for the `tadl` Pawtucket2 theme used for TADL local history.

Deploy flow:

1. make changes in this repo
2. commit and push to GitHub
3. rsync the theme to the Pawtucket2 server theme directory
4. clear `app/tmp`
5. restart Apache

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
php tests/media_preferences_test.php
php tests/media_preference_controller_test.php
```
