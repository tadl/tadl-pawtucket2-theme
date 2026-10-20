# WCAG 2.2 AA audit and maintenance

Audit date: October 16, 2026. Target: [WCAG 2.2 Level AA](https://www.w3.org/TR/WCAG22/).
This records source remediation and its evidence. It is not a declaration that
every deployed page, catalogue item, document, viewer or complete account process
conforms. The remaining acceptance work below is part of reaching that target.

## October 20 follow-up

[Audit remediation](AUDIT_REMEDIATION.md) records the later 26-issue source pass.
The current suite has 43 PHP programs, with 12 browser page states at 320/1280px,
an open Lightbox menu, gallery race/failure tests, canvas-scoped native keyboard
shortcuts and lazy document text. Grid regressions cover 320–1600px; native PDF.js
checks include scrolling, paging/search/download and focus restoration. The viewer
provides a semantic HTML finding-aid alternative; the Dompdf PDF is still untagged.
The older counts below describe the October 16 checkpoint, not current coverage.
Full deployed/content/process and assistive-technology acceptance remains required.
The per-page picker uses a labeled native select that updates results on change,
with an Apply button for the no-JavaScript GET fallback. Its separate browser regression
covers 320–1600px reflow, automated A/AA checks on the result controls, paging and
cross-page display preferences. Subsequent collection pages omit repeated metadata
and retain a link back to the complete description.

## Coverage and evidence

- Inventoried all theme view areas, helpers, controllers, styles and scripts.
  Reviewed the shared shell, forms, navigation, result views, disclosure/popup
  interactions, galleries, accounts, lightboxes, cookie UI, viewer wrappers,
  contribution controls and error/status presentation. Copied alternatives and
  optional native views remain in scope when enabled; inventory alone does not
  establish their runtime accessibility.
- Read-only public baseline: 32 routes covering home, About, Contact, six advanced
  search forms, eight browse targets, search previews, Tiles/List results,
  collections, gallery, object and authority details, login/reset, cookies and
  a missing page. No forms were submitted and no account or catalogue data changed.
- Repeated baseline findings across those pages: 32 invalid form-role instances,
  33 nameless identifier links, nine repeated pagination landmark names, two
  nested banner landmarks and one low-contrast notification. These are element
  counts across sampled pages, not independent defects or a complete WCAG census.
  Some landmark/ARIA findings are axe best-practice checks.
- The changed actual header/footer and component templates are rendered with
  synthetic request/configuration/model boundaries. Browser checks use native
  jQuery, Bootstrap and panel/sortable assets from Pawtucket 2.0.10, including its
  existing interactions. No production application or database is bootstrapped.
- Browser coverage: nine page states at 1280px and 320px, plus an open login
  dialog. Axe covers WCAG A/AA through 2.2 and best practices. Checks also exercise
  200% computed text sizing, text spacing, page overflow, skip-link focus, mobile
  navigation, gallery replacement focus, hidden carousel slides, modal Tab/Escape
  and restored focus, cookie radios, and lightbox reorder success/failure rollback.
- All 36 portable PHP suites pass. The accessibility suite renders real form,
  cookie, gallery and shell templates; existing result tests cover empty identifiers,
  missing titles, thumbnail names and disclosure content. PHP/JavaScript syntax
  checks and whitespace checks are required alongside the browser checks.

Rendered previews and baseline reports stay outside Git. Never add real patron
accounts, submissions, tokens, catalogue exports or production screenshots to tests.

## Changes made

| Area | Result |
| --- | --- |
| Shared shell | Native main landmark and focusable skip target; visible skip link; named navigation; valid named preference form; distinguishable pagination landmarks. |
| Focus and contrast | Dark outer/white inner focus rings; visible input borders; readable placeholders, alerts and notifications; wrapping controls and a reduced-motion preference. |
| Results | No empty identifier links; usable title fallback and thumbnail names; optional extra information uses native details instead of mouse-only hover panels. |
| Menus and filters | Native Browse/Filters buttons; expanded state on dropdowns; normal list semantics for result options; Escape closes disclosure lists; filter choices retain focus until explicitly closed and return focus to their launcher. |
| Dialogs | Named native close buttons across account/lightbox/comment/share forms and viewers; shared panel focus adapter also handles nested panels; surrounding siblings become inert while a modal is open. |
| Contact and account forms | Native email/required/autofill semantics; associated arithmetic instructions; textual field errors and invalid/described-by state; focusable contact error summary; correct reset-password confirmation label and new-password autocomplete. |
| Cookies | Named notice region; native details and grouped radios; existing preference names/values and required-category behavior retained. |
| Gallery and homepage | Native previous/next buttons and disabled endpoints; focus survives gallery AJAX replacement; selection/range status; hidden featured items leave the tab order; manual controls remain the alternative to swiping. |
| Lightboxes | Visible Move earlier/later controls for editable items; same native checked reorder endpoint and CSRF mechanism as dragging; pending state, saved/error feedback and failed-save rollback. Read-only sets receive no ordering controls. |
| Other native views | Named delete confirmation with keyboard controls; associated contribution agreement/math labels; PDF.js controls use DOM focus order and its password prompt has an accessible name; presentation mode permits browser zoom. |

`assets/pawtucket/css/accessibility.css` loads after existing styles. The native
access and ACL checks, submission names, authorization and CSRF validation remain
the application's responsibility. The theme does not bypass them.

## View inventory and boundaries

| View areas | Coverage and follow-up |
| --- | --- |
| pageFormat, Front, About, system | Shared shell, homepage, FAQ, notices and recovery pages; inspect editorial content and unusual long/translated headings. |
| Search, Browse, Collections, Listing | Public routes sampled; native form/result tests retained; apply the keyboard checklist to open Options, deferred facets, hierarchy and result-loading states after deployment. |
| Details, bundles | Public object/authority samples and existing permission/media tests; review every enabled representation type, annotations, document text and long metadata. |
| Gallery, Lightbox, LoginReg, Cookies | Public samples plus synthetic browser coverage; authenticated ownership/sharing/profile/delete and real session transitions still need controlled account tests. |
| mediaViewers, Compare | Wrapper/PDF.js source improvements; third-party image, PDF, audio/video, 3D, timeline and map components require their own enabled-state audit. |
| Contribute, Library, Ban | Static review and targeted label/notice fixes. These optional native workflows need a safe configured fixture/application before activation; primary public sampling does not certify them. |
| Form, ajaxFooter, lookup, JSON/manifest views | Inspect them in the complete parent interaction; fragments and transport responses do not independently establish page conformance. |
| Binary exports and mailTemplates | Separate document/email outputs. Their accessibility cannot be inferred from web-page scans. |

## Criterion checklist

Every A/AA success criterion is tracked below. “Theme” means there is relevant
source/test coverage, not a whole-site pass. “Review” requires human/content or
complete-process verification. “Conditional” requires checking the feature when
it is enabled. Use the [W3C quick reference](https://www.w3.org/WAI/WCAG22/quickref/)
for the normative exceptions and testing guidance.

| Criterion | Coverage / remaining acceptance |
| --- | --- |
| 1.1.1 | Theme: named links/icons and synthetic media; review catalogue images, scans, complex graphics and meaningful alternatives. |
| 1.2.1 | Review: transcripts/alternatives for audio-only and silent video items. |
| 1.2.2 | Review: accurate synchronized captions for prerecorded video. |
| 1.2.3 | Review: sufficient descriptions or equivalent alternatives for visual information in media. |
| 1.2.4 | Conditional: live-media captions, if any live material is offered. |
| 1.2.5 | Review: audio description for applicable prerecorded video. |
| 1.3.1 | Theme: main/regions, form labels, radio groups and disclosures; review editorial tables, native widgets and PDF structure. |
| 1.3.2 | Theme: DOM order retained; review every responsive/widget reading sequence. |
| 1.3.3 | Theme: text labels alongside controls; review content that relies on position, shape or sound. |
| 1.3.4 | Theme: flexible layouts; acceptance includes portrait and landscape on actual devices. |
| 1.3.5 | Theme: contact/account/password autocomplete; review configured profile and contribution fields. |
| 1.4.1 | Theme: underlined prose links, textual errors and selected-state cues; review authored content and charts. |
| 1.4.2 | Theme: active homepage has manual navigation; review every media player's audio startup/control behavior. |
| 1.4.3 | Theme: tested normal/error/notice states; review remaining dynamic, selected, disabled-exception and editorial states. |
| 1.4.4 | Theme: text-resize checks and presentation zoom; inspect native viewers and authenticated pages at enlarged sizes. |
| 1.4.5 | Review: images containing essential text, applicable exceptions and readable alternatives. |
| 1.4.10 | Theme: tested 320px page reflow; review long metadata and distinguish genuinely two-dimensional viewer content. |
| 1.4.11 | Theme: input boundaries, focus and controls; review custom viewer icons, maps and selection overlays. |
| 1.4.12 | Theme: spacing checks on synthetic pages; review imported rich text and configured native widgets. |
| 1.4.13 | Theme: explicit disclosures/filter closing; verify all third-party tooltips/popovers remain usable and dismissible. |
| 2.1.1 | Theme: keyboard controls, radios, galleries and reorder alternatives; review every enabled viewer/tool. |
| 2.1.2 | Theme: panel Tab/Escape/restoration tested; review nested viewer dialogs and third-party embeds. |
| 2.1.4 | Conditional: inspect native viewer single-character shortcuts and their disable/remap/focus behavior. |
| 2.2.1 | Review: real login/session, verification and contribution time limits and recovery. |
| 2.2.2 | Theme: primary featured items do not auto-advance; review optional slideshows, feeds and media with persistent movement. |
| 2.3.1 | Review: actual moving/flashing collection media; styles alone cannot evaluate the files. |
| 2.4.1 | Theme: first keyboard link focuses main; verify persistent navigation and embedded viewer transitions. |
| 2.4.2 | Theme: existing route/item titles retained; review configured page/editorial titles and standalone outputs. |
| 2.4.3 | Theme: modal/gallery restoration and native PDF control order; inspect complete authenticated and nested-widget sequences. |
| 2.4.4 | Theme: empty links removed and controls named; review actual labels and editorial link context. |
| 2.4.5 | Theme: search, browse, navigation and contextual links retained; check every intended public destination. |
| 2.4.6 | Theme: labels and structured component headings; review descriptive accuracy of actual metadata and content. |
| 2.4.7 | Theme: visible focus rings; verify all canvas/embedded controls and high-contrast modes. |
| 2.4.11 | Theme: skip/focus/reflow improvements; manually inspect focused controls under overlays and native sticky panels. |
| 2.5.1 | Theme: gallery buttons accompany swiping; review pan/zoom, annotation and other multipoint/path gestures. |
| 2.5.2 | Theme: native click activation retained; review viewer drawing/drag/pointer cancellation. |
| 2.5.3 | Theme: visible control wording is retained in names; inspect generated toolbars and voice-control behavior. |
| 2.5.4 | Conditional: check device-motion inputs if introduced; none added by this work. |
| 2.5.7 | Theme: explicit lightbox move controls; review image/annotation/map tools that still require dragging. |
| 2.5.8 | Theme: axe 2.2 checks and enlarged icon controls; inspect populated toolbars, dense filters and pointer spacing. |
| 3.1.1 | Theme: documents retain language metadata; verify installation locale and standalone viewer documents. |
| 3.1.2 | Review: language changes in multilingual editorial/catalogue content. |
| 3.2.1 | Theme: navigation is not triggered by focusing ordinary controls; check native lookup/viewer behavior. |
| 3.2.2 | Theme: explicit submissions and choice controls; review configured native onchange actions. |
| 3.2.3 | Theme: shared navigation preserved; check authenticated and alternate-layout pages. |
| 3.2.4 | Theme: consistent close, filter, result and gallery controls; review generated native toolbars. |
| 3.2.6 | Theme: shared contact/help links retained; verify every optional process presents its help consistently. |
| 3.3.1 | Theme: textual errors, invalid state and alerts; test every real server-side validation failure. |
| 3.3.2 | Theme: required/contact/password/group labels; review optional contribution/library forms and instructions. |
| 3.3.3 | Theme: contact corrections and native validation messages; review all actual account/server errors. |
| 3.3.4 | Theme: named delete confirmation and order-save rollback; verify authenticated data-edit/delete safeguards. |
| 3.3.7 | Review: complete registration, profile, contribution and recovery processes for unnecessary repeated entry. |
| 3.3.8 | Theme: password managers, email recovery and pasting remain available; review real verification and authentication challenges. |
| 4.1.2 | Theme: native controls, names and expanded/invalid/current states; review complete native widgets with assistive technology. |
| 4.1.3 | Theme: error/notice, gallery/range and reorder status; verify actual announcements and avoid excessively verbose updates. |

## Remaining acceptance work

1. Deploy this source revision through the established separately authorized
   workflow. Repeat scans against actual rendered pages and populated Options,
   filters, hierarchy, downloads, viewer dialogs and account states. This source
   audit did not deploy or change server/application configuration.
2. Use controlled test accounts for profile changes, password reset, lightbox
   editing/reordering/sharing/deleting and any active contribution/library flow.
   Verify success, failure, read-only access, timeouts and recovery without real
   patron data. Confirm a failed reorder restores the order and reports failure.
3. Test VoiceOver/Safari and an additional supported screen-reader/browser pairing,
   keyboard-only use, touch, voice input, 200%/400% browser zoom, text spacing,
   landscape and forced/high-contrast colors. Automated scans cannot certify this.
4. Audit media and files: captions, transcripts, descriptions, image alternatives,
   PDF reading order/tags/forms, and linked research documents. Existing OCR text
   and readable HTML do not by themselves make a PDF accessible. Confirm an
   equivalent accessible route for any output that cannot yet be remediated.
5. Audit enabled third-party image/PDF/audio/video/map/timeline/3D and comparison
   components, annotations, tooltips and CAPTCHA. Record the exact component,
   version, failed interaction and criterion before choosing theme work, a native
   upgrade/patch or an equivalent accessible alternative. No upstream tree was
   edited or dependency upgraded by this theme remediation.

Do not publish a whole-site AA claim until the remaining applicable content,
pages and complete processes have evidence. Keep findings open when a feature
cannot be exercised safely; absence from a sampled page is not a pass.

## Running the checks

The ordinary PHP loop includes the accessibility regression suite:

```sh
for test_file in tests/*_test.php; do php "$test_file" || exit 1; done
```

Browser tools are development-only and may live outside the checkout. With Node.js,
PHP/DOM/mbstring, Playwright 1.62.1, axe-core 4.14.0 and Chromium installed:

```sh
TADL_REFERENCE_ROOT=/path/to/pawtucket2 \
  node tests/accessibility_browser_test.js
TADL_REFERENCE_ROOT=/path/to/pawtucket2 \
  node tests/display_preferences_browser_test.js
```

Use `NODE_PATH` if the tools are installed in a separate directory, `TADL_AXE_PATH`
for a standalone axe script, `PHP_BINARY` for another PHP CLI, and
`TADL_BROWSER_CHANNEL=chrome` to test an installed Chrome. Optional
`TADL_A11Y_ARTIFACT_DIR` writes synthetic previews outside the checkout. The
reference tree supplies static assets only and is never bootstrapped or written.

GitHub Actions installs the pinned browser tools in a temporary directory and
uses only the required static assets from the pinned official 2.0.10 revision.
The test server binds loopback, uses synthetic data, and blocks external browser
requests. CI retains read-only repository permissions and no production access.
