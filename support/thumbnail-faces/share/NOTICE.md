# License and dependency notices

This thumbnail-focus source package is distributed under GNU GPL version 3 or
later, consistent with its CollectiveAccess integration. See [LICENSE](LICENSE).
Copyright 2026 Traverse Area District Library contributors.

The package does not bundle Python dependencies or the YuNet model. Their own
licenses apply when installing or redistributing them:

- [OpenCV 4.13.0](https://github.com/opencv/opencv/blob/4.13.0/LICENSE): Apache 2.0.
  The `opencv-python-headless` wheel includes its dependency/license notices.
- [NumPy](https://github.com/numpy/numpy/blob/v2.4.3/LICENSE.txt): BSD license,
  with additional notices for bundled components in its wheels.
- [YuNet model](https://github.com/opencv/opencv_zoo/blob/main/models/face_detection_yunet/LICENSE):
  MIT, copyright 2020 Shiqi Yu. Retain its upstream license with the downloaded model.
- [CollectiveAccess Pawtucket2](https://github.com/collectiveaccess/pawtucket2):
  GPL 3 or later; required application, not included in this package.

Keep these notices and the applicable upstream license files when redistributing
the source, installed Python wheels or model. No detector weights, catalogue
images or production cache entries are part of this distribution.
