# Community Notes Plugin Proposal

**Status:** Draft for archives team discussion

**Date:** November 5, 2026

**Scope:** Pawtucket public catalogue, Providence catalogue records, and the TADL theme

Community Notes would let invited contributors add knowledge about objects and
collections. Archives staff would review each submission before it becomes public.
Every published note would include the contributor's name and would be stored in
the associated Providence record, keeping the contribution with the catalogue data.

The proposed plugin would provide submission forms, a moderation queue, contributor
history, and public display. This proposal is for planning; implementation and
installation would follow agreement on the outstanding decisions below.

## Contributor experience

1. An invited contributor signs into Pawtucket with their own account.
2. An eligible object or collection page offers **Add a community note**.
3. The contributor enters their note and reviews the name that will be published
   with it. The form explains that the text and name will become public if approved.
4. Submitting the form places the note in the moderation queue and provides a
   confirmation. It does not immediately change the public record.
5. **My account → My contributions** lists the contributor's submissions, associated
   records, dates, current status, and feedback intended for them.
6. After approval, the note appears in a clearly labeled **Community notes** section
   on the relevant record page, with the contributor's name and publication date.

The initial scope is text contributions to objects and collections, including the
archival levels selected for participation. A note belongs to the specific record
on which it was submitted; collection notes would not automatically appear on all
descendant collections or objects.

Contributors could revise or withdraw an unpublished submission. A proposed change
to an already published note would require another review, with the existing
published version remaining visible until its replacement is approved.
My contributions would show both states, for example **Published; revision awaiting
review**. Declining or withdrawing that revision would preserve the published note.

## Moderation and contribution history

Authorized users would see **My account → Moderate contributions**. The queue would
support filtering by status, contributor, record type, and submission date. Each
review would show the note, proposed attribution, linked catalogue record,
submission history, and previous decisions.

| Status | Meaning |
| --- | --- |
| Submitted | Awaiting review. |
| Changes requested | Returned to the contributor with feedback. |
| Published | Approved and successfully saved to the catalogue record. |
| Not accepted | Reviewed and declined. |
| Withdrawn | The contributor withdrew an unpublished submission. |
| Unpublished | A moderator removed a previously published note from public display. |

Moderators could publish, request changes, decline, or unpublish a contribution.
Feedback visible to the contributor would be separate from private staff notes.
The history would record who made each decision and when, retaining the submitted
and published versions.

Approval and publication would be one operation. A failed catalogue save must leave
the submission awaiting review and explain the failure to the moderator. Repeated
requests or simultaneous approvals must not create duplicate published notes.

Contributors could view only their own private submissions and feedback. If an
associated record later becomes unavailable to them, their history could retain
their own text and status while withholding the record's current restricted title
and link. Public notes would always follow their parent record's visibility.

## Named attribution and catalogue storage

**Contributor names are a required part of published contributions.** Each note
would retain the name approved with that contribution. Changing or disabling an
account later would not silently rewrite or erase earlier attribution. Corrections
to a published name would follow an explicit staff workflow.

The account's full name is the proposed starting point. Whether to support a
separately approved publication name remains an archives policy decision. Email
addresses, login names, and internal account identifiers would stay private.

Providence would receive an optional, repeatable **Community notes** metadata
container, with one occurrence per published note. The proposed element code is
`community_notes`.

| Field in each occurrence | Purpose |
| --- | --- |
| Note text | The approved public text. |
| Contributor name | The contributor's approved public attribution. |
| Publication date | When the note was published. |
| Note identifier | A stable identifier linking this occurrence to its private moderation history. |

The container would apply to both objects and collections, with explicit coverage
of the intended record types and archival levels. Keeping these values together
would preserve the relationship between each note and its author in exports and
future catalogue displays.

Private plugin tables in the shared database would store account associations,
submissions and revisions, moderation status, decisions, feedback, and audit
history. Only approved public content would enter the catalogue metadata field.
Search, export, finding-aid, and OAI mappings would be reviewed separately to decide
where published notes and names should appear.

The native metadata would be the authoritative public version. Publishing,
updating, and unpublishing would use CollectiveAccess's metadata APIs to retain
normal catalogue history and indexing behavior. Unpublishing would remove only
the matching note occurrence and retain its private history. The plugin should
detect manual Providence edits or removals and flag discrepancies rather than
silently overwrite staff changes or recreate removed notes.

