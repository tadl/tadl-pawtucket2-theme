# Installation and operation

## 1. Put the source in your theme

Use a custom theme rather than editing Pawtucket core or the default theme. In the
commands below, replace `/path/to/thumbnail-faces`, `/path/to/pawtucket` and
`my-theme` with your actual paths. Copy only these files from the package, keeping
their relative layout:

```text
helpers/thumbnail_focus.php
conf/thumbnail_focus.conf
assets/pawtucket/js/thumbnail-focus.js
support/detect-thumbnail-faces.php
support/thumbnail-faces/detect.py
support/thumbnail-faces/requirements.txt
```

Merge existing directories; do not replace your theme's `helpers`, `conf`,
`assets` or `support` trees. These filenames should be new in another theme. If
already present, compare versions before replacing them. Keep the package license
and notices with the copied code. [Theme integration](THEME_INTEGRATION.md) lists
the PHP, asset and CSS changes required to display the results.

The CLI bootstraps the installed Pawtucket application, using its normal database
and media configuration. Do not copy a production `setup.php` to a test machine.
The script is CLI-only and returns 404 if requested through the web. It never
writes database records, staff centers or media files.

## 2. Install a local Python runtime

For a new Linux installation, use a separate Python 3.11+ virtual environment
outside the application and web roots. Install the runtime as root and keep it
root-owned. The application user needs read/execute access, not write access.
If your OS lacks the Python venv module, install it through the OS package manager
first. Do not install these dependencies into system Python.

The defaults retain the original tool's names, `/opt/tadl-thumbnail-faces` and
`/var/cache/tadl-thumbnail-faces`, to keep the shipped code unchanged. Other paths
work using the configuration and CLI overrides below.

```sh
# As root, for a new installation:
umask 022
install -d -m 0755 /opt/tadl-thumbnail-faces
python3 -m venv /opt/tadl-thumbnail-faces/venv
/opt/tadl-thumbnail-faces/venv/bin/python -m pip --isolated install \
  --no-cache-dir --only-binary=:all: --index-url https://pypi.org/simple \
  -r /path/to/thumbnail-faces/support/thumbnail-faces/requirements.txt
/opt/tadl-thumbnail-faces/venv/bin/python -m pip check
```

Pinned versions are OpenCV's headless wheel `4.13.0.92` and NumPy `2.4.3`. Binary
wheel availability depends on the OS/architecture and Python version; pip should
fail rather than silently building a different runtime. Recreate a venv when
moving to a different server/interpreter instead of copying it.

## 3. Download and verify YuNet

This is the OpenCV Zoo **YuNet 2023mar** model. Installation downloads it once;
inference never downloads models or calls an internet API. For a new installation:

```sh
# As root:
curl --fail --location \
  'https://media.githubusercontent.com/media/opencv/opencv_zoo/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx' \
  --output /opt/tadl-thumbnail-faces/yunet.onnx
curl --fail --location \
  'https://raw.githubusercontent.com/opencv/opencv_zoo/main/models/face_detection_yunet/LICENSE' \
  --output /opt/tadl-thumbnail-faces/YUNET-LICENSE
chmod 0644 /opt/tadl-thumbnail-faces/yunet.onnx /opt/tadl-thumbnail-faces/YUNET-LICENSE
printf '%s\n' '8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4  /opt/tadl-thumbnail-faces/yunet.onnx' | sha256sum --check
```

The detector requires exactly **232589 bytes** and that SHA-256. A Git LFS pointer,
changed weights or corrupt download will be rejected before OpenCV loads. Do not
remove this validation to use arbitrary weights; a model change needs a reviewed
detector/cache-generation update. Retain upstream license notices; see
[NOTICE.md](../NOTICE.md).

Verify the application user can load the installed runtime/model:

```sh
sudo -u www-data /opt/tadl-thumbnail-faces/venv/bin/python -c \
  'import cv2; cv2.FaceDetectorYN.create("/opt/tadl-thumbnail-faces/yunet.onnx", "", (320, 320)); print("Model loaded")'
```

## 4. Provision a persistent shared cache

The copied `conf/thumbnail_focus.conf` contains:

```text
face_cache_directory = /var/cache/tadl-thumbnail-faces
```

Web rendering and the CLI read that same file relative to the copied helper. Use
an absolute directory outside application/web roots and outside `app/tmp`:

```sh
# As root; replace www-data with your actual web/maintenance account:
install -d -o www-data -g www-data -m 0750 /var/cache/tadl-thumbnail-faces
```

Run maintenance as the application account. It needs read access to existing
derivatives and write access to this directory. Cached JSON files have mode 0640;
if CLI and web run under different accounts, arrange a shared group and directory
permissions so web requests can read them. Keep runtime code/model non-writable
by the web user. Do not put the cache in Git, theme distribution archives, web
roots or temporary-directory purge jobs. Include it in operational backups.

To customize paths, edit `face_cache_directory` and pass absolute `--python` and
`--model` arguments. `--pawtucket-root` is independent of the cache and must point
to the installed Pawtucket application, not to Providence or to this source package.

## 5. Pilot and inspect the result

First [wire the theme](THEME_INTEGRATION.md). Choose an existing public collection
and replace synthetic ID `123` below. Omitting `--apply` is read-only:

