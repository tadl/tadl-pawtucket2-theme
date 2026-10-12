# Families

**Browse > Families** uses the catalogue's `fam` entity type and native subtypes.
It defaults to Identifier ascending and List, with Name and Tiles available.
Family details identify the record as **Family** and link to **Browse families**.
Unknown entity types omit a Browse destination rather than linking to the invalid
`Browse/Index` action.

## Combined objects

Family details include objects linked directly to the family plus objects linked
to directly related people (`ind`) and organizations (`org`), including subtypes.
Both relationship directions and all direct relationship types count. An object
attached to multiple members appears once. This does not infer membership from
a name or change catalogue relationships.

Traversal stops after these direct people/organization relationships. It does
not follow another family or a member's other relationships. Private, deleted or
unreadable roots/members, denied relationship bundles, native type/source access,
and Pawtucket ACLs prune the scope. Native search enforces object access and sorting.

`helpers/family_contents.php` batches roots and relationships rather than loading
one model or running one object search per family. `FamilyContentsController.php`
rebuilds the permitted scope on every request and delegates to the native object
SearchController. Paging, sort/view controls and exports retain the family ID;
these fixed-scope results omit ordinary search facet controls.
Empty scopes produce an explicit zero-hit search. Results use a readable family
heading and private/no-store/noindex headers.

## Media preference

**All items** includes empty families. **Only items with media** includes a family
when its combined scope contains an accessible object with usable public media.
Existing object/representation eligibility rules apply. Filtering precedes counts,
paging and detail Prev/Next navigation. Media attached to the family itself does
not qualify it. Tiles use native family thumbnails; List avoids requiring an image
on every family record.

## Verification and rollout

Run `php tests/family_contents_test.php`, `php tests/media_preferences_test.php`,
`php tests/authority_detail_test.php`, `php tests/browse_navigation_test.php`,
`php tests/result_context_heading_test.php` and `php tests/crawler_policy_test.php`.
These use synthetic records and real SQLite scope SQL; native object search
remains an integration boundary. The browse suite optionally checks full
configuration with the installed native parser, as documented in the handoff.

After an authorized deployment, follow a person-to-family relationship and verify
the family heading and Browse link, a family with only member-linked objects,
duplicate/shared objects, two results pages, and both media preference modes.
Verify an inaccessible member cannot qualify a family or add its objects.
The updated `support/site-root/robots.txt` needs its separate root installation;
normal theme deployment does not install it. No production deployment or catalogue
changes were performed for this source implementation.
