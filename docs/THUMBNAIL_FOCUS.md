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
endpoint or a page-load job. It reads public primary representations from a chosen
public collection and its public descendants. Object/representation deletion and
public access are checked. The CLI is an operator tool, not a visitor endpoint;
cached boxes can only reach the browser through an image already selected by the
native access-filtered view. It never expands the site's record/media visibility.

The detector uses OpenCV YuNet locally. It sends no collection images to external
services and performs face detection only, without identifying people. It reads
existing bounded JPEG `medium`/`small` derivatives, skips staff-selected centers,
and stores normalized face boxes in disposable cache files. Multiple faces guide
the crop together; if all faces fit the crop they are retained. If the group cannot
fit the required aspect ratio, the crop centers on the group and still fills the
card. Detection remains fallible on small, damaged or unusual historical images;
staff can always correct it with **Set center**.

### Install the optional runtime

Use a separate Python 3.11+ virtual environment outside the web roots, maintained
alongside the site's other tools. Do not install packages into system Python.
The theme has no new Composer dependency, browser ML download or CDN dependency.

```sh
python3 -m venv /private/tools/tadl-thumbnail-faces/venv
/private/tools/tadl-thumbnail-faces/venv/bin/python -m pip install \
  --only-binary=:all: -r /path/to/tadl/support/thumbnail-faces/requirements.txt
```

Download `face_detection_yunet_2023mar.onnx` from
[OpenCV Zoo](https://github.com/opencv/opencv_zoo/tree/main/models/face_detection_yunet)
to `/private/tools/tadl-thumbnail-faces/yunet.onnx`. The direct model URL is
`https://media.githubusercontent.com/media/opencv/opencv_zoo/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx`.
The detector requires the pinned SHA-256
`8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4`
and size 232589 bytes, so changed/corrupt downloads fail before processing.
OpenCV is [Apache-2.0 licensed](https://github.com/opencv/opencv/blob/4.13.0/LICENSE)
and this YuNet model is [MIT licensed](https://github.com/opencv/opencv_zoo/blob/main/models/face_detection_yunet/LICENSE); retain the
upstream notices with the installed runtime/model. Runtime and model installation
are separate from deploying the theme.

### Inspect and process a collection

Run as the application maintenance user, normally `www-data`, with media read
access and write access to Pawtucket's application `app/tmp` directory. Start with
inspection; no detector or cache write occurs without `--apply`:

```sh
php /path/to/tadl/support/detect-thumbnail-faces.php \
  --pawtucket-root=/path/to/pawtucket --collection-id=123 --limit=100

php /path/to/tadl/support/detect-thumbnail-faces.php \
  --pawtucket-root=/path/to/pawtucket --collection-id=123 --limit=100 \
  --apply --python=/private/tools/tadl-thumbnail-faces/venv/bin/python \
  --model=/private/tools/tadl-thumbnail-faces/yunet.onnx
```

`limit` bounds uncached derivatives per invocation (1–1000), not object count.
Repeat to advance through the collection: valid cached results, including no-face
results, are skipped. `pending: 0` indicates no remaining eligible uncached
derivatives. The summary reports manual/cached/pending/written/failed counts,
without printing catalog titles, media paths or image data. A failed detection
is reported, is not cached, and makes the command exit unsuccessfully.

Caches live under `app/tmp/tadl-thumbnail-faces`, with directory mode 0750 and
atomic mode-0640 files. Preserve normal maintenance/web-user ownership. They contain
boxes only, not copies of media. Keys include detector generation, derivative
filename/magic/checksum/dimensions and original checksum; replacing/reprocessing
media invalidates stale boxes automatically. Manual points always take precedence
without having to remove an existing suggestion.

A native/application cache purge that removes this directory also removes automatic
suggestions. Re-run the batch command after such a deployment/purge. Manual focal
points remain in the database and need no detection runtime. Nothing automatically
installs a scheduler, changes catalog records, regenerates derivatives, or restarts
production. A future scheduled job can reuse this same bounded command.

## Verification

`tests/thumbnail_focus_test.php` checks native tag preservation, saved-point
priority, stale/corrupt caches and responsive cover geometry. The companion
`tests/thumbnail_detector_test.php` exercises the actual CLI against synthetic
native selection and detector boundaries, bounded batches, private cache writes
and no-op repeats. Both run with the normal portable PHP suites and Node.js.

The pinned OpenCV runtime/model were also tested locally: the selected live test
portrait produced two face detections and a generated blank image produced none.
The user's selected center was read through native production APIs and decorated
successfully by the new helper without deploying it. Desktop/phone browser previews
kept cover while displaying the subjects' heads. These are read-only/live-source
and local-preview checks, not proof of theme deployment or a production batch run.