Disabling the plugin would leave published metadata and private history intact.
Any permanent deletion or anonymization would require a separate retention policy.

## Accounts and permissions

The plugin would use existing CollectiveAccess authentication and roles. Every
contributor would have an individual account. Contributor accounts would normally
be public-access-only accounts, allowing Pawtucket sign-in without Providence
backend access.

| Role | Proposed plugin permission | Native catalogue rights |
| --- | --- | --- |
| Community contributor | `can_submit_community_notes` | Read eligible records; no catalogue editing. |
| Community moderator | `can_moderate_community_notes` | Edit Community notes on the records they are authorized to manage. Submission permission can also be assigned. |
| Administrator | Native administrator privileges for registered plugin actions. | Existing administrative rights. |

Authorization would check permissions rather than depend on particular role names.
Example installation role codes are `community_contrib` and `community_moderator`.

Moderation permission would supplement native publishing rights. A moderator must
also be allowed to edit the target record and its Community notes field, including
applicable record, type, source, and collection restrictions. A dedicated limited
role could grant the necessary object/collection editing actions and Community
notes editing while leaving other fields read-only or inaccessible. Assigned
roles and groups must be checked together because a limited role cannot revoke
broader access granted elsewhere.

The plugin would check enabled state, authentication, permissions, ownership, and
record visibility on every applicable request, including direct URLs. All changes
would require POST requests and valid native protection against forged requests.
Private history and moderation pages would prohibit shared caching. Text input
would have configured length limits and safe output handling.

## Theme compatibility and plugin structure

The main plugin would live in Pawtucket's application plugin directory and supply
its own controllers, default views, configuration, helpers, and scoped styling.
A lightweight Providence companion would register the same permission codes for
the native role editor and support installation checks. The proposed architecture
uses the shared database and needs no separate worker or service account.

The workflow could be reused across themes, but inline placement requires small
theme integrations. Pawtucket does not provide a universal insertion point for
every theme's account menu or detail layout. Each participating theme would add
documented helper calls for account links and the object/collection notes panel,
with optional view overrides for presentation.

For TADL, integration would extend the existing account menu and detail templates.
Existing-account login is already available, while public login links and
self-registration are currently hidden or disabled. An invitation-based pilot
could retain that arrangement and supply contributors with the login URL.

Community Notes would use its own routes and private queue. Native comments have
basic moderation but do not provide the proposed lifecycle and publication into a
record field. The TADL theme's existing restrictions on native comments, tags,
ratings, and sample content submission would remain in place.

## Decisions for the archives team

The following choices would refine the proposal before development:

| Decision | Proposed starting point |
| --- | --- |
| Publication name | Use the account's full name, shown and confirmed before submission; decide whether another approved publication name is allowed. |
| Editorial changes | Return substantive changes to the contributor; decide whether moderators may make minor corrections directly. |
| Sources and evidence | Decide whether a separate citation/source field is needed alongside the note. |
| Eligible records | Select participating object types and collection levels, and any pilot collections. |
| Notifications | Decide whether contributors receive status emails and moderators receive queue notifications. |
| Login visibility | Begin with invited accounts and supplied login instructions; decide whether to add a public Contributor sign in link. |
| Moderation responsibility | Identify the staff responsible for review, publication, and requests to remove notes. |
| Retention and removal | Define how long private submissions are retained and how corrections, removal requests, and attribution changes are handled. |
| Public reuse | Agree on submission terms and where published notes and names may appear in search, exports, finding aids, and harvested metadata. |

## Installation and rollout

These are the required setup activities for the proposed implementation. Exact
commands and configuration keys would be supplied with the finished plugin.

1. **Verify compatibility and prepare a test installation.** Confirm the deployed
   Pawtucket, Providence, PHP, database, and authentication versions, and that both
   applications use the intended shared catalogue and accounts. The current design
   is based on the local 2.0.10 reference source; compatibility with the deployed
   versions must be verified. Take private backups before database or
   configuration changes.

2. **Install both plugin components with the feature disabled.** Install the main
   component under Pawtucket's `app/plugins/CommunityNotes/` and the companion in
   Providence's application plugin directory. Keep source read-only to web PHP and
   preserve installation-local configuration during upgrades. No core patch is
   planned.

