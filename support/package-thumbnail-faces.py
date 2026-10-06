"""Export the offline thumbnail-focus tools and integration guide for sharing."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import tempfile
import zipfile


THEME_FILES = (
    "helpers/thumbnail_focus.php",
    "conf/thumbnail_focus.conf",
    "assets/pawtucket/js/thumbnail-focus.js",
    "support/detect-thumbnail-faces.php",
    "support/thumbnail-faces/detect.py",
    "support/thumbnail-faces/requirements.txt",
    "tests/thumbnail_focus_test.php",
    "tests/thumbnail_detector_test.php",
)
GUIDE_FILES = (
    "README.md",
    "LICENSE",
    "NOTICE.md",
    "docs/INSTALL.md",
    "docs/THEME_INTEGRATION.md",
    "snippets/assets.conf",
    "snippets/thumbnail.php",
    "snippets/thumbnail.css",
    "tests/thumbnail_faces_test.py",
)


def export_package(source, destination, archive=None):
    """Copy only reviewed source files, without following directory trees."""
    if destination.exists() or destination.is_symlink():
        raise ValueError("Output directory already exists; choose a new directory.")
    if not destination.parent.is_dir():
        raise ValueError("Output parent directory must already exist.")
    if archive and (archive.exists() or archive.is_symlink()):
        raise ValueError("Archive already exists; choose a new filename.")
    if archive and (not archive.parent.is_dir() or archive.is_relative_to(destination)):
        raise ValueError("Archive parent must exist and archive must be outside the package.")
    commit = subprocess.run(
        ["git", "-C", str(source), "rev-parse", "HEAD"],
        check=True, capture_output=True, text=True,
    ).stdout.strip()
    guide = source / "support/thumbnail-faces/share"
    files = {name: source / name for name in THEME_FILES}
    files.update({name: guide / name for name in GUIDE_FILES})
    manifest = {}
    with tempfile.TemporaryDirectory(prefix="thumbnail-package-", dir=destination.parent) as temp:
        staging = Path(temp) / "package"
        staging.mkdir()
        for name, original in files.items():
            if original.is_symlink() or not original.is_file() or not original.resolve().is_relative_to(source):
                raise ValueError(f"Missing or unsafe package source: {name}")
            data = original.read_bytes()
            target = staging / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
            manifest[name] = hashlib.sha256(data).hexdigest()
        (staging / "PROVENANCE.json").write_text(json.dumps({
            "source_repository": "https://github.com/tadl/tadl-pawtucket2-theme",
            "source_commit": commit,
            "description": "Working-copy snapshot; SHA-256 values describe the packaged bytes.",
            "sha256": manifest,
        }, indent=2) + "\n", encoding="utf-8")
        if destination.exists() or destination.is_symlink():
            raise ValueError("Output directory appeared during export; refusing to replace it.")
        staging.rename(destination)
    if archive:
        with zipfile.ZipFile(archive, "x", compression=zipfile.ZIP_DEFLATED) as bundle:
            for name in sorted((*files, "PROVENANCE.json")):
                bundle.write(destination / name, "thumbnail-faces/" + name)
    return len(files) + 1


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output-dir", type=Path, help="New package directory (never overwritten)")
    parser.add_argument("--archive", type=Path, help="Optional new ZIP filename outside the package")
    args = parser.parse_args()
    source = Path(__file__).resolve().parents[1]
    destination = (args.output_dir or source.parent / "thumbnail-faces").absolute()
    archive = args.archive.absolute() if args.archive else None
    try:
        count = export_package(source, destination, archive)
    except (OSError, ValueError, subprocess.CalledProcessError) as error:
        parser.exit(1, f"Package export failed: {error}\n")
    print(f"Exported {count} files to {destination}")
    if archive:
        print(f"Shareable ZIP: {archive}")


if __name__ == "__main__":
    main()
