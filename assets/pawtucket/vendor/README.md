# TileViewer keyboard adaptation

Bundled from the Pawtucket 2.0.10 TileViewer 3.0.2 source (upstream license remains in the file). Original SHA-256: `e2b8531e760c618045595f89d04f2bd2b8d49cbb9bc0490110572db1151b81a8`.

Only the keyboard-registration block changes: shortcuts bind to the focusable image canvas and the Tab shortcut is removed. Native loading, controls, annotations and media callbacks remain intact. The theme asset loader substitutes this file for the native TileViewer script; the original is not loaded twice. Review this patch when upgrading Pawtucket.
