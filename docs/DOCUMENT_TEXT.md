# Object document text

Object detail pages show a collapsed **Document text** section below the media
and metadata columns when readable text and at least one accessible attached
PDF are available. Native HTML `details`/`summary` provides keyboard operation
without JavaScript. Expanded text spans the content width, preserves line/page
breaks, wraps long words and can be selected/copied.

## Source selection

1. Use populated `ca_object_representations.media_content` from each accessible
   attached PDF. Multiple PDFs appear in native rank/primary order with a stable
   representation-ID tie break. Readable representation labels identify them;
   unnamed or restricted labels use numbered PDF headings. Any readable populated
   extraction takes precedence over object metadata; blank PDFs are omitted.
2. Otherwise use populated `ca_objects.transcription`, the migrated Omeka/Scripto
   metadata element, when the visitor can read that bundle. Multiple values in
   the visitor's selected locale are separated by a blank line. Empty values and
   metadata-element defaults do not supply text.
3. Otherwise use populated, readable `ca_objects.pdf_text` with the same metadata
   value handling.
4. If all three sources are empty, missing or restricted, omit the section and
   its heading entirely. Whitespace alone does not count as text.

The accessible-PDF requirement also applies to imported text. An object with
only a public TIFF and a private paired PDF has no document-text section. The
theme does not recover text from hidden representations or expose native editing
transcription records.

All sources are displayed as escaped plain text, including literal angle
brackets. Stored HTML is not executed or rendered as rich text. CRLF/CR endings
become newlines and PDF form-feed page breaks become blank lines. No truncation
or text generation occurs during the request.

## Native permissions and extraction

`helpers/document_text.php` uses the current visitor's native access mask and
`getRepresentations()` attachment filtering. Loaded objects and PDFs must be
nondeleted, readable under type/source/bundle permissions and allowed by native
Pawtucket item ACLs. The representation `media` bundle must be readable; reading
extracted text additionally requires `media_content`. Each metadata fallback
requires its object's `transcription` or `pdf_text` bundle respectively. Restricted
sources are not fetched. An empty access mask fails closed.

The current native search-index configuration includes all object metadata via
`_metadata`, including `transcription` and `pdf_text`, and attached representation
`media_content`. Ordinary keyword search can therefore match all sources once
they have been populated and indexed. The display section does not change the
search index or trigger a rebuild.

PDF text must already be present in `media_content`. CollectiveAccess's native
PDF processing extracts selectable text using local PDF tools when media is
processed; this section does not run OCR, launch extraction tools, call an
external service, or reprocess files. An image-only PDF without imported text
has nothing to display. Existing text extraction/search indexing configuration
and the planned import remain separate operational work.

## Integration and verification

- `views/Details/ca_objects_default_html.php` loads the helper and places the
  section after both columns, outside their responsive grid.
- `assets/pawtucket/css/theme.css` supplies scoped layout, wrapping and focus
  styles; the existing asset-content versions handle CSS changes.
- `tests/document_text_test.php` tests all three sources and precedence, empty and
  missing fields, private pairs, access/deletion/ACL/bundle restrictions and
  fallbacks, escaping, Unicode/line breaks, multiple PDFs and actual template
  placement.

Run `php tests/document_text_test.php`, then the full standalone suite loop in
`AGENTS.md`. Tests use synthetic records and do not need an application database.

During development on 2026-10-08, a read-only check against the installed native
Pawtucket models verified a newspaper PDF with both extracted text and populated
object transcription. The candidate helper selected the complete extraction with
an anonymous public access mask. Only source names, counts and lengths were
reported; the candidate was evaluated through PHP stdin without installing files
or changing catalogue records. Synthetic tests additionally cover both metadata
fallbacks and their permissions. This does not establish that the planned
transcription import is complete.

All twenty-five standalone suites and changed PHP lint passed. A synthetic
browser preview with the actual object template and styles verified full-width
placement at 1440px, a single-column layout at 390px, readable 18px text and long
word wrapping without horizontal overflow. Enter/Space expanded/collapsed the
section with a visible focus outline and disclosure marker.

Deploy the helper, object view and stylesheet together through the normal theme
workflow. Source commits/pushes do not deploy them. After deployment, check a
text-bearing newspaper PDF, a PDF with blank extraction and populated
transcription, a PDF with only populated `pdf_text`, an image-only PDF with no
text, and a TIFF with a private paired PDF. Confirm collapsed/expanded
keyboard behavior and wrapping at desktop and phone widths.
