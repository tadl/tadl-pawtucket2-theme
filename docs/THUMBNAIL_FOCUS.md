# Thumbnail focal points and offline face detection

Search/browse tiles, List thumbnails, collection index thumbnails and multisearch
previews keep `object-fit: cover`. The theme adds focus metadata to existing native
image tags; it does not select another representation, change access/ACL checks,
alter originals, or introduce a whole-image/letterbox fallback. Authority-related
object results use the same shared result views. Detail and gallery viewers are
unchanged.

## Staff-selected centers

Providence's media representation editor has **Set center**. Save the object after
choosing the point. The native `_CENTER` values are normalized coordinates in the
original image. The helper reads them for the exact representation/version already
selected by the native view. A saved point overrides every automatic suggestion,
including an explicitly stored center point `(0.5, 0.5)`. Native images with no
stored point have an empty `_CENTER` array, rather than an explicit center.

The browser calculates the cover crop from the image's actual dimensions and the
rendered card dimensions. Using the coordinates directly as CSS percentages would
not center the selected subject correctly. The calculation clamps at image edges
and reruns on load, resize and new AJAX/infinite-scroll results. A percentage
fallback is present for manual points before JS loads. Both paths keep cover.

Original-image coordinates are applied only to derivatives with essentially the
same aspect ratio (2% tolerance for rounding). For an already cropped derivative,
staff coordinates cannot be mapped accurately without its crop transform. Its
existing crop is retained, and automatic suggestions do not override that choice.
Missing/corrupt face caches and images with no faces retain the centered cover crop.

## Automatic suggestions

`support/detect-thumbnail-faces.php` is an explicit maintenance CLI, not a public
endpoint or a page-load job. `--all` reads every image representation attached to
a public object, including nonprimary media and objects without collections. The
optional collection mode retains public primary representations from that public
collection and its public descendants. Shared representations are scanned once.
Deleted/private objects and representations, unattached media, and video/audio/PDF
previews are excluded. Object/representation deletion and public access are checked.
The CLI is an operator tool, not a visitor endpoint;
cached boxes can only reach the browser through an image already selected by the
native access-filtered view. It never expands the site's record/media visibility.

The detector uses OpenCV YuNet locally. Detection makes no HTTP/API calls and
sends no collection images to external services. Installation downloads packages
and the model once; processing uses that local model and performs face detection
only, without identifying people. It reads existing bounded JPEG `medium`/`small`
derivatives, skips staff-selected centers, and stores normalized face boxes in
durable cache files outside the application. Multiple faces guide
the crop together; if all faces fit the crop they are retained. If the group cannot
fit the required aspect ratio, the crop centers on the group and still fills the
card. Detection remains fallible on small, damaged or unusual historical images;
staff can always correct it with **Set center**.

### Install the optional runtime

Use a separate Python 3.11+ virtual environment outside the web roots, maintained
alongside the site's other tools. Do not install packages into system Python.
The theme has no new Composer dependency, browser ML download or CDN dependency.
On Linux, create/install the runtime as root under `/opt/tadl-thumbnail-faces`.
Keep it root-owned; the application user needs read/execute access, not write
access. The runtime sits outside theme/application upgrades and cache purges.

```sh
# As root, for a new installation:
umask 022
install -d -m 0755 /opt/tadl-thumbnail-faces
python3 -m venv /opt/tadl-thumbnail-faces/venv
/opt/tadl-thumbnail-faces/venv/bin/python -m pip --isolated install \
  --no-cache-dir --only-binary=:all: --index-url https://pypi.org/simple \
  -r /path/to/tadl/support/thumbnail-faces/requirements.txt
/opt/tadl-thumbnail-faces/venv/bin/python -m pip check
```