```sh
sudo -u www-data php /path/to/pawtucket/themes/my-theme/support/detect-thumbnail-faces.php \
  --pawtucket-root=/path/to/pawtucket --collection-id=123 --limit=100

sudo -u www-data php /path/to/pawtucket/themes/my-theme/support/detect-thumbnail-faces.php \
  --pawtucket-root=/path/to/pawtucket --collection-id=123 --limit=100 --apply
```

When installed under `themes/my-theme/support`, the script can infer its
Pawtucket root. Explicit `--pawtucket-root` is useful for clarity or for running
the source package outside the installed theme. Both cases use the configuration
next to the helper actually being executed; ensure it matches your web theme.

Collection mode selects public **primary** image representations from public
objects in the selected public collection or its public descendants. Repeat
limited runs to advance. Compare portrait, landscape and group photographs on
desktop and narrow screens. Save a **Set center** point in Providence, save the
record and confirm it wins over automatic boxes. Detection is fallible; staff
centers are the supported correction mechanism.

## 6. Process the entire catalogue

```sh
# Inspect every eligible public attached image:
sudo -u www-data php /path/to/pawtucket/themes/my-theme/support/detect-thumbnail-faces.php \
  --pawtucket-root=/path/to/pawtucket --all

# Process the entire scope in one invocation:
sudo -u www-data php /path/to/pawtucket/themes/my-theme/support/detect-thumbnail-faces.php \
  --pawtucket-root=/path/to/pawtucket --all --apply
```

`--all` includes nonprimary and shared representations attached to public objects,
including objects without collections. It excludes deleted/private objects and
representations, unattached images, and video/audio/PDF previews. Public is
currently the native access value **1**; this is intentionally an operator scan
for standard public access, not a scan under a visitor's session or an alternate
access-value configuration. The web helper decorates only images the site's
existing access/ACL-filtered view has already chosen. It does not grant access.

The current batch scans JPEG `medium` and `small` derivatives at most 2000 pixels
per dimension. Other displayed versions can use manual points, but need an
explicit detector-version extension for automatic boxes. Images attached only to
a collection/other authority are not scanned by the object-based query. Missing,
unreadable, queued, icon, non-JPEG and oversized derivatives are skipped.

The scan uses database keyset pages of 250 representations and a single local
OpenCV worker/thread. It streams one derivative per job. `--all --apply` runs to
completion unless `--limit=1..1000` is supplied; collection mode defaults to 100
uncached derivatives. Choose `--all` or `--collection-id`, never both. No scope is
selected by default. Progress goes to stderr every 100 processed/migrated entries;
the final JSON summary goes to stdout.

| Counter | Meaning |
| --- | --- |
| `manual` | Representations skipped because staff saved a center |
| `cached` | Valid existing derivative results skipped, including no-face results |
| `pending` | Uncached derivatives selected this invocation, including those then written |
| `written` | Newly detected derivative results stored |
| `failed` | Detection errors; not cached and cause exit status 1 |
| `migrated` | Valid legacy temporary-cache entries copied to durable storage |
| `complete` | Scope fully scanned instead of stopped at a limit; check failures separately |

Completed writes are atomic and remain usable after interruption. Only one
maintenance writer can hold the cache lock at a time. Re-running skips valid
entries, including images with no faces, and retries failures. New/reprocessed
media get new keys automatically. A successful repeat with `pending: 0` means no
eligible uncached derivatives remain. There is no scheduler or automatic pruning;
run again after imports or schedule it using your normal maintenance tooling.

## Existing proof-of-concept caches

The helper temporarily supports `app/tmp/tadl-thumbnail-faces` as a fallback.
Before a deployment clears `app/tmp`, preserve its JSON files as the same account:

```sh
sudo -u www-data find /path/to/pawtucket/app/tmp/tadl-thumbnail-faces \
  -maxdepth 1 -type f -name '*.json' \
  -exec cp -n -- {} /var/cache/tadl-thumbnail-faces/ \;
```

This leaves sources and existing destinations intact. `--apply` also validates and
migrates surviving eligible legacy entries without inference. Read-only runs
never migrate. A valid durable no-face result wins over a legacy suggestion.
Clearing `app/tmp` after migration does not lose durable results. Deleting the
durable directory does require rebuilding it.

## Troubleshooting and rollback

- No automatic focus: check cache ownership, the configured path, public access,
  derivative version/MIME and whether the batch reported skips or failures.
- No `data-tadl-focus` attribute: check the PHP decoration call, native filename,
  exact representation URL and existing cached view HTML. No boxes/manual point
  correctly means no attribute and the normal centered crop.
- Attribute exists but position stays centered: check the JS loaded once, the
  image has actual dimensions and computed `object-fit: cover`, and no
  `object-position: ... !important` rule overrides it.
- Model validation failure: verify the exact model size/hash and ensure the
  downloaded file is not a Git LFS pointer. Do not disable the checksum check.
- Cache lock conflict: another maintenance process is running; wait for it.
- Rollback: revert the theme decoration calls/JS registration together. Keep the
  cache/runtime if you may re-enable the feature; media and database records were
  never altered. Deploy using your normal reviewed procedure.
