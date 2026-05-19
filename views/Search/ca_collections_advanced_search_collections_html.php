<?php require_once(__DIR__.'/tadl_advanced_search_helpers.php'); ?>
<div class="tadl-advanced-search">
	<?php tadlAdvancedSearchHero($this->request, 'collections', _t('Collections Advanced Search'), _t('Search collection-level description, creators, dates, extent, language, subject terms, and rights statements.')); ?>

	{{{form}}}

	<div class="advancedContainer tadl-advanced-search-card">
		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Collection Identity'); ?></h2>
				<p><?php _p('Find collections by title, identifier, type, and date.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Keyword'), _t('Search across all indexed collection fields.'), '_fulltext', '{{{_fulltext%width=220px&height=1&label=Keyword}}}', 'col-sm-12');
				tadlAdvancedField(_t('Collection title'), _t('Search collection names.'), 'ca_collections_preferred_labels_name', '{{{ca_collections.preferred_labels.name%width=220px&label=Collection_title}}}');
				tadlAdvancedField(_t('Identifier'), _t('Search collection identifiers.'), 'ca_collections_idno', '{{{ca_collections.idno%width=220px&label=Identifier}}}');
				tadlAdvancedField(_t('Type'), _t('Limit results to a collection type.'), 'ca_collections_type_id', '{{{ca_collections.type_id%height=30px&id=ca_collections_type_id&label=Type}}}');
				tadlAdvancedField(_t('Date or date range'), _t('Search collection dates.'), 'ca_collections_date_dates_value', '{{{ca_collections.date.dates_value%width=220px&height=40px&useDatePicker=0&label=Date}}}');
?>
			</div>
		</section>

		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Description and Access'); ?></h2>
				<p><?php _p('Search descriptive, physical, language, subject, and rights metadata.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Creators'), _t('Search creators related to collections.'), 'ca_entities_preferred_labels_displayname_creator', '{{{ca_entities.preferred_labels.displayname%restrictToRelationshipTypes=creator&width=220px&label=Creators}}}');
				tadlAdvancedField(_t('Source of description'), _t('Search description source notes.'), 'ca_collections_description_source', '{{{ca_collections.description_source%width=220px&label=Source_of_description}}}');
				tadlAdvancedField(_t('Extent'), _t('Search extent statements.'), 'ca_collections_extent_text', '{{{ca_collections.extent_text%width=220px&label=Extent}}}');
				tadlAdvancedManualTextField(_t('Language'), _t('Search language metadata.'), 'ca_collections_language', 'ca_collections_language', '{{{ca_collections.language%width=220px&label=Language}}}');
				tadlAdvancedField(_t('Scope and content'), _t('Search scope and content notes.'), 'ca_collections_collection_scope_content', '{{{ca_collections.collection_scope_content%width=220px&height=80px&label=Scope_and_content}}}', 'col-sm-12');
				tadlAdvancedField(_t('Vocabulary terms'), _t('Search related vocabulary terms.'), 'ca_list_items_preferred_labels_name_singular', '{{{ca_list_items.preferred_labels.name_singular%width=220px&label=Vocabulary_terms}}}');
				tadlAdvancedField(_t('LOC heading'), _t('Search Library of Congress subject headings.'), 'ca_collections_lcsh_terms', '{{{ca_collections.lcsh_terms%width=220px&label=LOC_heading}}}');
				tadlAdvancedField(_t('Rights'), _t('Search rights statements.'), 'ca_collections_rights_rightsText', '{{{ca_collections.rights.rightsText%width=220px&label=Rights}}}');
				tadlAdvancedField(_t('Copyright statement'), _t('Search copyright statements.'), 'ca_collections_rights_copyrightStatement', '{{{ca_collections.rights.copyrightStatement%width=220px&label=Copyright_statement}}}');
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
		$('.advancedSearchField .formLabel').popover({container: 'body', placement: 'top', viewport: {selector: 'body', padding: 12}});
		$('.tadl-generated-search-field').find(':input').prop('disabled', true).attr('tabindex', '-1');
	});
</script>
