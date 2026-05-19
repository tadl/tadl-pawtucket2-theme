<?php
	require_once(__CA_MODELS_DIR__.'/ca_objects.php');
	require_once(__DIR__.'/tadl_advanced_search_helpers.php');

	$t_object_types = new ca_objects();
	$va_object_types = $t_object_types->getTypeList(array('omitRoot' => true));
	if (!is_array($va_object_types) || !sizeof($va_object_types)) {
		$va_object_types = $t_object_types->getTypeList();
	}

	$vm_selected_type = $_REQUEST['ca_objects_type_id'] ?? ($_REQUEST['ca_objects.type_id'] ?? null);
	if (is_array($vm_selected_type)) { $vm_selected_type = array_shift($vm_selected_type); }
	$vm_selected_type = (string)$vm_selected_type;

?>
<div class="tadl-advanced-search">
	<?php tadlAdvancedSearchHero($this->request, 'objects', _t('Objects Advanced Search'), _t('Use one field for a broad search, or combine several fields to narrow results across titles, identifiers, dates, collections, people, subjects, rights, and physical description.')); ?>

	{{{form}}}

	<div class="advancedContainer tadl-advanced-search-card">
		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Start Broad'); ?></h2>
				<p><?php _p('Good first-pass fields for exploratory research.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Keyword'), _t('Search across all indexed fields.'), '_fulltext', '{{{_fulltext%width=200px&height=1&label=Keyword}}}', 'col-sm-12');
				tadlAdvancedField(_t('Title'), _t('Limit your search to object titles.'), 'ca_objects_preferred_labels_name', '{{{ca_objects.preferred_labels.name%width=220px&label=Title}}}');
				tadlAdvancedField(_t('Identifier'), _t('Search object identifiers and accession numbers.'), 'ca_objects_idno', '{{{ca_objects.idno%width=210px&label=Identifier}}}');
?>
				<div class="advancedSearchField col-sm-6">
					<label for="ca_objects_type_id" class="formLabel" data-toggle="popover" data-trigger="hover" data-content="<?php _p('Limit results to a specific object type.'); ?>"><?php _p('Type'); ?></label>
					<select name="ca_objects_type_id[]" id="ca_objects_type_id" class="form-control">
						<option value=""><?php _p('Any type'); ?></option>
<?php
						foreach($va_object_types as $vn_type_id => $va_type_info) {
							if (!$vn_type_id) { continue; }
							$vs_type_label = $va_type_info['name_singular'] ?? ($va_type_info['name_plural'] ?? ($va_type_info['idno'] ?? $vn_type_id));
							print "<option value=\"".(int)$vn_type_id."\"".(((string)$vn_type_id === $vm_selected_type) ? " selected" : "").">".htmlspecialchars($vs_type_label, ENT_QUOTES, 'UTF-8')."</option>\n";
						}
?>
					</select>
					<input name="ca_objects_type_id_label" value="<?php _p('Type'); ?>" type="hidden"/>
					<div class="tadl-generated-search-field" aria-hidden="true">
						{{{ca_objects.type_id%height=30px&id=ca_objects_type_id_generated&label=Type}}}
					</div>
				</div>
<?php
				tadlAdvancedField(_t('Date or date range'), _t('Search records for a particular date or date range, such as 1910 or 1910-1920.'), 'ca_objects_date_dates_value', '{{{ca_objects.date.dates_value%width=200px&height=40px&useDatePicker=0&label=Date}}}');
?>
			</div>
		</section>

		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('People, Collections, and Context'); ?></h2>
				<p><?php _p('Useful when you know who created something, where it belongs, or how it was described.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Collection'), _t('Search records within a particular collection.'), 'ca_collections_preferred_labels', '{{{ca_collections.preferred_labels%restrictToTypes=collection&width=200px&height=40px&label=Collection}}}');
				tadlAdvancedField(_t('People / organizations'), _t('Search related people and organizations.'), 'ca_entities_preferred_labels_displayname', '{{{ca_entities.preferred_labels.displayname%width=220px&label=People_organizations}}}');
				tadlAdvancedField(_t('Creators'), _t('Search people or organizations related as creators.'), 'ca_entities_preferred_labels_displayname_creator', '{{{ca_entities.preferred_labels.displayname%restrictToRelationshipTypes=creator&width=220px&label=Creators}}}');
				tadlAdvancedField(_t('Publisher'), _t('Search people or organizations related as publishers.'), 'ca_entities_preferred_labels_displayname_publisher', '{{{ca_entities.preferred_labels.displayname%restrictToRelationshipTypes=publisher&width=220px&label=Publisher}}}');
				tadlAdvancedField(_t('Description'), _t('Search public descriptive notes.'), 'ca_objects_description', '{{{ca_objects.description%width=220px&height=80px&label=Description}}}', 'col-sm-12');
				tadlAdvancedField(_t('Source of description'), _t('Search notes about where the description came from.'), 'ca_objects_description_source', '{{{ca_objects.description_source%width=220px&label=Source_of_description}}}', 'col-sm-12');
?>
			</div>
		</section>

		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Subjects, Physical Description, and Rights'); ?></h2>
				<p><?php _p('More targeted fields for known-item searches and rights review.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Languages'), _t('Search language metadata.'), 'ca_objects_language', '{{{ca_objects.language%width=220px&label=Languages}}}');
				tadlAdvancedField(_t('Materials and techniques'), _t('Search materials and technique notes.'), 'ca_objects_materials_techniques', '{{{ca_objects.materials_techniques%width=220px&label=Materials_and_techniques}}}');
				tadlAdvancedField(_t('Inscriptions / marks'), _t('Search inscriptions, markings, and annotations.'), 'ca_objects_inscriptions_marks', '{{{ca_objects.inscriptions_marks%width=220px&label=Inscriptions_marks}}}');
				tadlAdvancedField(_t('Subject headings'), _t('Search Library of Congress or other subject terms.'), 'ca_objects_lcsh_terms', '{{{ca_objects.lcsh_terms%width=220px&label=Subject_headings}}}');
				tadlAdvancedField(_t('Rights'), _t('Search rights statements.'), 'ca_objects_rights_rightsText', '{{{ca_objects.rights.rightsText%width=220px&label=Rights}}}');
				tadlAdvancedField(_t('Copyright statement'), _t('Search copyright statements.'), 'ca_objects_rights_copyrightStatement', '{{{ca_objects.rights.copyrightStatement%width=220px&label=Copyright_statement}}}');
				tadlAdvancedField(_t('Website name'), _t('Search external link labels.'), 'ca_objects_external_link_url_source', '{{{ca_objects.external_link.url_source%width=220px&label=Website_name}}}');
				tadlAdvancedField(_t('URL'), _t('Search external URLs.'), 'ca_objects_external_link_url_entry', '{{{ca_objects.external_link.url_entry%width=220px&label=URL}}}');
?>
			</div>
		</section>

		<div class="advancedFormSubmit tadl-advanced-actions">
			<span class="btn btn-default">{{{reset%label=Reset}}}</span>
			<span class="btn btn-primary">{{{submit%label=Search}}}</span>
		</div>
	</div>

	{{{/form}}}
</div>

<script>
	jQuery(document).ready(function() {
		$('.advancedSearchField .formLabel').popover();
		$('.tadl-generated-search-field').find(':input').prop('disabled', true).attr('tabindex', '-1');
	});
</script>
