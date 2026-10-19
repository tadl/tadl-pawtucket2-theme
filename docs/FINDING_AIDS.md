# Collection finding aids

The **Finding aid** line offers **Download** and **View** for a descriptive PDF
of the selected archival unit and its complete readable hierarchy. Download sends
an attachment; View opens Pawtucket's existing PDF.js document viewer in the media
overlay, with paging, zoom, text search and download controls. The existing dialog
handles keyboard focus and returns focus to View when closed. Modified clicks
and browsers without the panel follow a direct inline-PDF link instead; browser
PDF settings can override inline viewing. Both options use the same access-checked
document. No Providence representation or stored export is created.
Series, subseries, boxes, folders,
volumes and other components use their own recorded metadata. A folder can
correctly describe 80 clippings without having 80 individual object records.

The PDF contains no catalogue-object totals, “directly linked items” counts,
object inventories or messages implying that a component without linked objects
is physically empty. Extent and scope come from staff descriptions in Providence.
The export does not query objects, representations or storage locations.
Browser counts remain available on collection pages; they describe catalogue
results and are separate from the finding aid.

## Document contents

- Selected unit's title, public identifier, library identification and generation
  timestamp. The timestamp is not represented as a description revision date.
- Populated, readable metadata once in the opening summary.
- Every readable descendant under its actual parent, with title, public
  identifier, recorded archival level and all configured populated fields.
  The level follows the title in normal-weight parentheses, such as
  **Box 1** (Container) and **Folder 1** (Folder), including for the selected root.
  It is not repeated as a separate “Level of description” field. Missing or
  restricted levels have no suffix.
- Readable body text, paragraph and line breaks, and page numbers. Editor HTML
  becomes escaped plain text; scripts, styles and executable markup are excluded.
- Recorded hierarchy rank first, natural title order for equal ranks, then ID
  for deterministic ties. Complete branches stay together. Set
  `hierarchy_sort = name` to explicitly use natural title order instead.

Missing or restricted metadata is omitted without an empty heading or an invented
statement. Extent, dates and other values are not copied down from a parent or
inferred from database record counts. Components without separately catalogued
objects or media still appear.

The selected unit is the export root, including when it is a series or folder.
Its metadata appears once in **About this collection**; descendants follow under
**Arrangement and contents**. The collection page's flat/hierarchy mode, browser
display depth, pagination and media preference do not limit the PDF.

## Field mappings

Adapt `conf/finding_aid.conf` to your Providence installation profile. The same
`collection_fields` mapping applies independently to the root and every descendant.
Mappings are ordered; each contains a label and candidate native bundle paths.
The first existing, populated, readable candidate supplies the field. Removing a
mapping removes that field at every level. Paths stay within the record's table;
arbitrary display templates and PHP are not accepted.

The supplied mappings cover:

| Information | Native bundle or mapping |
| --- | --- |
| Repository and level | `ca_collections.repository`, `ca_collections.type_id` |
| Creators | `ca_entities` relationships restricted to the `creator` role |
| Dates | `ca_collections.date.dates_value`, then supported `unitdate` value paths |
| Extent | `ca_collections.extent_text`, then `ca_collections.extent` |
| Languages | `ca_collections.language`, then `ca_collections.langmaterial` |
| Abstract and description | `ca_collections.abstract`, `ca_collections.description` |
| Source of description | `ca_collections.description_source` |
| History, scope and arrangement | `adminbiohist`, `collection_scope_content` / `scopecontent`, `arrangement` |
| Provenance and acquisition | `provenance` / `custodhist` / `custohist`, `acqinfo`, `accruals` |
| Access conditions | `accessrestrict`, `physaccessrestrict`, `techaccessrestrict` |
| Rights and use | `rights.rightsText`, `rights.copyrightStatement`, `reproduction` |
| Subjects | `lcsh_terms` |
| Related resources | `originalsloc`, `altformavail`, `relatedmaterial`, `external_link.url_line2.url_entry`, `otherfindingaid` |
| Preparation and citation | `processInfo` / `processinfo`, `preferCite` |

All shortened paths in the table have the `ca_collections.` prefix in the actual
configuration. The local profile provides description, source, dates, extent,
language, provenance, scope/content, subjects, rights and related links. Other
mappings support common DACS/ISAD profiles but do not create absent fields.
Dates use recorded date values; typed inclusive/bulk-date labels need a
profile-specific extension if your installation stores them separately.

Creator relationships use the native `ca_entities_x_collections` relationship
types. Only configured valid roles are queried; unknown types fail closed. Names
are deduplicated by entity ID and independently checked for record, type, source,
label and Pawtucket ACL access. A general related entity is not assumed to be a
creator, donor or repository. Reference-valued attributes such as rights-holder
entity records are not automatically added as unchecked names.

For `ca_collections.language`, `display_language_labels = 1` matches the existing
collection detail behavior: selected language terms are descriptive labels, even
when those vocabulary terms have default private access. This exception applies
only to that exact attribute when its native datatype is List. The collection
and language bundle still require their normal permissions; unrelated fields,
related entities and other datatypes retain access filtering. Set the option to
`0` to require language-term access as well.

Never enumerate every metadata element automatically. Internal notes,
preservation-system identifiers, private donor details and staff-only storage
instructions do not belong in a public finding aid. Any newly mapped acquisition,
processing or access note must be appropriate for the public bundle permissions.

