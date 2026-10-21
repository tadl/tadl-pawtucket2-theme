# Production verification

Run source checks and deploy through the operator workflow in [Deployment](DEPLOYMENT.md).
No cache clearing or Apache reload is part of routine deployment. This checklist is
an acceptance test, not permission to modify real accounts or catalogue records.
Use designated disposable accounts/sets and preserve their initial settings.

## Read-only checks after deployment

- Confirm normal Reload picks up changed assets and PHP, after the OPcache
  timestamp revalidation interval; inspect the rendered asset content versions.
- Check home, About, Gallery, Collections, object/authority details and both
  collection views at phone/tablet/desktop widths, including keyboard navigation.
- Check an invalid route returns a safe 404, and unexpected errors use a safe 500;
  no response may reveal stack traces, paths, request parameters or credentials.
- Compare the origin root robots file with `support/site-root/robots.txt`, including
  CollectionContents, FamilyContents, PlaceContents and DocumentText. [Crawler policy](CRAWLER_POLICY.md) explains
  the separate installation; theme deployment alone does not replace the root file.
- Check flat/hierarchy counts and paging with both media preferences. Inspect
  network/console errors and loading/error states; avoid public load testing.

## Controlled account and export checks

The operator must identify disposable accounts and approve their limited writes.
Do not use an arbitrary staff/patron account or inspect another person's lightbox.

1. Sign in with designated staff and public-access accounts. Anonymous visitors
   must have no account tools; only staff get the Providence shortcut. Log out
   and confirm the prior account controls are absent.
2. Change and restore one synthetic profile preference. An unchanged email must
   not generate the former duplicate-email error. Invalid forms must not save
   partial preferences. Verify password-reset delivery to the designated test
   mailbox; never publish tokens or reset a real user's password for testing.
3. Confirm object details and account menus contain no Lightbox controls for
   anonymous or signed-in users. Direct `/Lightbox/Index` and
   `/Lightbox/addItemForm` requests must return 404, including stale modal requests.
   Published featured galleries must still render. Do not create or modify sets;
   item sets are deferred until a future public researcher-account feature.
4. Use small approved TIFF/BMP/JPEG/PDF records to check view/download menus,
   full image dimensions, PDF completeness and repeat exports. Generated files and
   download logs are production writes, so this is separate from read-only checks.
   Verify a private representation is denied even after its former export is cached.
5. Download an approved finding aid and check metadata, complete hierarchy, extent and the **Read as text** version, with no per-object inventory. Preserve original media/access settings.
6. If an export fails, find `tadl_export_failed` in the server PHP error log. Expect
   safe IDs/format/code location, never passwords, request data or exception messages.

Keep private pilot notes outside the source repository. Record pass/fail and runtime
versions without account identifiers, cookies, emails, exported files or catalogue data
in public documentation. Standalone tests do not prove email delivery or live sharing.
