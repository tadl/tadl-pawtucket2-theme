# Face-aware thumbnails for CollectiveAccess Pawtucket2

Keep filled, `object-fit: cover` thumbnails while placing the crop around people's
faces. Staff-selected **Set center** points from Providence take priority. An
optional offline OpenCV YuNet detector supplies face boxes where no staff point
exists. This detects faces; it does not identify people.

The detector runs locally with a downloaded model. It makes no internet/API calls
during processing, and no inference runs during page requests. Installation needs
internet access to download Python dependencies and the model once.

This is a self-contained source distribution extracted from the TADL Pawtucket
theme. It contains no private application configuration, credentials, catalogue records,
images, cached detections, Python environment or model binary. You do not need the
TADL theme, its branding, its collection queries or its media-preference controls.

## Start here

1. [Install the tools, Python runtime and persistent cache](docs/INSTALL.md).
2. [Wire your theme's exact files and thumbnail calls](docs/THEME_INTEGRATION.md).
3. Test a small collection, then process the whole catalogue with `--all --apply`.

The supplied helper and CLI target the native media APIs/schema used by
Pawtucket2/Providence 2.0.10. PHP 8+, DOM, JSON, `proc_open` and Python 3.11+ are
required. Verify against your own application version and media-processing setup;
custom CDN filenames and already cropped derivatives have limitations described
in the integration guide. Installation examples use Linux and `www-data`; replace
paths and service accounts for your server. No Composer package or browser ML
library is required.

## Package contents

| File | Purpose |
| --- | --- |
| `support/detect-thumbnail-faces.php` | Read-only inspection or explicit batch processing through Pawtucket |
| `support/thumbnail-faces/detect.py` | Persistent local YuNet detector; JSON-lines input/output |
| `support/thumbnail-faces/requirements.txt` | Pinned Python runtime dependencies |
| `helpers/thumbnail_focus.php` | Decorates existing native image tags with saved-point/face metadata |
| `assets/pawtucket/js/thumbnail-focus.js` | Responsive cover positioning, including AJAX-loaded thumbnails |
| `conf/thumbnail_focus.conf` | Shared web/CLI durable cache directory |
| `snippets/` | PHP, asset registration and scoped CSS examples to merge into a theme |
| `tests/` | Synthetic helper, crop geometry, CLI/SQL and Python checks |
| `PROVENANCE.json` | Source commit and SHA-256 hashes of the distributed files |
| `LICENSE`, `NOTICE.md` | Package license and external dependency notices |

Function names and cache keys retain the `tadl` prefix so the same tested source
works in other themes and remains compatible with existing caches. It is a
namespace, not a requirement to use TADL branding. Avoid installing multiple
copies of the helper into one application request.

## Verify without an application database

From this package directory, with PHP DOM/PDO SQLite/JSON/`proc_open`, Node.js and
Python 3.11+ on PATH:

```sh
php tests/thumbnail_focus_test.php
php tests/thumbnail_detector_test.php
python3 tests/thumbnail_faces_test.py
```

These tests use synthetic media descriptors and a temporary SQLite catalogue.
They do not connect to a real application or require OpenCV/model installation.
The detector test uses a synthetic worker to exercise transport, SQL selection,
cache writes, resumability, lock conflicts and failures. The Python tests exercise
box normalization and reject an invalid model before importing OpenCV. Real
inference and final visual validation are separate installation checks.

## Maintaining and sharing the package

The runtime files are copies of canonical source in the
[TADL theme repository](https://github.com/tadl/tadl-pawtucket2-theme). Packaging
does not move or change that theme's deployment paths. Copy this entire directory
or share the accompanying ZIP. After extracting it, follow the installation guide;
the package directory itself is not a complete Pawtucket theme.

To create another snapshot from a theme checkout:

```sh
python3 support/package-thumbnail-faces.py \
  --output-dir=../thumbnail-faces-new \
  --archive=../thumbnail-faces-new.zip
```

The exporter copies an explicit list of source files and refuses existing output
paths. It excludes private configuration, caches and media by construction. The
snapshot records working-copy bytes and the current Git commit; export from a
clean, verified checkout for a release. Changes made only to an exported copy do
not automatically update the TADL source.
