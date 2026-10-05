# Accounts, lightboxes and home-page FAQ

## Current account pilot

The theme uses Pawtucket's existing `LoginReg` and `Lightbox` controllers, with
a focused `AccountProfile` controller for profile saves. There is no separate
authentication system or custom account database.

Bookmark `/LoginReg/LoginForm` on the archive site. Anonymous visitors see no
login, registration, account menu or Add to lightbox links. Existing accounts
can log in. Self-registration is disabled with `dontAllowRegistration = 1`;
the public catalog does not require authentication.

After login, **My account** offers **My lightboxes**, **My profile** and **Log out**.
Its positioning overrides the inherited Yamm mega-menu rule so the green
hover/focus/open marker and dropdown stay attached to the account item.
**Add to lightbox** appears below object media and on full object result cards/list
rows, including related-item and collection contents that use those result
views. The native modal lets the user choose or create a lightbox. The action
adds the object, rather than a particular representation. Native authentication,
CSRF, set ownership and sharing checks remain in charge. No credentials or users
were created by this source change.

The native login form retains password reset and now supports password managers.
Profile forms support contact details and password changes. Email delivery,
password reset and the corrected profile-save endpoint need live checks after
deployment. Pawtucket and Providence use the configured CollectiveAccess
users/authentication adapter;
the applications have separate login sessions. Prefer public-access accounts
for researchers and deliberate staff roles for editors. A Pawtucket login is
not permission to edit FAQ content or the collection catalog.

### Profile-save validation

Keep profile GET links at `/LoginReg/profileForm`. Both the full-page and modal
forms POST to `/AccountProfile/profileSave`; deploy the controller and form
together. This controller inherits native form rendering and authentication
setup, requires POST, a logged-in session and a valid native CSRF token, and
loads only the authenticated user ID. Submitted account IDs or privilege fields
are ignored; only configured `profile` preferences are editable.

Native `LoginReg::profileSave()` compares the submitted email with `user_name`,
which need not equal a staff account's contact email. The corrected save checks
login-name collisions only when a public user's email changes, excluding that
same user ID. Unchanged email preserves the current login name for every account;
staff contact-email changes also preserve the staff login name.

Validate fields, password confirmation, public-use group invitations and profile
preferences before applying changes. Proposed edits use a separate `ca_users`
model, never the request's session user: native `RequestHTTP::close()` calls
`ca_users::close()`, which saves that user even after controller validation fails.
Reload the request user after a successful save so its stale preferences cannot
overwrite the new values. Failed validation/setters leave profile data unchanged.
The form displays stored values again after an error; passwords are never echoed.

Native model update still enforces password policy, complexity and authentication
adapter support. This is not a transaction across an external authentication
service. Group membership is attempted after a successful profile save; if that
write fails, the form explicitly says the profile saved but the group was not
joined. Invalid group codes block the profile save during validation.

`tests/profile_save_test.php` exercises the actual theme controller and both forms
with synthetic ORM/session/CSRF boundaries, including an end-of-request user save.
It does not connect to a real database or edit a real account. No core patch is
required. Existing bookmarked or stale forms posting to native
`/LoginReg/profileSave` still use upstream behavior; reload the profile form after
deploying this change.

## Native feature inventory

Inspected against the nearby Pawtucket 2.0.10 source, not just old screenshots.

| Feature | Current theme behavior / requirement |
| --- | --- |
| Named lightboxes / saved object lists | Native creation, naming, descriptions, additions and removal |
| Item ordering | Native manual reordering and configured identifier/title sorting |
| Notes and discussion | Native lightbox/item comments; existing native permissions and moderation settings apply |
| Sharing | Native sharing with users and user groups, including read/edit access and group invitations |
| Presentation | Native lightbox slideshow/presentation views |
| Exports | Native configured export formats; available formats depend on installation/export setup |
| Map and timeline | Existing lightbox views; useful only with corresponding location/date data and supporting services |
| Profile and password reset | Native forms retained with corrected theme profile saves; reset requires working email configuration |
| Object comments/tags, content submission | Optional native capabilities; not enabled by this task |
| Researcher media preference | Still the existing browser cookie, not an account permission |

Preserve `lightbox_default_access = 1`, the upstream-compatible access status.
The native list filters sets through the catalog access mask (normally `[1]`),
so changing it to `0` can make newly created lightboxes disappear even for their
owner. Ownership and explicit user/group sharing still gate the Lightbox
controller. Do not describe this access flag as a complete privacy policy;
review set publication/export/presentation routes before offering private
research storage. Do not broaden catalog access statuses just to show lightboxes.
Existing sets and permissions are not changed by this theme.

