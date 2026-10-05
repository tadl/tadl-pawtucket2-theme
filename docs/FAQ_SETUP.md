# Activating the FAQ editor

FAQ content uses native Providence Site Pages in the database shared with
Pawtucket. Each entry uses template code `faq_entry` with category, question and
rich-text answer fields. The home-page accordion reads published entries from
that template; no questions are stored in the theme.

## Repeatable setup

Keep these files in the maintained TADL theme:

- `templates/faq_entry.tmpl`: trusted display template.
- `conf/templates.conf`: native field labels and rich-text editor settings.
- `support/activate-faq.php` and `helpers/faq_setup.php`: explicit setup operation.

Deploy or stage these files together. Run the script as the application's normal
maintenance user (usually `www-data`), using the real Providence root and installed
Pawtucket theme directory. Do not run PHP maintenance as root just because SSH
uses root: application caches should retain their normal ownership.

Inspect first; this is the default and does not write database configuration:

```sh
php /path/to/tadl/support/activate-faq.php \
  --providence-root=/path/to/providence \
  --theme-root=/path/to/pawtucket/themes/tadl
```

Apply the reviewed plan with a fresh backup filename in a private writable
directory outside both application web roots:

```sh
php /path/to/tadl/support/activate-faq.php \
  --providence-root=/path/to/providence \
  --theme-root=/path/to/pawtucket/themes/tadl \
  --apply --backup-file=/private/backups/faq-before.json
```

The script requires a Providence bootstrap and the native system UI with editor
code `site_page_editor_ui`, table `ca_site_pages`, and exactly one default screen.
That UI normally comes from the Providence installation profile. It stops if the
UI is absent/ambiguous or an existing FAQ template was deliberately deleted;
resolve those conditions explicitly rather than replacing editors or permissions.

The operation:

1. Compiles the three FAQ tags with the native `View` parser and theme configuration.
2. Creates only `faq_entry`, or updates its template/tags if the source changed.
   Existing custom template titles are preserved. Other templates are untouched.
3. Adds missing title, URL path, description, Page content, access, sort order and
   locale placements to the existing default Site Page editor screen. Existing
   placements, settings and their order are preserved; additions go at the end.
4. Writes a mode-0600 JSON snapshot of the previous FAQ template, screen and
   placements before applying changes. Existing backup files are never overwritten.
5. Uses the native ORM in one database transaction; failure rolls back template
   and placement writes. Apply refuses to run unless these tables use InnoDB.

A repeat run with unchanged configuration reports `unchanged`, no added bundles,
and `applied: false`; it performs no database writes and needs no new backup.
The script never creates FAQ pages, publishes content, modifies roles/accounts,
scans other templates, edits Providence core, clears caches or restarts services.
Keep backups private and preserve them outside temporary staging directories.

## Editing questions

In Providence, reload **Manage → Content Management → Site pages**. The upper
right control should offer **New FAQ entry page** with a plus icon. Select
**FAQ entry** and click plus to open the editor. Fill in page metadata and the
three Page content fields. Use the same category spelling to group questions.
Set sort order (e.g. 10, 20, 30) and set **Access** to **Accessible to public**
when ready to publish. Unspecified locale works for the site's default language.

No published, complete entries means no FAQ section appears on the homepage.
See `USER_FEATURES.md` for rendering rules and staff permissions.

## Upgrades and recovery

Normal Providence upgrades preserve the shared database, including template
records, FAQ content and editor placements. The custom template and activation
script live in the separately maintained theme, so no Providence core patch must
be reapplied. Preserve the theme and database backups during upgrades. Re-run
inspection afterward; apply only if required configuration is missing or changed.
Avoid scanning an unrelated Providence theme over the shared templates.

For rollback, inspect the private JSON backup and the current records first:

- Remove only placements listed in `plan.add_bundles` with matching
  `tadl_faq_<bundle>` placement codes, using Providence's User Interface editor.
- For an updated FAQ template, restore the prior template/tags through the native
  ORM using `template_before`. Do not overwrite other templates.
- For a newly inserted template, only mark it deleted if no Site Pages reference
  it. Once staff create content, preserve the template/content and set individual
  pages to nonpublic if they must be withdrawn.

Do not replay a broad database restore over subsequent catalog changes.

## Verified production activation

On 2026-10-05 the authorized setup registered **FAQ entry** in the shared production
database and added only **Sort order** and **Locale** to the existing editor.
The installed template/configuration already matched the maintained theme.
Native template selection and generation of all three editor fields were verified
through Providence APIs. The affected tables use InnoDB. A second apply run made
no changes. No Site Pages were created or published; no service restart or cache
purge was needed. Authenticated browser editing/saving remains a staff check.

The private operational backup location is recorded in the workspace handoff,
outside this repository. Synthetic regression coverage is in
`tests/faq_setup_test.php`; it does not bootstrap the application or use its DB.