3. **Create the private workflow tables.** Use an explicit, versioned setup tool
   with inspection and apply modes for submissions, revisions, and moderation
   history. It must detect existing setup and avoid changing the native
   CollectiveAccess schema version. Schema creation must be an administrator
   operation, not a side effect of an ordinary page request.

4. **Create the Community notes metadata.** Through **Manage → Administration →
   Metadata elements**, or a reviewed additive setup tool, create the container and
   its children. Set minimum occurrences to zero, allow multiple occurrences, and
   configure the container to permit multiple notes in the same locale. Bind it to
   objects and collections with the intended type/subtype coverage. Validate that
   every eligible type accepts the field. Do not rerun the catalogue's installation
   profile to add this feature.

5. **Add the field to Providence editing screens.** Add Community notes to the
   appropriate object and collection interfaces. Configure public reading of
   approved values under the existing access policy and restrict field editing to
   authorized staff.

6. **Register plugin permissions in both applications.** Refresh each application's
   plugin action list using targeted cache invalidation so the new actions are
   recognized immediately and appear in Providence's role editor.

7. **Create and verify roles.** In **Manage → Access control → Access roles**, create
   the contributor and moderator roles, assign their plugin actions, and configure
   moderators' native record and field publishing permissions. Confirm effective
   rights across all assigned roles and groups. Setup must report missing rights
   rather than automatically grant broad privileges.

8. **Provision contributor and moderator accounts.** In **Manage → Access control →
   User logins**, create or update individual accounts, confirm names, activate the
   accounts, and assign roles. Use public-access-only accounts for contributors.
   Verify sign-in and password-reset delivery, then provide invitation instructions.
   Keep self-registration disabled for the proposed pilot.

9. **Configure the plugin.** Configure metadata mappings, eligible records and
   types, text limits, publication-name handling, moderation options, and any email
   notifications. Verify the existing mail transport if notifications are enabled.
   Keep private settings outside source control.

10. **Integrate the TADL theme.** Add My contributions and permission-gated Moderate
    contributions links to the account menu, add notes panels and submission links
    to object and collection templates, and load the plugin's scoped assets.
    Document equivalent integration calls for other themes. Maintain current
    record-access filtering and prevent private controls from entering shared
    page caches.

11. **Review publication mappings and backup coverage.** Apply the agreed search,
    export, finding-aid, and OAI mappings for approved notes and names. Ensure that
    pending content and private workflow data cannot enter public output. Include
    plugin tables and local configuration in the normal private backup process.

12. **Validate and launch a limited pilot.** Complete the acceptance checks below
    on a test installation, then enable an agreed set of records and invited
    contributors. Verify the deployed workflow with contributor, moderator, and
    anonymous sessions before expanding participation. Document upgrades and
    rollback, including how to disable the feature while retaining its data.

## Delivery and acceptance

Implementation would start by proving that a limited moderator can publish only
to the intended field and records, and that metadata publication, queue status,
and audit history can be saved together with correct indexing behavior. This
compatibility check would precede the full interface work.

The next stages would build the private submission history and publication logic,
the contributor and moderation pages, and the reusable display helpers and TADL
integration. Installation tooling and operator documentation would accompany the
implementation.

Acceptance would cover:

- Contributor submission, revision, withdrawal, feedback, and status history.
- Named public attribution saved with the note, including behavior after account
  name changes or deactivation.
- Publication, requested changes, rejection, replacement, and unpublishing on
  both objects and collections.
- Ownership boundaries, restricted records and hierarchies, revoked permissions,
  forged requests, direct URLs, safe text handling, and private-page caching.
- Failed saves, incomplete metadata setup, repeat limits, duplicate requests, and
  simultaneous moderation without false status or duplicate notes.
- Native history, indexing, exports, and detection of manual Providence edits.
- Mobile layout, keyboard access, visible feedback, and theme customization.
- Disabling and upgrading the plugin without losing published metadata or private
  moderation history.

## Related documentation

- [Existing TADL accounts and user features](USER_FEATURES.md)
- [Theme installation and feature guides](../README.md)
- [CollectiveAccess application plugins](https://oldmanual.collectiveaccess.org/providence/developer/app_plugins.html)
- [CollectiveAccess access control](https://oldmanual.collectiveaccess.org/providence/user/administration/control.html)
