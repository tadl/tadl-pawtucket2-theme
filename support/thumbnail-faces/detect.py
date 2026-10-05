"""Offline YuNet face boxes for native local image derivatives; JSON lines in/out."""
import argparse
import hashlib
import json
from pathlib import Path
import sys

MODEL_SHA256 = "8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4"


def normalized_faces(rows, width, height):
    """Clamp detections at image edges, omitting invalid boxes."""
    import math
    boxes = []
    for row in rows:
        x, y, w, h = (float(v) for v in row[:4])
        if not all(math.isfinite(v) for v in (x, y, w, h)) or w <= 0 or h <= 0:
            continue
        left, top = max(0.0, x / width), max(0.0, y / height)
        right, bottom = min(1.0, (x + w) / width), min(1.0, (y + h) / height)
        if right > left and bottom > top:
            boxes.append([left, top, right - left, bottom - top])
    return boxes[:100]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--model", required=True)
    args = parser.parse_args()
    model = Path(args.model)
    if not model.is_file() or model.stat().st_size != 232589 or hashlib.sha256(model.read_bytes()).hexdigest() != MODEL_SHA256:
        parser.error("Expected the checksum-verified OpenCV Zoo YuNet 2023mar model")
    import cv2
    cv2.setNumThreads(1)
    detector = cv2.FaceDetectorYN.create(str(model), "", (320, 320), 0.85, 0.3, 5000)
    for line in sys.stdin:
        job = json.loads(line)
        response = {"key": job["key"]}
        try:
            image = cv2.imread(job["path"], cv2.IMREAD_COLOR)
            if image is None:
                raise ValueError("Unreadable derivative")
            height, width = image.shape[:2]
            # The launcher supplies existing bounded JPEG derivatives, never originals/URLs.
            if width < 1 or height < 1 or width > 2000 or height > 2000:
                raise ValueError("Derivative dimensions are outside the supported range")
            detector.setInputSize((width, height))
            _, rows = detector.detect(image)
            response["faces"] = normalized_faces(rows if rows is not None else [], width, height)
        except (ValueError, cv2.error):
            response["error"] = "Face detection failed for this derivative"
        print(json.dumps(response, allow_nan=False), flush=True)


if __name__ == "__main__":
    main()
