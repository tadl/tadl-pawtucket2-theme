<?php
/* ----------------------------------------------------------------------
 * TADL object summary PDF
 * ----------------------------------------------------------------------
 *
 * Template configuration:
 *
 * @name TADL object summary
 * @type page
 * @pageSize letter
 * @pageOrientation portrait
 * @tables ca_objects
 * @marginTop 0.5in
 * @marginLeft 0.55in
 * @marginRight 0.55in
 * @marginBottom 0.5in
 *
 * ----------------------------------------------------------------------
 */

$t_item = $this->getVar('t_subject');

if (!function_exists('tadlPdfValue')) {
	function tadlPdfValue($item, $template, $skip_access_check = false) {
		$options = ['convertCodesToDisplayText' => true];
		if (!$skip_access_check) {
			$options['checkAccess'] = caGetUserAccessValues();
		}
		$value = trim((string)$item->getWithTemplate($template, $options));
		return strlen(trim(strip_tags($value))) ? $value : '';
	}
}

if (!function_exists('tadlPdfField')) {
	function tadlPdfField($label, $value) {
		if (!strlen(trim(strip_tags((string)$value)))) { return ''; }
		return "<div class='field'><h2>".htmlspecialchars($label, ENT_QUOTES, 'UTF-8')."</h2><div>{$value}</div></div>\n";
	}
}

if (!function_exists('tadlPdfFirstValue')) {
	function tadlPdfFirstValue($item, $templates, $skip_access_check = false) {
		foreach ($templates as $template) {
			$value = tadlPdfValue($item, $template, $skip_access_check);
			if ($value) { return $value; }
		}
		return '';
	}
}

if (!function_exists('tadlPdfRepresentationCaptions')) {
	function tadlPdfRepresentationCaptions($item) {
		if (!$item || !method_exists($item, 'getRepresentations')) { return ''; }
		if (!class_exists('ca_object_representations') && defined('__CA_MODELS_DIR__')) {
			require_once(__CA_MODELS_DIR__.'/ca_object_representations.php');
		}

		$captions = [];
		$representations = $item->getRepresentations(['thumbnail'], null, ['checkAccess' => caGetUserAccessValues()]);
		if (!is_array($representations)) { return ''; }

		foreach ($representations as $representation_id => $representation) {
			$rep = new ca_object_representations($representation_id);
			$caption = tadlPdfValue($rep, '^ca_object_representations.media_caption', true);
			if ($caption) { $captions[] = $caption; }
		}
		return join('<br/>', $captions);
	}
}

