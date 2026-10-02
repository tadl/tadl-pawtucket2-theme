<?php
/**
 * Run with php tests/authority_detail_test.php (PHP and Node.js required).
 * Actual authority helpers/templates, synthetic native API boundaries, no app or DB.
 * The real AJAX result count is covered by result_context_heading_test.php.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
$GLOBALS['authorityAssertions'] = 0;
$GLOBALS['authorityBundleAccess'] = [];
$GLOBALS['authorityMediaCalls'] = [];
$GLOBALS['authorityPawAclTables'] = [];
$GLOBALS['authorityAclDeniedIds'] = [];
$GLOBALS['authorityAclCalls'] = [];
$GLOBALS['authorityEligibleCollections'] = [50, 51];
$GLOBALS['authorityUrlOverride'] = null;
$GLOBALS['authorityIcon'] = '<span class="synthetic-spinner" title="collector\'s &amp; loading"></span>';
$GLOBALS['authorityLoadingText'] = "Loading related collector's \"quoted\" items \\ & < > \u{2028}\u{2029}";

function checkAuthority($condition, $message) {
	$GLOBALS['authorityAssertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text, ...$values) {
	if ($text === 'Loading related items...') { return $GLOBALS['authorityLoadingText']; }
	foreach ($values as $index => $value) { $text = str_replace('%'.($index + 1), (string)$value, $text); }
	return $text;
}
function caGetUserAccessValues($request) { return $request->access; }
function caGetBundleAccessLevel($table, $bundle) { return $GLOBALS['authorityBundleAccess'][$table.':'.$bundle] ?? 1; }
function caACLIsEnabled($subject, $options = []) {
	checkAuthority(($options['forPawtucket'] ?? false) === true, 'Relationship ACL lookup must include front-end-only ACL settings.');
	return in_array(is_string($subject) ? $subject : $subject->tableName(), $GLOBALS['authorityPawAclTables'], true);
}
function caGetBrowseInstance($table) { return new AuthorityBrowse($table); }
function tadlMediaPreference($request) { return $request->mode; }
function tadlMediaEligibleIDs($table, $ids, $access) {
	checkAuthority($table === 'ca_collections', 'Authority groups should apply media eligibility only to collections.');
	checkAuthority($access === [1], 'Collection eligibility omitted current record access values.');
	$GLOBALS['authorityMediaCalls'][] = [$table, $ids, $access];
	return array_values(array_intersect($ids, $GLOBALS['authorityEligibleCollections']));
}
function caNavUrl($request, $module, $controller, $action, $params = [], $options = []) {
	if ($controller === 'Search' && $GLOBALS['authorityUrlOverride'] !== null) { return $GLOBALS['authorityUrlOverride']; }
	return '/synthetic/'.$controller.'/'.$action.($params ? '?'.http_build_query($params) : '');
}
function caNavLink($request, $text, $class, $module, $controller, $action, $params = []) {
	return '<a class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'" href="'.htmlspecialchars(caNavUrl($request, $module, $controller, $action, $params), ENT_QUOTES, 'UTF-8').'">'.$text.'</a>';
}
function caDetailLink($request, $text, $class, $table, $id, $options = []) {
	return '<a href="/synthetic/Detail/'.$table.'/'.(int)$id.'">'.$text.'</a>';
}
function caBusyIndicatorIcon($request) { return $GLOBALS['authorityIcon']; }

class AuthorityRequest {
	public array $access = [1];
	function __construct(public string $mode = 'only') {}
	function getUserID() { return 7; }
}
class Datamodel {
	static function getInstance($table, $initialize = true) { return new AuthorityTarget($table); }
}
class AuthorityTarget {
	function __construct(private string $table) {}
	function tableName() { return $this->table; }
	function tableNum() { return ['ca_collections' => 20, 'ca_entities' => 22, 'ca_places' => 72, 'ca_occurrences' => 67][$this->table]; }
}
class AuthorityBrowse {
	function __construct(private string $table) {}
	function filterHitsByACL($ids, $tableNum, $userID) {
		checkAuthority($tableNum === (new AuthorityTarget($this->table))->tableNum() && $userID === 7, 'Relationship ACL filter must use target table and current user.');
		$GLOBALS['authorityAclCalls'][] = [$this->table, $ids, $tableNum, $userID];
		return array_values(array_diff($ids, $GLOBALS['authorityAclDeniedIds'][$this->table] ?? []));
	}
}
class AuthorityItem {
	public array $relationReads = [];
	public array $templateReads = [];
	public array $intrinsicReads = [];
	public array $relationships = [];
	public array $fields = [];
	public array $occupations = [];
	public array $types = [
		10 => ['idno' => 'ind', 'parent_id' => 1],
		11 => ['idno' => 'synthetic_artist', 'parent_id' => 10],
		12 => ['idno' => 'synthetic_photographer', 'parent_id' => 11],
		20 => ['idno' => 'org', 'parent_id' => 1],
		21 => ['idno' => 'synthetic_studio', 'parent_id' => 20],
		30 => ['idno' => 'synthetic_other', 'parent_id' => 1],
		40 => ['idno' => 'synthetic_cycle_a', 'parent_id' => 41],
		41 => ['idno' => 'synthetic_cycle_b', 'parent_id' => 40]
	];
	function __construct(public string $table = 'ca_entities', public int $typeID = 10, public string $typeName = 'Individual', public int $objectCount = 0) {}
	function get($name, $options = []) {
		$this->intrinsicReads[] = $name;
		if ($name === 'type_id') { return $this->typeID; }
		if ($name === 'ca_entities.occupation') {
			checkAuthority(($options['checkAccess'] ?? null) === [1] && ($options['convertCodesToDisplayText'] ?? false) === true, 'Occupation reads must retain access checks and display labels.');
			checkAuthority(($options['returnWithStructure'] ?? false) === true && ($options['dontReturnDefault'] ?? false) === true, 'Occupation dates must stay paired with their names without default values.');
			return [42 => $this->occupations];
		}
		if (in_array($name, ['entity_id', 'place_id', 'occurrence_id'], true)) { return 42; }
		if ($name === 'ca_objects.object_id') { return $this->objectCount ? range(101, 100 + $this->objectCount) : []; }
		throw new RuntimeException('Unexpected intrinsic read: '.$name);
	}
	function getTypeList() { return $this->types; }
	function getTypeName() { return $this->typeName; }
	function getWithTemplate($template, $options = []) {
		checkAuthority(($options['checkAccess'] ?? null) === [1], 'Detail field/header reads must use current record access values.');
		checkAuthority(($options['convertCodesToDisplayText'] ?? false) === true, 'Detail field/header reads must use native display labels.');
		$this->templateReads[] = $template;
		return $this->fields[$template] ?? '';
	}
	function getRelatedItems($table, $options = []) {
		checkAuthority(($options['checkAccess'] ?? null) === [1], 'Relationship query omitted native record access values.');
		$this->relationReads[] = [$table, $options];
		// Native getRelatedItems applies record access, deletion and ACL checks.
		return array_filter($this->relationships[$table] ?? [], function ($row) use ($options) {
			return in_array($row['access'] ?? 1, $options['checkAccess'], true) && !($row['deleted'] ?? false) && ($row['nativeAclAllowed'] ?? $row['aclAllowed'] ?? true);
		});
	}
}
class AuthorityView {
	function __construct(public AuthorityRequest $request, private AuthorityItem $item, private array $extra = []) {}
	function getVar($name) { return $name === 'item' ? $this->item : ($this->extra[$name] ?? null); }
	function render() {
		ob_start();
		try {
			include dirname(__DIR__).'/views/Details/'.$this->item->table.'_default_html.php';
			return ob_get_clean();
		} catch (Throwable $error) {
			ob_end_clean();
			throw $error;
		}
	}
}
require dirname(__DIR__).'/views/Details/authority_detail_helpers.php';

foreach ([
	[10, 'Individual', 'Person', 'people'],
	[11, 'Synthetic artist', 'Person', 'people'],
	[12, 'Synthetic photographer', 'Person', 'people'],
	[20, 'Organization', 'Organization', 'organizations'],
	[21, 'Synthetic studio', 'Organization', 'organizations'],
	[30, 'Synthetic family', 'Synthetic family', 'Index'],
	[99, 'Synthetic missing type', 'Synthetic missing type', 'Index'],
	[40, 'Synthetic cyclic type', 'Synthetic cyclic type', 'Index'],
	[0, '', 'Person or organization', 'Index']
] as [$typeID, $typeName, $kind, $browse]) {
	$info = tadlAuthorityEntityKind(new AuthorityItem('ca_entities', $typeID, $typeName));
	checkAuthority($info['kind'] === $kind && $info['browse'] === $browse, 'Entity classification failed for type '.$typeID.'.');
}

$item = new AuthorityItem();
$item->relationships = [
	'ca_collections' => [
		['collection_id' => 50, 'label' => 'Synthetic accessible media collection', 'relationship_typename' => 'Part of'],
		['collection_id' => 52, 'label' => 'Synthetic collection without media'],
		['collection_id' => 53, 'label' => 'Synthetic private collection', 'access' => 0],
		['collection_id' => 54, 'label' => 'Synthetic deleted collection', 'deleted' => 1],
		['collection_id' => 55, 'label' => 'Synthetic ACL-hidden collection', 'aclAllowed' => false]
	],
	'ca_entities' => [
		['entity_id' => 42, 'row_id' => 77, '_key' => 'relation_id', 'label' => 'Synthetic self link'],
		['entity_id' => 77, 'row_id' => 42, '_key' => 'relation_id', 'relation_id' => 999, 'label' => 'Synthetic related person', 'relationship_typename' => 'Parent of'],
		['entity_id' => 77, 'row_id' => 42, 'label' => 'Synthetic related person', 'relationship_typename' => 'Employer of'],
		['entity_id' => 78, 'label' => 'Synthetic private person', 'access' => 0],
		['entity_id' => 0, 'label' => 'Synthetic invalid ID'],
		['entity_id' => 79, 'label' => '   ']
	],
	'ca_places' => [['place_id' => 82, 'label' => 'Synthetic <img src=x onerror="alert(1)"> & "Harbor"', 'relationship_typename' => 'Located <script>alert(2)</script> & "near"']],
	'ca_occurrences' => [['occurrence_id' => 83, 'label' => 'Synthetic exhibition', 'relationship_typename' => 'Participant in']]
];
$groups = tadlAuthorityRelatedGroups(new AuthorityRequest(), $item, 'ca_entities');
checkAuthority(count($groups) === 4, 'Expected four visible related authority groups.');
$markup = join('', array_merge(...array_column($groups, 'links')));
checkAuthority(str_contains($markup, '/ca_collections/50') && !str_contains($markup, '/ca_collections/52'), 'Media-only collection filtering failed.');
checkAuthority(!str_contains($markup, 'Synthetic private') && !str_contains($markup, 'Synthetic deleted') && !str_contains($markup, 'Synthetic ACL-hidden'), 'Native access-filtered rows leaked.');
checkAuthority(count($GLOBALS['authorityMediaCalls']) === 1 && $GLOBALS['authorityMediaCalls'][0][1] === [50, 52], 'Collection eligibility must follow native access filtering.');
checkAuthority(substr_count($markup, '/ca_entities/77') === 2 && !str_contains($markup, '/ca_entities/42') && !str_contains($markup, '/ca_entities/999'), 'Self relations used row_id/relationship ID or removed distinct relationships.');
checkAuthority(str_contains($markup, 'Parent of') && str_contains($markup, 'Employer of') && str_contains($markup, 'Participant in'), 'Native directional relationship names were lost.');
checkAuthority(str_contains($markup, '</a></span> <span class="tadl-authority-relationship-role">(Parent of)</span>') && str_contains($markup, '(Employer of)</span>') && str_contains($markup, '(Participant in)</span>'), 'Relationship roles must follow their links with a space and parentheses.');
checkAuthority(str_contains($markup, 'Synthetic accessible media collection</a></span> <span class="tadl-authority-relationship-role">(Part of)</span>'), 'Collection relationships must use the same inline role convention.');
checkAuthority(!str_contains($markup, '<img ') && !str_contains($markup, '<script>') && str_contains($markup, '&lt;img') && str_contains($markup, '&lt;script&gt;'), 'Relationship label/role HTML was not escaped.');
checkAuthority(str_contains($markup, '&amp; &quot;Harbor&quot;') && str_contains($markup, '&amp; &quot;near&quot;'), 'Relationship label/role quotes were not escaped.');
$mediaCallsBefore = count($GLOBALS['authorityMediaCalls']);
$all = tadlAuthorityRelatedGroups(new AuthorityRequest('all'), $item, 'ca_entities');
checkAuthority(str_contains(join('', $all[0]['links']), 'Synthetic collection without media</a></span></li>'), 'Relationships without a role must omit parentheses and empty role markup.');
checkAuthority(str_contains(join('', $all[0]['links']), '/ca_collections/52'), 'All-items mode failed to restore an accessible collection without media.');
checkAuthority(count($GLOBALS['authorityMediaCalls']) === $mediaCallsBefore, 'All-items mode should not query media eligibility.');

foreach ([['ca_entities:ca_places', 'ca_places'], ['ca_places:preferred_labels', 'ca_places'], ['ca_entities:ca_entities', 'ca_entities']] as [$deniedBundle, $deniedTable]) {
	$GLOBALS['authorityBundleAccess'] = [$deniedBundle => 0];
	$item->relationReads = [];
	$restricted = tadlAuthorityRelatedGroups(new AuthorityRequest(), $item, 'ca_entities');
	checkAuthority(!in_array($deniedTable, array_column($item->relationReads, 0), true), 'Denied relation/label bundle queried records: '.$deniedBundle);
	checkAuthority(!in_array($deniedTable === 'ca_places' ? 'place_id' : 'entity_id', array_column($restricted, 'key'), true), 'Denied relation/label bundle rendered a group: '.$deniedBundle);
}
$GLOBALS['authorityBundleAccess'] = [];

// Front-end-only ACLs are not checked inside native getRelatedItems().
$GLOBALS['authorityPawAclTables'] = ['ca_entities', 'ca_collections'];
$GLOBALS['authorityAclDeniedIds'] = ['ca_entities' => [88], 'ca_collections' => [50]];
$frontAclItem = new AuthorityItem();
$frontAclItem->relationships = [
	'ca_entities' => [
		['entity_id' => 87, 'label' => 'Synthetic front-end ACL-visible person'],
		['entity_id' => 88, 'label' => 'Synthetic front-end ACL-hidden person', 'nativeAclAllowed' => true]
	],
	'ca_collections' => [['collection_id' => 50, 'label' => 'Synthetic front-end ACL-hidden collection', 'nativeAclAllowed' => true]]
];
$mediaCallsBefore = count($GLOBALS['authorityMediaCalls']);
$frontAclGroups = tadlAuthorityRelatedGroups(new AuthorityRequest(), $frontAclItem, 'ca_entities');
$frontAclMarkup = join('', array_merge(...array_column($frontAclGroups, 'links')));
checkAuthority(str_contains($frontAclMarkup, '/ca_entities/87') && !str_contains($frontAclMarkup, '/ca_entities/88'), 'Front-end-only ACL-hidden person leaked into sidebar links.');
checkAuthority(!str_contains($frontAclMarkup, '/ca_collections/50'), 'Front-end-only ACL-hidden collection leaked into sidebar links.');
checkAuthority(count($GLOBALS['authorityAclCalls']) === 2, 'Configured front-end-only ACLs were not checked for both related tables.');
checkAuthority(($GLOBALS['authorityMediaCalls'][$mediaCallsBefore][1] ?? []) === [], 'Collection media eligibility must run after front-end-only ACL filtering.');
$mediaCallsBefore = count($GLOBALS['authorityMediaCalls']);
$frontAclAllGroups = tadlAuthorityRelatedGroups(new AuthorityRequest('all'), $frontAclItem, 'ca_entities');
$frontAclAllMarkup = join('', array_merge(...array_column($frontAclAllGroups, 'links')));
checkAuthority(str_contains($frontAclAllMarkup, '/ca_entities/87') && !str_contains($frontAclAllMarkup, '/ca_entities/88') && !str_contains($frontAclAllMarkup, '/ca_collections/50'), 'All-items mode must preserve front-end-only ACL restrictions.');
checkAuthority(count($GLOBALS['authorityMediaCalls']) === $mediaCallsBefore, 'All-items ACL filtering should not query collection media.');
$GLOBALS['authorityPawAclTables'] = [];
$GLOBALS['authorityAclDeniedIds'] = [];

function authorityDocument($html) {
	$document = new DOMDocument();
	$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	return new DOMXPath($document);
}
function authorityScripts($html) {
	preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $matches);
	checkAuthority(count($matches[1]) === 1, 'Authority page should have one shared loader script.');
	checkAuthority(!str_contains($matches[1][0], '</script>'), 'Data terminated the authority loader script.');
	return $matches[1];
}

$item = new AuthorityItem();
foreach ([7 => 'Golf', 3 => 'charlie', 6 => 'Foxtrot', 1 => 'Alpha', 5 => 'Echo', 2 => 'bravo', 4 => 'Delta'] as $id => $label) {
	$item->relationships['ca_places'][] = ['place_id' => $id, 'label' => $label];
}
$html = (new AuthorityView(new AuthorityRequest(), $item))->render();
$xpath = authorityDocument($html);
$visible = $xpath->query('//section[contains(@class,"tadl-authority-related")]/ul/li/span/a');
checkAuthority(array_map(fn($node) => $node->textContent, iterator_to_array($visible)) === ['Alpha', 'bravo', 'charlie', 'Delta', 'Echo'], 'Visible relationships should show the first five alphabetically.');
$additional = $xpath->query('//details[contains(@class,"tadl-authority-more")]/ul/li/span/a');
checkAuthority(array_map(fn($node) => $node->textContent, iterator_to_array($additional)) === ['Foxtrot', 'Golf'], 'Native details disclosure should contain all remaining sorted relationships.');
checkAuthority($xpath->query('//details[contains(@class,"tadl-authority-more")]/summary')->item(0)->textContent === 'Show 2 more places', 'Disclosure should describe its additional relationships.');
checkAuthority($xpath->query('//aside[contains(@class,"tadl-detail-metadata")]//h2[contains(@class,"tadl-metadata-heading")]')->length === 1, 'Relationship headings must use shared Detail metadata styling.');
checkAuthority(!str_contains($html, 'tadl-authority-about'), 'Empty metadata should not create an empty About section.');

$nodeFixtures = [];
foreach (['ca_entities' => ['entity_id', 'Person'], 'ca_places' => ['place_id', 'Place'], 'ca_occurrences' => ['occurrence_id', 'Event']] as $table => [$key, $kind]) {
	foreach (['only', 'all'] as $mode) {
		foreach ([0, 1, 20] as $count) {
			$item = new AuthorityItem($table, 10, 'Individual', $count);
			$labelField = $table === 'ca_entities' ? 'displayname' : 'name';
			$item->fields['^'.$table.'.preferred_labels.'.$labelField] = 'Synthetic &lt;script&gt;alert("name")&lt;/script&gt; &amp; "Record"';
			$item->fields['^'.$table.'.idno'] = 'synthetic-42';
			$html = (new AuthorityView(new AuthorityRequest($mode), $item))->render();
			$xpath = authorityDocument($html);
			checkAuthority($xpath->query('//h1')->item(0)->textContent === 'Synthetic <script>alert("name")</script> & "Record"', 'Authority title changed or failed to escape encoded markup.');
			checkAuthority(!str_contains($html, '<script>alert("name")'), 'Hostile authority title emitted a real script tag.');
			checkAuthority(str_contains($html, '>'.$kind.'</div>'), 'Authority kind heading is incorrect for '.$table.'.');
			$expectedUrl = '/synthetic/Search/objects?'.http_build_query(['search' => $key.':42', 'view' => 'images', 's' => 0, '_advanced' => 0, 'clear' => 1]);
			$link = $xpath->query('//a[contains(@class,"tadl-authority-all-items")]')->item(0);
			checkAuthority($link->getAttribute('href') === $expectedUrl, 'Authority fallback Search URL omitted query/view/reset options.');
			checkAuthority($xpath->query('//noscript')->length === 1 && $xpath->query('//*[@id="browseResultsContainer"]')->item(0)->getAttribute('aria-busy') === 'true', 'Authority results need progressive fallback and a loading state.');
			checkAuthority(!in_array('ca_objects.object_id', $item->intrinsicReads, true), 'Authority layout should not switch on an unfiltered native object count.');
			checkAuthority($xpath->query('//div[contains(@class,"tadl-authority-layout--items-only")]')->length === 1, 'Empty sidebar should allow the results to use the full layout.');
			$scripts = authorityScripts($html);
			foreach (['success', 'notmodified', 'error'] as $status) {
				$nodeFixtures[] = ['name' => $table.' '.$mode.' '.$count.' '.$status, 'scripts' => $scripts, 'url' => $expectedUrl, 'count' => $count, 'status' => $status, 'empty' => $mode === 'only' ? 'No related items with digital media are currently available.' : 'No related items are currently available.'];
			}
		}
	}
}

$item = new AuthorityItem('ca_entities', 21, 'Synthetic studio');
$item->fields['^ca_entities.biography'] = '<p>Synthetic studio description.</p>';
$item->fields['^ca_entities.date.dates_value'] = '1900–1950';
$html = (new AuthorityView(new AuthorityRequest('all'), $item, ['commentsEnabled' => true, 'comments' => ['synthetic'], 'itemComments' => '<p>Synthetic comment.</p>', 'shareEnabled' => true, 'shareLink' => '<a href="/synthetic/share">Share</a>']))->render();
checkAuthority(str_contains($html, 'aria-label="About this organization"') && str_contains($html, 'Synthetic studio description.') && str_contains($html, '1900–1950'), 'Organization metadata fields or accessible section name were lost.');
$metadataXPath = authorityDocument($html);
checkAuthority($metadataXPath->query('//aside[contains(@class,"tadl-detail-metadata")]//section[contains(@class,"tadl-authority-about")]/h2')->length === 0, 'Metadata must start with field labels, without a redundant visible About heading.');
checkAuthority(str_contains($html, 'Comments (1)') && str_contains($html, 'Synthetic comment.') && str_contains($html, '/synthetic/share'), 'Native comment/share content was lost.');

$item = new AuthorityItem('ca_entities', 12, 'Synthetic photographer');
$item->occupations = [
	301 => ['occupation_name' => 'Photographer', 'occupation_date' => '1940–1960'],
	302 => ['occupation_name' => 'Archivist', 'occupation_date' => ''],
	303 => ['occupation_name' => '', 'occupation_date' => ''],
	304 => ['occupation_name' => '', 'occupation_date' => '1970'],
	305 => ['occupation_name' => '<script>synthetic</script> & "writer"', 'occupation_date' => '<b>1980</b>']
];
$item->fields['^ca_entities.individual_dates'] = ';'; // Reproduce the native empty container display.
$html = (new AuthorityView(new AuthorityRequest(), $item))->render();
checkAuthority(str_contains($html, 'Photographer (1940–1960)<br/>Archivist<br/>'), 'Occupations must retain row pairing and omit missing date punctuation.');
checkAuthority(!str_contains($html, '1970') && !str_contains($html, 'Archivist ()'), 'Blank occupation names or dates must not produce orphan values or punctuation.');
checkAuthority(str_contains($html, '&lt;script&gt;synthetic&lt;/script&gt; &amp; &quot;writer&quot; (&lt;b&gt;1980&lt;/b&gt;)'), 'Occupation name/date output must be escaped.');
checkAuthority(!str_contains($html, '<label>Dates</label>') && !str_contains($html, '<label>Birth date</label>') && !str_contains($html, '<label>Death date</label>'), 'Empty life dates must omit headings.');
checkAuthority(!in_array('^ca_entities.individual_dates', $item->templateReads, true), 'Entity dates must not use the delimiter-only container fallback.');

foreach (['individual_dates_birth' => 'Birth date', 'individual_birthdate' => 'Birth date', 'individual_dates_death' => 'Death date', 'individual_deathdate' => 'Death date'] as $code => $label) {
	$dated = new AuthorityItem();
	$dated->fields['^ca_entities.individual_dates.'.$code] = '1901';
	$html = (new AuthorityView(new AuthorityRequest(), $dated))->render();
	checkAuthority(str_contains($html, '<label>'.$label.'</label>1901'), 'Current/legacy individual date subfield missing: '.$code);
	checkAuthority(!str_contains($html, '<label>'.($label === 'Birth date' ? 'Death date' : 'Birth date').'</label>'), 'A missing companion date must not create a heading.');
}
$dated->fields['^ca_entities.individual_dates.individual_dates_death'] = '1988';
$html = (new AuthorityView(new AuthorityRequest(), $dated))->render();
checkAuthority(substr_count($html, '<label>Death date</label>') === 1 && str_contains($html, '<label>Death date</label>1988') && !str_contains($html, '<label>Death date</label>1901'), 'Current and legacy date aliases must not duplicate a field.');
$GLOBALS['authorityBundleAccess']['ca_entities:occupation'] = 0;
$item->intrinsicReads = [];
$html = (new AuthorityView(new AuthorityRequest(), $item))->render();
checkAuthority(!str_contains($html, '<label>Occupation</label>') && !in_array('ca_entities.occupation', $item->intrinsicReads, true), 'Denied occupations must not be read or displayed.');
$GLOBALS['authorityBundleAccess'] = [];
$organization = new AuthorityItem('ca_entities', 20, 'Organization');
$organization->occupations = $item->occupations;
$html = (new AuthorityView(new AuthorityRequest(), $organization))->render();
checkAuthority(!str_contains($html, '<label>Occupation</label>') && !in_array('ca_entities.occupation', $organization->intrinsicReads, true), 'Occupation display belongs only to individual entity types.');

$GLOBALS['authorityUrlOverride'] = "/synthetic/Search/objects?search=place_id:42&note=collector's \"quoted\" \\ </script> <>& \u{2028}\u{2029}";
$html = (new AuthorityView(new AuthorityRequest(), new AuthorityItem('ca_places')))->render();
$scripts = authorityScripts($html);
checkAuthority(authorityDocument($html)->query('//a[contains(@class,"tadl-authority-all-items")]')->item(0)->getAttribute('href') === $GLOBALS['authorityUrlOverride'], 'Fallback URL changed while escaping HTML attributes.');
checkAuthority(str_contains($html, htmlspecialchars($GLOBALS['authorityLoadingText'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), 'Loading translation was not escaped in the HTML state.');
$nodeFixtures[] = ['name' => 'hostile URL remains a single JavaScript string', 'scripts' => $scripts, 'url' => $GLOBALS['authorityUrlOverride'], 'count' => 1, 'status' => 'success', 'empty' => 'No related items with digital media are currently available.'];

$nodeCode = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const fixtures = JSON.parse(fs.readFileSync(0, 'utf8'));
let assertions = 0;
for (const fixture of fixtures) {
    const attributes = { 'aria-busy': 'true' };
    const loads = [];
    const replacements = [];
    const markerChecks = [];
    const container = {
        load(url, callback) { loads.push(url); callback.call(container, `<p data-tadl-result-count="${fixture.count}">${fixture.count} items</p>`, fixture.status); return container; },
        attr(name, value) { attributes[name] = value; return container; },
        find(selector) { markerChecks.push(selector); assert.equal(selector, '[data-tadl-result-count="0"]'); return { length: fixture.count === 0 ? 1 : 0 }; },
        html(value) { replacements.push(value); return container; }
    };
    const jquery = selector => {
        if (typeof selector !== 'string') return { ready(callback) { callback(); } };
        assert.equal(selector, '#browseResultsContainer');
        return container;
    };
    const context = vm.createContext({ document: {}, jQuery: jquery });
    for (const script of fixture.scripts) new vm.Script(script, { filename: fixture.name }).runInContext(context, { timeout: 1000 });
    assert.deepEqual(loads, [fixture.url], fixture.name + ': related results URL changed');
    assert.equal(attributes['aria-busy'], 'false', fixture.name + ': loading state did not clear');
    assertions += 2;
    if (fixture.status === 'error') {
        assert.equal(replacements.length, 1);
        assert.match(replacements[0], /tadl-authority-error/);
        assert.match(replacements[0], /Related items could not be loaded/);
        assert.match(replacements[0], /View all related items/);
        assert.deepEqual(markerChecks, [], fixture.name + ': failed response should not be interpreted as an empty result');
        assertions += 5;
    } else {
        assert.deepEqual(markerChecks, ['[data-tadl-result-count="0"]']);
        assertions++;
        if (fixture.count === 0) {
            assert.equal(replacements.length, 1);
            assert.match(replacements[0], /tadl-authority-empty/);
            assert.ok(replacements[0].includes(fixture.empty), fixture.name + ': wrong preference-specific empty text');
            assertions += 3;
        } else {
            assert.deepEqual(replacements, [], fixture.name + ': successful nonempty results were replaced');
            assertions++;
        }
    }
}
process.stdout.write(JSON.stringify({ assertions }));
JS;
$process = proc_open([getenv('TADL_TEST_NODE') ?: 'node', '-e', $nodeCode], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Node.js is required for emitted authority loader regressions.'); }
fwrite($pipes[0], json_encode($nodeFixtures, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
checkAuthority($status === 0, "Authority loader failed in Node.js:\n".$stderr);
$nodeResult = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
echo json_encode(['status' => 'passed', 'assertions' => $GLOBALS['authorityAssertions'] + $nodeResult['assertions'], 'loaderCases' => count($nodeFixtures), 'templates' => 'actual entity/place/occurrence details and shared helpers', 'boundaries' => 'synthetic native record/access/bundle/media/URL/view APIs; Node.js loader execution'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
