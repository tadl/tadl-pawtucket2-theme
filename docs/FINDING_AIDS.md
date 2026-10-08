# Collection finding aids

The initial **Download finding aid** action replaces the generic collection
summary PDF link. It generates a letter-size PDF for the collection being viewed,
including readable descendant collections and counts of their accessible cataloged
objects. It does not redirect the export to the hierarchy's
top-level collection.

## Initial document

- Collection name and identifier, library identification and generation timestamp.
- Description, dates, extent, scope/content, language and rights when populated
  and readable. Missing fields and punctuation-only dates have no heading.
- Complete readable collection hierarchy, with each subcollection indented under
  its parent and its name, readable identifier and directly linked object count.
  Every descendant is listed, including empty subcollections. Siblings sort
  naturally by name (for example, Drawer 2 before Drawer 10); each whole branch
  stays together. Counts can overlap when an object belongs to multiple
  collections; the overall total counts each object only once by its internal ID.
- Collection contents total, including objects in readable descendant collections.
  Objects with matching titles/identifiers remain distinct records in the count.
- Page numbers and wrapping text. Individual object titles, accession numbers,
  dates, memberships and storage locations are omitted.

These are counts of current catalog records, not a claim that every physical
item has already been cataloged. The document intentionally counts records
without representations, regardless of the **Only items with media** preference.
That preference controls display, not access permissions.

## Collection field mappings

`conf/finding_aid.conf` defines collection metadata fields. Each
mapping has a label and a list of candidate native bundles; the first populated,
readable bundle wins. Missing elements are skipped without probing nonexistent
metadata. Mappings allow field paths within the subject table, not arbitrary
display templates or PHP.

The 2026-10-08 change replaces the object inventory with counts. The former
`inventory_fields` and `include_storage_locations` configuration entries are no
longer used. Object metadata and storage records are not loaded for this summary.

## Implementation and access

The theme download URL is `/CollectionFindingAid/Download/collection_id/<id>`.
Do not use `FindingAid` as the controller name: Pawtucket's native dispatcher
treats that prefix as its bundled plugin, even when the plugin is disabled, and
looks for a plugin `DownloadController` instead of the theme controller. Deploy
the renamed controller and updated collection detail view together; then reload
the collection page and use its updated link. The original `/FindingAid/Download/`
URL is not the theme endpoint.

- `controllers/CollectionFindingAidController.php`: GET-only download, selected collection
  validation, login requirements, configuration, generation and safe filename.
- `helpers/finding_aid.php`: native model traversal, record/type/source/bundle/ACL
  checks, accessible collection metadata, unique object counts and Dompdf rendering.
- `views/Details/finding_aid_pdf_html.php`: escaped text and PDF styling.
- `views/Details/finding_aid_binary.php`: PDF attachment with `private, no-store`
  and `nosniff` headers.
- `views/Details/ca_collections_default_html.php`: selected collection's link.

Private, deleted or unreadable collections and objects are
excluded. Unreadable collection branches are not traversed, and relationship and
hierarchy bundle restrictions are honored. No private-record placeholders or
counts are included.

Hierarchy traversal visits every readable descendant without a paging or
media-presence filter. Parent IDs and relative depths preserve the actual tree;
sibling sorting cannot interleave distinct branches with identical names. Cycle
protection lists each collection once. A selected subcollection is the root of
its own finding aid, with all of its readable descendants.

Object IDs are deduplicated before checking access, preserving all readable
memberships for the collection counts. Related-item queries explicitly remove the native default cap
(1000/4000 in reference APIs), so the export is not a first-page preview. Each
object is loaded once for native access checks; individual metadata and storage
queries, object sorting and per-object PDF rendering are skipped.
Very large collections may need a later background-export
workflow; there is currently no silent truncation or shared PDF cache.

The export uses Dompdf already supplied by Pawtucket, with remote resources,
embedded PHP and JavaScript disabled. It never reuses the native generic summary
PDF cache in `export/`, avoiding stale or access-context-independent documents.
An unavailable renderer or generation failure returns HTTP 503 with a readable
message. Requests have at least 180 seconds when PHP has a configured execution
limit; infrastructure timeouts still apply.

## Verification and deployment

Run `php tests/finding_aid_test.php` for synthetic controller, access, hierarchy,
duplicate-membership counts, collection fields, omission of individual object
metadata/entries, escaping and renderer boundaries. It includes a 4,105-object
collection to catch default-cap regressions, plus empty and single-item totals,
nested/empty/200-sibling hierarchies and distinct branches with identical names.

Optionally verify URL parsing through the actual Pawtucket dispatcher:

```sh
TADL_TEST_REQUEST_DISPATCHER=/path/to/pawtucket/app/lib/Controller/RequestDispatcher.php \
php tests/finding_aid_test.php
```

This uses synthetic controller/plugin directories, reproduces the bundled
`FindingAid` collision and verifies that the new route resolves to the existing
theme controller/action with the selected collection ID. It does not bootstrap
the application, invoke the plugin or connect to a database.

To verify with an available reference Composer runtime:

```sh
TADL_TEST_COMPOSER_AUTOLOAD=/absolute/path/to/pawtucket2/vendor/autoload.php \
  php tests/finding_aid_test.php
```

For a disposable synthetic sample, also set `TADL_TEST_FINDING_AID_PDF` to an
absolute path outside this repository. Never commit samples, real inventories,
private catalog exports or generated PDFs. Render sample pages with Poppler and
inspect layout and extracted text. Standalone tests do not bootstrap the catalog
or connect to its database.

The initial inventory implementation passed synthetic/native-parser and real
Dompdf checks. The count-only revision retains those access and rendering
boundaries and uses a synthetic sample with overlapping collection memberships
and child-only objects. All twenty-four standalone suites and changed PHP lint
passed. Real Dompdf generation/native dispatcher checks passed; Poppler text and
visual inspection confirmed the updated four-page sample with all 36 collection
nodes through three descendant levels, long/non-ASCII names, 56 unique items,
overlapping 55-item collection counts and no individual object entries.
Production field applicability, data and performance
remain deployment-time checks. Source commits do not deploy.

## Questions for the archives team

- Define collection organization and ordering: series,
  subseries, box/folder, dates or a manual archival arrangement.
- Specify narrative sections such as administrative/biographical history,
  provenance, processing notes, access/use restrictions and preferred citation.
- Decide whether the finding aid should describe only cataloged objects or also
  uncataloged physical holdings, and whether a separate detailed staff inventory
  export is needed.
- Supply an approved example and decide whether later HTML, CSV, EAD/XML or
  archival-standard output is wanted alongside PDF.
