# October 20 theme audit remediation

This tracks the 26 concerns from the theme review and the resulting source changes.
It is a remediation record, not a whole-site WCAG conformance certificate or proof
of deployment. Catalogue content and complete authenticated workflows still need
acceptance testing in the deployed application.

## Outcomes

| # | Concern | Result |
| --- | --- | --- |
| 1 | TileViewer intercepts Tab and global character shortcuts | The reviewed theme copy of TileViewer binds shortcuts to its focusable canvas and leaves Tab to the browser. The asset loader substitutes it once, preserving native initialization and callbacks. Provenance and the upgrade boundary are in the vendor README. |
| 2 | Lightbox Options cannot be reached or named from the keyboard | Named buttons expose expanded state and control ordinary linked lists, with Tab/Escape navigation. Open-menu semantics and heading contrast are covered by axe. |
| 3 | Lightbox actions appear only on hover and cards expand | Actions remain visible; card dimensions stay stable on hover and focus. Native ownership/write checks remain in charge of which actions exist. |
| 4 | Delayed gallery responses overwrite newer selections | Superseded requests are aborted and revision-checked. Media and information commit together; failed selections preserve the previously matched item and report an error. |
| 5 | Hierarchy icons create unnamed redundant links | Ordinary title links have no redundant icon link. Collapse-mode branches retain a separate named detail link. |
| 6 | Image-link names expose filenames | Featured, object-detail and gallery images use public record context or descriptive fallbacks. Native scripts and callbacks are preserved, and literal dollar signs/backslashes remain text. |
| 7 | Lightbox form values break attribute/textarea contexts | User group, add-item and sharing values are escaped for their output contexts. Tests round-trip apostrophes, quotes, ampersands and literal closing tags. |
| 8 | Generated PDFs lack tagging/language structure | The viewer offers an equivalent **Read as text** finding aid with semantic headings, description lists and nested hierarchy. A no-script collection link also reaches it. Dompdf PDFs remain untagged; this provides an accessible HTML alternative rather than claiming PDF/UA compliance. |
| 9 | Revealing more gallery thumbnails loses focus | Focus moves to the first newly revealed thumbnail before the More button disappears. |
| 10 | Featured clips are clickable list items | Native buttons retain the existing seek/time callbacks and use a section heading. |
| 11 | Collection loaders leave an indefinite spinner after failure | Both loaders expose busy/error state; contents failures retain a direct result-page link, also available without JavaScript. |
| 12 | Comment fields and write outcomes are not announced | Comments have an associated label, status/error regions, transport-failure feedback and focus handling after deletion. Native CSRF and set access remain intact. |
| 13 | A collection thumbnail reads every candidate | Keyset windows return at most 32 candidates per unresolved root and stop after a permitted usable image. Tests cover 10,001 candidates and continuation past denied candidates. |
| 14 | Collection paging loads descendant models and builds long OR searches | Active scopes, media presence and counts use scalar hierarchy rows with bulk visibility checks. Indexed membership unions feed native Browse object-ID filters. The existing theme plugin handles both place and collection contents; missing plugin code fails closed. |
| 15 | Repeated media-only requests decode complete descriptors | Current permissions and membership are still checked before pagination. Descriptor validity alone can be reused through the native cache, keyed by the current SHA-256 descriptor fingerprint, site, representation and decoder. Warm checks avoid fetching/decoding the blob; changed bytes invalidate the entry. |
| 16 | Every finding-aid view/download runs Dompdf again | Access-filtered descriptive data is rebuilt first. Identical data and renderer/template identity can reuse immutable PDF bytes for at most one day; changed data or permissions change the key. Page HTML and permissions are not persisted. |
| 17 | Closed document text includes the complete OCR payload | Initial discovery uses scalar PDF IDs and bounded text previews. Full text is requested on expansion through a GET-only endpoint that repeats access checks; direct visits work as normal pages. Source precedence, complete text and private-pair exclusions remain intact. |
| 18 | Collection entry points choose different images | Index, Search and Browse share the native-collection/direct-object/descendant image policy. |
| 19 | Small derivatives are enlarged in wide/high-DPI grids | Responsive `srcset`/`sizes` offers existing same-representation derivatives with compatible geometry. Originals, icons and queued media are excluded; List rows use a narrow size hint. Cover crops and normalized focal data remain valid without rerunning detection. |
| 20 | Media-only authority filtering applies to Browse only | Search and multisearch use the same authority policy before counts/paging/context, including descendant places and related family members. |
| 21 | Families are absent from global/advanced search | Families now have overview/full search configuration, a native family-type restriction and an advanced-search form/tab. |
| 22 | Rich metadata retains editor presentation styles | Shared rich-value and gallery rendering strips presentation attributes through the native sanitizer policy while preserving semantic formatting and links. Stored content is unchanged. |
| 23 | CSS imports delay font discovery | Both shared and presentation document heads discover the font stylesheet directly, with preconnect hints and swap/fallback behavior. |
| 24 | Metadata uses form labels; access actions nest controls | Metadata uses headings. Lightbox access changes/removals use native buttons without interactive nesting. |
| 25 | Direct contribution routes remain enabled | The sample contribution controller is disabled in its native configuration gate; institutions must explicitly configure and enable a real mapping before using it. |
| 26 | Preview links announce a format-list label instead of its values | Format lists sit outside image/title links. Titles remain link names; each format remains readable as a separate list item. |

## Verification

The portable PHP suite, JavaScript/PHP/shell syntax checks and whitespace checks
run without a production database or catalogue exports. Focused fixtures cover
current type/source/bundle/access/ACL boundaries, inaccessible ancestors, cycles,
wide hierarchies, media replacement and failed requests. Real installed Dompdf
and HTMLPurifier checks supplement the synthetic library boundaries.

Browser regressions use local synthetic content, actual theme templates/styles
and the reference Pawtucket 2.0.10 browser libraries. They cover:

- Axe, reflow, enlarged/spaced text and keyboard flows at 320 and 1280 pixels,
  including an open Lightbox menu, gallery races/failures, modal focus and lazy
  document text.
- Object Tiles/List, overview and Lightbox cards at 320–1600 pixels, including
  fixed 3:2 crops, format layout, reduced motion, touch navigation and AJAX insertion.
- Native PDF.js loading/rendered/zoomed layouts, one document scroller, background
  scroll locking/restoration, paging, search, download and close/reopen at desktop
  and phone widths.

These checks do not establish real mail delivery, live sharing/access transitions,
all representation types or screen-reader behavior across platforms. The PDF
alternative preserves descriptions; it does not add tagged structure to Dompdf's
binary output. See [Accessibility](ACCESSIBILITY.md) and
[Production verification](PRODUCTION_CHECKS.md) for the remaining acceptance scope.

## Operator boundary

Deploy the complete theme, including controllers, helpers, assets and `plugins/`,
through the normal operator workflow. No new database index, schema migration,
face-detection run, runtime installation, cache purge or Apache restart is required.
The supplied site-root robots policy now also covers `DocumentText`; review/merge
that file separately because theme deployment does not replace `/robots.txt`.
No deployment, production write, cache flush or service restart was performed
for this remediation work.
