<?php require_once(__DIR__.'/tadl_advanced_search_helpers.php'); ?>
<div class="tadl-advanced-search">
	<?php tadlAdvancedSearchHero($this->request, 'organizations', _t('Organizations Advanced Search'), _t('Search organization names, dates, descriptions, source notes, external links, and related records.')); ?>

	{{{form}}}

	<div class="advancedContainer tadl-advanced-search-card">
		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Organization'); ?></h2>
				<p><?php _p('Find organizations by name, identifier, dates, and descriptive notes.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Keyword'), _t('Search across all indexed organization fields.'), '_fulltext', '{{{_fulltext%width=220px&height=1&label=Keyword}}}', 'col-sm-12');
				tadlAdvancedField(_t('Preferred name'), _t('Search preferred organization names.'), 'ca_entities_preferred_labels_displayname', '{{{ca_entities.preferred_labels.displayname%width=220px&label=Preferred_name}}}');
				tadlAdvancedField(_t('Alternate names'), _t('Search alternate names.'), 'ca_entities_nonpreferred_labels_displayname', '{{{ca_entities.nonpreferred_labels.displayname%width=220px&label=Alternate_names}}}');
				tadlAdvancedField(_t('Identifier'), _t('Search entity identifiers.'), 'ca_entities_idno', '{{{ca_entities.idno%width=220px&label=Identifier}}}');
				tadlAdvancedField(_t('Dates'), _t('Search organization dates.'), 'ca_entities_individual_dates_dates_value', '{{{ca_entities.individual_dates.dates_value%width=220px&height=40px&useDatePicker=0&label=Dates}}}');
				tadlAdvancedField(_t('Description'), _t('Search organization descriptions.'), 'ca_entities_biography', '{{{ca_entities.biography%width=220px&height=80px&label=Description}}}', 'col-sm-12');
				tadlAdvancedField(_t('Source of description'), _t('Search source notes.'), 'ca_entities_biography_source', '{{{ca_entities.biography_source%width=220px&label=Source_of_description}}}');
				tadlAdvancedField(_t('External links'), _t('Search external link labels or URLs.'), 'ca_entities_external_link_url_entry', '{{{ca_entities.external_link.url_entry%width=220px&label=External_links}}}');
?>
			</div>
		</section>

		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Related Records'); ?></h2>
				<p><?php _p('Find organizations by connected events, places, collections, and objects.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Related events'), _t('Search related events.'), 'ca_occurrences_preferred_labels_name', '{{{ca_occurrences.preferred_labels.name%width=220px&label=Related_events}}}');
				tadlAdvancedField(_t('Related places'), _t('Search related places.'), 'ca_places_preferred_labels_name', '{{{ca_places.preferred_labels.name%width=220px&label=Related_places}}}');
				tadlAdvancedField(_t('Related collections'), _t('Search related collections.'), 'ca_collections_preferred_labels_name', '{{{ca_collections.preferred_labels.name%width=220px&label=Related_collections}}}');
				tadlAdvancedField(_t('Related objects'), _t('Search related object titles.'), 'ca_objects_preferred_labels_name', '{{{ca_objects.preferred_labels.name%width=220px&label=Related_objects}}}');
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
	});
</script>
