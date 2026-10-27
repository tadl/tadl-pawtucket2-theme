# TileViewer theme adaptation

Bundled from the Pawtucket 2.0.10 TileViewer 3.0.2 source (upstream license remains in the file). Original SHA-256: `e2b8531e760c618045595f89d04f2bd2b8d49cbb9bc0490110572db1151b81a8`.

Theme changes are limited to keyboard registration and zoom controls:

- Shortcuts bind to the focusable image canvas, and the Tab shortcut is removed.
- Slider bounds cover the engine's configured maximum (normally 400%) and an already larger fitted image. The initial value matches the actual zoom; fractional steps avoid whole-percent jumps. Fit and subsequent zoom actions synchronize the existing widget through its value API, extending bounds when a resized fit requires it.
- Slider and button zoom use the center of the overscanned canvas. Absolute slider zoom preserves the image point at that anchor across levels and partial edge tiles.

Native tile loading, annotations and media callbacks remain intact. The theme asset loader substitutes this file for the native TileViewer script; the original is not loaded twice. Review these adaptations when upgrading Pawtucket.

`tests/tileviewer_zoom_browser_test.js` exercises the actual engine and native jQuery UI with synthetic small, large and tall images at desktop/mobile sizes. It reproduces the old reversed-range handle-drag exception, then checks real handle drags, endpoints, image scale/position, button and canvas zoom, Fit and configured limits. It uses the same development browser dependencies and static reference tree as the other browser checks.