Download `face_detection_yunet_2023mar.onnx` from
[OpenCV Zoo](https://github.com/opencv/opencv_zoo/tree/main/models/face_detection_yunet)
to `/opt/tadl-thumbnail-faces/yunet.onnx`. The direct model URL is
`https://media.githubusercontent.com/media/opencv/opencv_zoo/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx`.
The detector requires the pinned SHA-256
`8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4`
and size 232589 bytes, so changed/corrupt downloads fail before processing.
OpenCV is [Apache-2.0 licensed](https://github.com/opencv/opencv/blob/4.13.0/LICENSE)
and this YuNet model is [MIT licensed](https://github.com/opencv/opencv_zoo/blob/main/models/face_detection_yunet/LICENSE); retain the
upstream notices with the installed runtime/model. Runtime and model installation
are separate from deploying the theme.

Save the model's upstream `LICENSE` alongside it as `YUNET-LICENSE`, with both
files root-owned and mode 0644. Verify the checksum and application-user access:

```sh
printf '%s\n' '8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4  /opt/tadl-thumbnail-faces/yunet.onnx' | sha256sum --check
sudo -u www-data /opt/tadl-thumbnail-faces/venv/bin/python -c \
  'import cv2; cv2.FaceDetectorYN.create("/opt/tadl-thumbnail-faces/yunet.onnx", "", (320, 320)); print("Model loaded")'
```

### Durable storage and upgrading the proof of concept

`conf/thumbnail_focus.conf` sets the shared web/CLI `face_cache_directory`, default
`/var/cache/tadl-thumbnail-faces`. Provision it once as root:

```sh
install -d -o www-data -g www-data -m 0750 /var/cache/tadl-thumbnail-faces
```

**Before deploying with a helper that clears `app/tmp`**, preserve the existing
proof-of-concept suggestions. Substitute the actual application root below:

```sh
sudo -u www-data find /path/to/pawtucket/app/tmp/tadl-thumbnail-faces \
  -maxdepth 1 -type f -name '*.json' \
  -exec cp -n -- {} /var/cache/tadl-thumbnail-faces/ \;
```

This copies only cache JSON, preserves existing destination files and leaves the
sources in place. Their private modes/ownership are retained when run as the same
application user. Deploy the helper, CLI and new configuration together. If old
files survive deployment, `--apply` also validates and copies eligible legacy
entries automatically, including cached no-face results, without inference.
Read-only inspection never migrates files. Web rendering temporarily reads legacy
files when no valid durable entry exists; a valid durable no-face result wins.

The durable directory is outside the application and web roots; ordinary theme
upgrades and `app/tmp` clearing do not remove it. Include it in operational backups
and do not include it in purge scripts. If that directory itself is deleted,
detection must run again. No automatic pruning or scheduler is installed.

### Inspect and process everything

Run as `www-data`, with media read access and write access to the durable directory.
When installed under `themes/tadl`, the script detects its Pawtucket root. The
runtime/model default to the `/opt/tadl-thumbnail-faces` paths above; all paths can
still be overridden with `--pawtucket-root`, `--python` and `--model`.

```sh
# Read-only: count uncached eligible thumbnails across the whole catalogue.
sudo -u www-data php /path/to/pawtucket/themes/tadl/support/detect-thumbnail-faces.php --all

# Process the complete catalogue in one invocation.
sudo -u www-data php /path/to/pawtucket/themes/tadl/support/detect-thumbnail-faces.php --all --apply
```

`--all` requires no collection loop or repeated invocation. It uses keyset pages of
250 representations and streams one derivative at a time to one detector process,
with one OpenCV worker thread. It reports progress every 100 processed/migrated
entries to stderr, retaining a final JSON summary on stdout. Existing valid caches
are skipped; Ctrl-C/interruption leaves completed atomic writes usable on rerun.
Only one maintenance writer can hold the durable directory's lock at a time.

For a bounded pilot, add `--limit=100`. The limit counts uncached derivatives
(1–1000), not objects or cached/migrated entries. Choose `--all` or `--collection-id`,
never both. There is no default broad scope when scope arguments are missing.

### Inspect and process a collection

Collection mode remains compatible and defaults to 100 uncached derivatives:

```sh
sudo -u www-data php /path/to/pawtucket/themes/tadl/support/detect-thumbnail-faces.php \
  --collection-id=123 --limit=100

sudo -u www-data php /path/to/pawtucket/themes/tadl/support/detect-thumbnail-faces.php \
  --collection-id=123 --limit=100 --apply
```

Repeat limited runs to advance. The final summary includes:

- `manual`: representations skipped for a stored staff center.
- `cached`: valid durable/legacy derivative results skipped, including no faces.
- `pending`: uncached derivatives selected during this invocation, including those
  subsequently written; it is not the remaining count after processing.
- `written`: newly detected derivative results saved.
- `failed`: derivative detection errors, which are not cached and cause exit 1.
- `migrated`: existing valid legacy results copied to durable storage.
- `complete`: the scope was fully scanned, rather than stopped at a limit. This
  does not imply no failures; check `failed` and the exit status too.

`pending: 0` on a repeat means no remaining eligible uncached derivatives.
The tool does not print catalogue titles, media paths or image data in summaries.
Missing/unreadable, queued, icon, non-JPEG or oversized derivatives are skipped.

Cache files contain boxes only, not images, and use atomic mode-0640 writes. Keys
include detector generation, derivative filename/magic/checksum/dimensions and
original checksum; media replacement/reprocessing invalidates stale boxes. Staff
centers always win without deleting suggestions. Nothing changes database records,
regenerates media or restarts services.

## Verification

`tests/thumbnail_focus_test.php` checks native tag preservation, saved-point
priority, stale/corrupt caches and responsive cover geometry. The companion
`tests/thumbnail_detector_test.php` exercises the actual CLI and SQL against
synthetic catalogues, multi-page global scans, bounded
runs, shared/nonprimary media, private/deleted record exclusion, atomic cache
writes, legacy migration, temporary-cache purge survival, lock conflicts, detector
failures and no-op repeats. Both run with the normal portable PHP suites and Node.js.

The new global/collection SQL was also run read-only through native production
models, including two keyset pages and a query-plan check. All nineteen portable
suites passed. Durable-cache migration and the global detector have not been
deployed/applied in production by this change.

The pinned OpenCV runtime/model were also tested locally: the selected live test
portrait produced two face detections and a generated blank image produced none.
The user's selected center was read through native production APIs and decorated
successfully by the new helper without deploying it. Desktop/phone browser previews
kept cover while displaying the subjects' heads. These are read-only/live-source
and local-preview checks, not proof of theme deployment or a production batch run.

The isolated Linux runtime was subsequently installed and verified as `www-data`
with Python 3.12, OpenCV 4.13.0 and NumPy 2.4.3. The deployed detector passed
synthetic blank/unreadable-image checks, and the application user cannot write to
the runtime/model. No collection batch was run as part of runtime installation.
