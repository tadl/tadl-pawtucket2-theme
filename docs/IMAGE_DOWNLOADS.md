# Image downloads

Image menus offer JPG and single-page PDF exports, plus the original TIFF when
available. Conversion reads the original through Pawtucket's installed image
processor and retains its full pixel dimensions. PDF printing scales that image
onto letter paper; it does not substitute a small preview. Native PDF documents
keep their complete original download rather than becoming single-image PDFs.

Every request rechecks object access, representation attachment, record and media
bundle permissions, Pawtucket-only ACLs, native download policy and permission
to download the original. A cached file never bypasses those checks. Conversion
failures return HTTP 503 rather than bytes disguised as the requested format.

## Private conversion cache

`conf/app.conf` enables `tadl_cache_image_downloads`. Original TIFF/JPEG downloads
need no conversion cache. Generated JPG/PDF files are reused across authorized
requests using a hash of the original path, native checksum, file size/timestamps,
format and pipeline version. Replacing media invalidates the cache. Cache hits
validate byte MIME, JPEG dimensions or PDF framing before serving files.

The default directory is OS temporary storage named
`tadl-image-downloads-<effective-web-user-id>`. It must be owned by the PHP user
and have mode `0700`; files use `0600`. No deployment-time directory provisioning
is required for the default. The cache is disposable: clearing it only causes
the next authorized request to regenerate an export.

For a durable cache, set `tadl_image_download_cache_directory` in the application
configuration to an absolute directory owned by the PHP user, mode `0700`,
outside **all** web roots. The helper rejects the application root, symlinked
directories and shared filesystem permissions, but cannot discover other
virtual hosts' roots. Provisioning that directory is a separate operator action.
Do not put exports under the theme, `export/` or a publicly served media path.

Unused exports expire after seven days. Successful cached downloads trigger
cleanup at most hourly. Per-export locks prevent simultaneous conversion of
the same original; a competing request waits at most three seconds, then returns
503. Cleanup skips locked exports. Small lock files remain to avoid a lock
replacement race; OS temporary-directory cleanup can remove the disposable cache
when the site is idle. Atomic publication keeps partial conversions out of hits.

Image downloads and binary streaming use a 120-second PHP execution limit.
ImageMagick subprocesses inherit `MAGICK_TIME_LIMIT`, and the Imagick extension's
time resource limit is capped when supported; stricter ImageMagick limits remain.
Limits are restored after conversion. PHP's execution limit is not a universal
wall-clock deadline for external tools: the configured processor and web server's
timeouts still apply. No new renderer, queue service or internet API is required.

Set `tadl_cache_image_downloads = 0` to use private request-local conversions with
shutdown cleanup. Authorization and conversion limits still apply.

## Verification

Run `php tests/image_download_test.php`. It covers actual controller authorization,
revoked-access cache hits, repeat JPG/PDF reuse, replacement/corruption,
filesystem privacy, lock contention, expiry, native viewer/gallery callbacks
and conversion errors using synthetic media and model boundaries. Optional
`TADL_TEST_MAGICK` and `TADL_TEST_COMPOSER_AUTOLOAD` exercise the installed processor
and bundled Dompdf. After an authorized deployment, verify a TIFF and BMP export,
repeat each download, and recheck an account with restricted media access.
