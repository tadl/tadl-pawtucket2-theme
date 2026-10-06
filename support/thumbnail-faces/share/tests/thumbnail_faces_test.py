"""Synthetic detector geometry and model validation; OpenCV is not required."""
import importlib.util
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest


sys.dont_write_bytecode = True
DETECTOR = Path(__file__).resolve().parents[1] / "support/thumbnail-faces/detect.py"
spec = importlib.util.spec_from_file_location("thumbnail_face_detector", DETECTOR)
detector = importlib.util.module_from_spec(spec)
spec.loader.exec_module(detector)


class DetectorTests(unittest.TestCase):
    def test_normalizes_and_clamps_image_edges(self):
        boxes = detector.normalized_faces([[20, 10, 20, 30]], 100, 100)
        self.assertEqual(len(boxes), 1)
        for actual, expected in zip(boxes[0], [0.2, 0.1, 0.2, 0.3]):
            self.assertAlmostEqual(actual, expected)
        self.assertEqual(detector.normalized_faces([[-10, -10, 120, 120]], 100, 100),
                         [[0.0, 0.0, 1.0, 1.0]])

    def test_rejects_invalid_or_outside_boxes(self):
        rows = [[float("nan"), 0, 10, 10], [0, 0, -1, 10],
                [0, 0, 10, 0], [110, 0, 10, 10], [0, float("inf"), 1, 1]]
        self.assertEqual(detector.normalized_faces(rows, 100, 100), [])

    def test_empty_and_bounded_results(self):
        self.assertEqual(detector.normalized_faces([], 100, 100), [])
        self.assertEqual(len(detector.normalized_faces([[0, 0, 1, 1]] * 101, 100, 100)), 100)

    def test_rejects_invalid_model_before_loading_opencv(self):
        with tempfile.TemporaryDirectory() as temp:
            model = Path(temp) / "synthetic.onnx"
            model.write_bytes(b"x" * 232589)
            result = subprocess.run([sys.executable, str(DETECTOR), "--model", str(model)],
                                    capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("checksum-verified", result.stderr)
        self.assertNotIn("ModuleNotFoundError", result.stderr)


if __name__ == "__main__":
    unittest.main()