Bulk selection / Add all results remain omitted. Individual additions work with
the theme's filtered result pages; native bulk-add would need to respect that
same media filter before restoration. Result HTML cache keys distinguish sessions
with and without lightbox controls. Keep whole-page caching disabled because
navigation and other page content depend on session/preference state.

## FAQ content editing

The homepage has a responsive, two-column accordion scaffold; phones use one
column. Native HTML `details` / `summary` works with keyboard and without
JavaScript. The section is completely hidden until at least one complete,
published FAQ entry exists. No archive policies or placeholder answers are
published by this task.

Use Providence's existing **Site Pages** editor rather than a new web admin panel.
Each question is one Site Page using the `faq_entry` template:

| Field | Purpose |
| --- | --- |
| Page title / description | Identify the entry in the staff editor |
| URL path | Give each entry a unique path such as `/faq/viewing-the-collection` |
| FAQ category | Group heading, e.g. `Accessing` or `Use, Credits, Citations` |
| FAQ question | Plain-text accordion label |
| FAQ answer | Rich-text answer; paragraphs and safe links are supported |
| Sort order | Lower numbers first; leave gaps (10, 20, 30) for easy insertions |
| Access | Set to **Accessible to public** to publish; draft entries stay hidden |
| Locale | Unspecified or the active site locale; other locales are excluded |

Categories follow their first entry's sort order. Questions within each category
follow sort order, then page ID for stable ties. Use the same spelling for entries
in the same category. Blank questions and answers are omitted, even when public.
All entries must pass the native page/content permission checks. Questions and
category names are escaped as text; answers pass through the native HTML purifier.
Edits appear on the next home-page request; there is no additional FAQ cache.

### One-time activation after theme deployment

Use the focused, repeatable setup in [FAQ activation](FAQ_SETUP.md). It defaults
to inspection, takes a private backup before applying, registers only the FAQ
template and adds missing editor fields without replacing existing configuration.
Authorized production activation was completed on 2026-10-05. Reload Providence
**Manage > Content Management > Site pages**, select **FAQ entry** in the upper
right New page control, and click its plus icon. No FAQ entries were created.

The broad native alternative remains available for administrators who intend to
register/update every template, rather than only FAQs:

1. Confirm the installed Pawtucket theme is `tadl` and uses the intended shared
   database. As the application's normal maintenance user, from the Pawtucket
   installation root, run:

   ```sh
   php support/bin/caUtils scan-site-page-templates
   ```

   This **writes template metadata to the database**, including updates to other
   scanned templates. The FAQ activation uses the targeted script instead. Review existing
   template state and the command output before continuing.
2. In Providence, open **Manage > Content Management > Site pages** and create an entry
   using `faq_entry`. If the editor is missing, configure the native Site Pages
   editing UI with page metadata and Page content bundles; grant only the
   necessary Site Pages permissions to the staff role. Existing public/researcher
   accounts need no such editing rights.
3. Enter the category, question and answer, assign sort order and public access,
   save, and reload the homepage. Draft an entry again to remove it from the FAQ.

The native workflow is documented in
[Pawtucket templates and content management](https://pawtucket2.readthedocs.io/en/latest/pawtucket/templates.html).
Theme source lives in `templates/faq_entry.tmpl`, `conf/templates.conf`,
`helpers/home_faq.php`, `views/Front/faq_html.php` and the scoped FAQ CSS.
No theme deployment is needed for routine question/answer edits after activation.

## Verification and remaining live checks

Run all standalone tests per `AGENTS.md`. `tests/user_features_test.php` renders
actual login/result views with synthetic native session/URL/CSRF boundaries.
`tests/home_faq_test.php` renders the actual FAQ loader/view with synthetic ORM
records, including drafts, restrictions, locale, ordering and escaping. To also
exercise a real installed HTMLPurifier without bootstrapping the database:

```sh
TADL_TEST_COMPOSER_AUTOLOAD=/path/to/pawtucket/vendor/autoload.php php tests/home_faq_test.php
```

Synthetic browser previews verify accordion interaction, responsive layout and
account navigation (keyboard, Escape, outside-click dismissal and expanded state).
The small `user-features.js` asset synchronizes that state for Bootstrap 3.0. They are not proof of a successful login, lightbox save,
sharing, password email or Providence editing session. Those require a configured
safe application/database or separately authorized live pilot. The later,
authorized FAQ activation changed production template/editor configuration;
details and verification limits are in `FAQ_SETUP.md`. No FAQ content was published.