## Implementation and access

The theme endpoints are `/CollectionFindingAid/Download/collection_id/<id>` for
attachments and `/CollectionFindingAid/View/collection_id/<id>` for inline PDFs.
`/CollectionFindingAid/Viewer/collection_id/<id>` provides the native PDF.js
overlay markup. It checks access before rendering; the PDF request independently
checks access again. Opening the overlay does not generate a second PDF.
`FindingAid` is reserved by Pawtucket's bundled plugin during native URL parsing;
the old `/FindingAid/Download/` URL is not this theme endpoint. Both collection
links use sentence case, ordinary detail-link styling and `rel="nofollow"`.
View has an eye icon and `aria-haspopup="dialog"`, and invokes the existing
`caMediaPanel` only on an ordinary unmodified activation. Its direct PDF `href`
remains usable without JavaScript, through context menus and with modifier keys.

- `controllers/CollectionFindingAidController.php`: GET-only actions sharing selected
  unit validation, configured login requirements, safe filename and diagnostics.
- `helpers/finding_aid.php`: checked native hierarchy traversal, descriptive
  metadata and creator lookup, ordering and Dompdf rendering.
- `views/Details/finding_aid_pdf_html.php`: escaped text and print layout.
- `views/Details/finding_aid_binary.php`: inline or attachment PDF with `private, no-store`,
  indexing directives and `nosniff` headers.
- `views/Details/ca_collections_default_html.php`: selected unit's Download/View links.
- `views/mediaViewers/pdfjs.php` and `viewerWrapper.php`: existing document viewer
  and close/download controls, reused without a second viewer implementation.

Private, deleted or unreadable units are excluded. Unreadable branches are not
traversed. Record/type/source access, bundle readability and Pawtucket ACLs apply
to every included unit. Hierarchy and creator-relationship restrictions are
honored. There are no hidden-record placeholders or counts. Independent models,
parent IDs, relative depths and cycle protection preserve the tree without
interleaving identically named branches or imposing a first-page cutoff.

The export uses the Dompdf supplied by Pawtucket. Remote resources, embedded PHP
and JavaScript are disabled. Generated PDFs are not stored in a shared export
cache. An unavailable renderer or generation failure returns a readable HTTP 503.
PHP execution limits are raised to at least 180 seconds where configured;
infrastructure timeouts still apply. Very large descriptive hierarchies may
ultimately need background exports, but no work scales with linked-object counts.

The layout uses US Letter portrait, 0.75-inch margins and 10.5-point body text.
Long fields flow across pages, with a heading kept with the start of its fields.
Indentation is capped to preserve readable line widths in very deep trees.

## Verification

Run `php tests/finding_aid_test.php` for synthetic controller, metadata, creator
privacy, field/bundle restrictions, ordering and hierarchy regressions. Fixtures
cover a folder with a physical extent and no object records, all descriptive
levels, missing/custom mappings, private branches, denied language labels,
selected-subunit exports, identical branch names, cycles and 200 siblings.
Object queries and models throw in the fixture, including for a root seeded with
4,105 linked IDs: those IDs must have no effect on the document.

Optionally exercise native URL parsing and real rendering against an available
reference installation:

```sh
TADL_TEST_REQUEST_DISPATCHER=/path/to/pawtucket2/app/lib/Controller/RequestDispatcher.php \
TADL_TEST_COMPOSER_AUTOLOAD=/path/to/pawtucket2/vendor/autoload.php \
php tests/finding_aid_test.php
```

To check the actual PDF.js browser integration, install the same development
Playwright tools used by the accessibility suite and run:

```sh
TADL_TEST_COMPOSER_AUTOLOAD=/path/to/pawtucket2/vendor/autoload.php \
node tests/finding_aid_viewer_browser_test.js
```

Set `TADL_REFERENCE_ROOT` if the reference Pawtucket assets are elsewhere, and
`TADL_BROWSER_CHANNEL=chrome` to use an installed Chrome instead of Playwright's
Chromium. The optional check renders real synthetic PDF bytes locally and covers
paging, text search, download, closing/reopening, focus restoration and desktop/
phone widths. It never bootstraps the catalogue or contacts a production site.

Also set `TADL_TEST_FINDING_AID_PDF` to an absolute disposable output path outside
this repository to generate a synthetic sample. Render every page with Poppler
and inspect layout and extracted text, including full long descriptions and
metadata at every level. Do not commit samples or catalogue exports. Standalone
checks do not bootstrap the catalogue or connect to its database.

After an authorized deployment, compare a populated collection, a nested folder,
a metadata-only component and a restricted unit against their Providence fields.
Check ordering, language labels, creator roles and both Download/View for your profile. Deploy the
configuration, helper and views together. No new dependency, schema change,
cache purge or service restart is required. A source push does not deploy.

## Remaining design work

This implements the descriptive-metadata portion of the review specification,
not full archival-standard or PDF accessibility conformance. The supplied
Dompdf export is not tagged. A linked contents list, PDF bookmarks, running
collection headers, accessible-PDF structure, approved repository/contact
mappings and public parent-collection context for subunit exports remain further
work. No curated item-level inventory is appended automatically. Archives staff
still need to review the actual descriptions and approve public access/use notes.