$title = htmlspecialchars($t_item->getLabelForDisplay(), ENT_QUOTES, 'UTF-8');
$type = htmlspecialchars($t_item->getTypeName(), ENT_QUOTES, 'UTF-8');
$reps = $t_item->getRepresentations(['medium', 'thumbnail'], null, ['checkAccess' => caGetUserAccessValues()]);
?>
<html>
	<head>
		<title><?= _t('Summary for %1', $t_item->getLabelForDisplay()); ?></title>
		<meta charset="utf-8" />
		<style>
			body { color: #252525; font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.45; margin: 0; padding: 0; }
			.header { border-bottom: 4px solid #3f7f16; margin-bottom: 18px; padding-bottom: 10px; }
			.site { color: #214c78; font-size: 12px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
			h1 { color: #111; font-size: 24px; line-height: 1.2; margin: 6px 0 4px; }
			.type { color: #555; font-size: 13px; font-weight: bold; text-transform: uppercase; }
			.representation { margin: 12px 0 18px; text-align: center; }
			.representation img { height: auto; max-height: 260px; max-width: 100%; width: auto; }
			.field { border-top: 1px solid #d9d9d9; padding: 8px 0; }
			.field h2 { color: #214c78; font-size: 11px; letter-spacing: 0.8px; margin: 0 0 3px; text-transform: uppercase; }
			a { color: #214c78; text-decoration: none; }
			.footer { border-top: 1px solid #d9d9d9; color: #666; font-size: 10px; margin-top: 18px; padding-top: 8px; }
		</style>
	</head>
	<body>
		<div class="header">
			<div class="site">Traverse Area District Library Local History Collection</div>
			<h1><?= $title; ?></h1>
			<div class="type"><?= $type; ?></div>
		</div>

<?php
if (is_array($reps) && sizeof($reps)) {
	print "<div class='representation'>";
	foreach ($reps as $rep) {
		print $rep['tags']['medium'] ?? $rep['tags']['thumbnail'] ?? '';
		break;
	}
	print "</div>\n";
}

print tadlPdfField('Identifier', tadlPdfValue($t_item, '^ca_objects.idno'));
print tadlPdfField('Alternate title', tadlPdfValue($t_item, '<unit relativeTo="ca_objects.nonpreferred_labels" delimiter="<br/>">^ca_objects.nonpreferred_labels.name</unit>'));
print tadlPdfField('Date', tadlPdfValue($t_item, '^ca_objects.date.dates_value'));
print tadlPdfField('Creators', tadlPdfValue($t_item, '<unit relativeTo="ca_entities" restrictToRelationshipTypes="creator" delimiter="<br/>">^ca_entities.preferred_labels.displayname</unit>'));
print tadlPdfField('Publisher', tadlPdfValue($t_item, '<unit relativeTo="ca_entities" restrictToRelationshipTypes="publisher" delimiter="<br/>">^ca_entities.preferred_labels.displayname</unit>'));
print tadlPdfField('Description', tadlPdfValue($t_item, '^ca_objects.description'));
print tadlPdfField('Source of description', tadlPdfValue($t_item, '^ca_objects.description_source'));
print tadlPdfField('Languages', tadlPdfValue($t_item, '^ca_objects.language', true));
print tadlPdfField('Dimensions', tadlPdfValue($t_item, '<unit relativeTo="ca_objects.dimensions" delimiter="<br/>"><ifdef code="ca_objects.dimensions.dimensions_height">^ca_objects.dimensions.dimensions_height H</ifdef><ifdef code="ca_objects.dimensions.dimensions_width"> x ^ca_objects.dimensions.dimensions_width W</ifdef><ifdef code="ca_objects.dimensions.dimensions_depth"> x ^ca_objects.dimensions.dimensions_depth D</ifdef><ifdef code="ca_objects.dimensions.dimensions_diameter"> x ^ca_objects.dimensions.dimensions_diameter diameter</ifdef><ifdef code="ca_objects.dimensions.dimensions_weight">; ^ca_objects.dimensions.dimensions_weight</ifdef><ifdef code="ca_objects.dimensions.dimensions_type"> (^ca_objects.dimensions.dimensions_type)</ifdef></unit>'));
print tadlPdfField('Inscriptions/marks', tadlPdfValue($t_item, '^ca_objects.inscriptions_marks'));
print tadlPdfField('Materials and techniques', tadlPdfFirstValue($t_item, [
	'^ca_objects.materials_techniques',
	'^ca_objects.text_format',
	'^ca_objects.image_format',
	'^ca_objects.formatNotes'
], true));
print tadlPdfField('Art and Architecture terms', tadlPdfValue($t_item, '^ca_objects.art_architecture_authority'));
print tadlPdfField('Thesaurus terms', tadlPdfValue($t_item, '^ca_objects.lctgm'));
print tadlPdfField('Library of Congress subject headings', tadlPdfValue($t_item, '^ca_objects.lcsh_terms'));
print tadlPdfField('Rights', tadlPdfValue($t_item, '^ca_objects.rights.rightsText'));
print tadlPdfField('Copyright statement', tadlPdfValue($t_item, '^ca_objects.rights.copyrightStatement'));
print tadlPdfField('Media caption', tadlPdfRepresentationCaptions($t_item));
print tadlPdfField('External links', tadlPdfValue($t_item, '<unit relativeTo="ca_objects.external_link" delimiter="<br/>"><ifdef code="ca_objects.external_link.url_source">^ca_objects.external_link.url_source: </ifdef><a href="^ca_objects.external_link.url_entry">^ca_objects.external_link.url_entry</a></unit>'));
print tadlPdfField('Related people/organizations', tadlPdfValue($t_item, '<unit relativeTo="ca_entities" delimiter="<br/>" excludeRelationshipTypes="creator,publisher">^ca_entities.preferred_labels.displayname (^relationship_typename)</unit>'));
print tadlPdfField('Related events', tadlPdfValue($t_item, '<unit relativeTo="ca_occurrences" delimiter="<br/>">^ca_occurrences.preferred_labels (^relationship_typename)</unit>'));
print tadlPdfField('Related places', tadlPdfValue($t_item, '<unit relativeTo="ca_places" delimiter="<br/>">^ca_places.preferred_labels (^relationship_typename)</unit>'));
print tadlPdfField('Related objects', tadlPdfValue($t_item, '<unit relativeTo="ca_objects.related" delimiter="<br/>">^ca_objects.related.preferred_labels.name (^relationship_typename)</unit>'));
?>
		<div class="footer">
			Generated from TADL Local History Collection.
		</div>
	</body>
</html>
