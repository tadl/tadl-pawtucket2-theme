# Collection finding aids

The initial **Download Finding Aid** action replaces the generic collection
summary PDF link. It generates a letter-size PDF for the collection being viewed,
including readable descendant collections and a complete inventory of their
accessible cataloged objects. It does not redirect the export to the hierarchy's
top-level collection.

## Initial document

- Collection name and identifier, library identification and generation timestamp.
- Description, dates, extent, scope/content, language and rights when populated
  and readable. Missing fields and punctuation-only dates have no heading.
- Collection organization, with the number of readable objects directly linked
  to each collection. Counts can overlap when an object belongs to multiple
  collections; the inventory total counts each object only once.
- Numbered object entries with title, identifier/accession number, legacy accession
  number, dates, recorded storage location and collection/series memberships.
- Natural identifier ordering, with unidentified records last; title and internal
  object ID break ties. Distinct records with matching titles/identifiers stay distinct.
- Page numbers and wrapping text. Inventory entries stay together when they fit
  on a page; long entries can flow onto subsequent pages.

This is an inventory of current catalog records, not a claim that every physical
item has already been cataloged. The document intentionally includes records
without representations, regardless of the **Only items with media** preference.
That preference controls display, not access permissions.

## Field mappings and locations

`conf/finding_aid.conf` defines collection metadata and inventory fields. Each
mapping has a label and a list of candidate native bundles; the first populated,
readable bundle wins. Missing elements are skipped without probing nonexistent
metadata. Mappings allow field paths within the subject table, not arbitrary
display templates or PHP.

Initial inventory mappings:

| Label | Source |
| --- | --- |
| Identifier / accession number | `ca_objects.idno` |
| Legacy accession number | `ca_objects.legacy_accession_number` |
| Dates | `ca_objects.date.dates_value` |
| Recorded storage location | Readable `home_location_id`, otherwise readable related `ca_storage_locations` records |

Location labels include the storage record's identifier when readable. Geographic
places are not treated as physical storage. Home/related locations are not claimed
to be verified current locations, and movement histories are not exported. Locations
require readable object location bundles and readable, public, nondeleted storage
records, including their own label/identifier bundle permissions. Set
`include_storage_locations = 0` to omit them.

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
  checks, accessible metadata, unique inventory and Dompdf rendering.
- `views/Details/finding_aid_pdf_html.php`: escaped text and PDF styling.
- `views/Details/finding_aid_binary.php`: PDF attachment with `private, no-store`
  and `nosniff` headers.
- `views/Details/ca_collections_default_html.php`: selected collection's link.

Private, deleted or unreadable collections, objects and storage records are
excluded. Unreadable collection branches are not traversed, and relationship and
hierarchy bundle restrictions are honored. Counts reflect the same checks as
inventory entries. No private-record placeholders or counts are included.

Object IDs are deduplicated before loading metadata, preserving all readable
memberships. Related-item queries explicitly remove the native default cap
(1000/4000 in reference APIs), so the export is not a first-page preview. Each
object is loaded once for native access checks; repeated storage labels are cached
only within the export. Very large collections may need a later background-export
workflow; there is currently no silent truncation or shared PDF cache.

The export uses Dompdf already supplied by Pawtucket, with remote resources,
embedded PHP and JavaScript disabled. It never reuses the native generic summary
PDF cache in `export/`, avoiding stale or access-context-independent documents.
An unavailable renderer or generation failure returns HTTP 503 with a readable
message. Requests have at least 180 seconds when PHP has a configured execution
limit; infrastructure timeouts still apply.

## Verification and deployment

Run `php tests/finding_aid_test.php` for synthetic controller, access, hierarchy,
duplicate-membership, field, location, escaping and renderer boundaries. It
includes a 4,105-object inventory to catch default-cap regressions.

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

The initial implementation passed thirteen standalone suites, PHP lint, the
native reference configuration parser and real Dompdf generation. A 55-entry
synthetic PDF was rendered and visually inspected, including a long title,
non-ASCII text and multipage inventory. Production field applicability, data and
performance remain deployment-time checks. Source commits do not deploy.

## Questions for the archives team

- Confirm which field is the authoritative accession number and whether legacy
  numbers should appear separately.
- Confirm the public location convention: holding library, box/folder, home
  storage, current movement-derived location, or an approved descriptive field.
- Define collection organization and ordering: accession numbers, series,
  subseries, box/folder, dates or a manual archival arrangement.
- Specify narrative sections such as administrative/biographical history,
  provenance, processing notes, access/use restrictions and preferred citation.
- Decide whether the finding aid should describe only cataloged objects or also
  uncataloged physical holdings, and whether a separate staff export is needed.
- Supply an approved example and decide whether later HTML, CSV, EAD/XML or
  archival-standard output is wanted alongside PDF.
