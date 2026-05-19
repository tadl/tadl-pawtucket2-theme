<?php require_once(__DIR__.'/tadl_advanced_search_helpers.php'); ?>
<div class="tadl-advanced-search">
	<?php tadlAdvancedSearchHero($this->request, 'occurrences', _t('Events Advanced Search'), _t('Search event names, identifiers, dates, source notes, and related people, organizations, places, collections, and objects.')); ?>

	{{{form}}}

	<div class="advancedContainer tadl-advanced-search-card">
		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Event'); ?></h2>
				<p><?php _p('Find events by name, identifier, dates, and description.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Keyword'), _t('Search across all indexed event fields.'), '_fulltext', '{{{_fulltext%width=220px&height=1&label=Keyword}}}', 'col-sm-12');
				tadlAdvancedField(_t('Event name'), _t('Search event names.'), 'ca_occurrences_preferred_labels_name', '{{{ca_occurrences.preferred_labels.name%width=220px&label=Event_name}}}');
				tadlAdvancedField(_t('Identifier'), _t('Search event identifiers.'), 'ca_occurrences_idno', '{{{ca_occurrences.idno%width=220px&label=Identifier}}}');
				tadlAdvancedField(_t('Type'), _t('Limit results to an event type.'), 'ca_occurrences_type_id', '{{{ca_occurrences.type_id%height=30px&id=ca_occurrences_type_id&label=Type}}}');
				tadlAdvancedField(_t('Dates'), _t('Search event dates.'), 'ca_occurrences_occurrence_dates_dates_value', '{{{ca_occurrences.occurrence_dates.dates_value%width=220px&height=40px&useDatePicker=0&label=Dates}}}');
				tadlAdvancedField(_t('Description'), _t('Search event descriptions.'), 'ca_occurrences_description', '{{{ca_occurrences.description%width=220px&height=80px&label=Description}}}', 'col-sm-12');
				tadlAdvancedField(_t('Source of description'), _t('Search source notes.'), 'ca_occurrences_description_source', '{{{ca_occurrences.description_source%width=220px&label=Source_of_description}}}', 'col-sm-12');
?>
			</div>
		</section>

		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Related Records'); ?></h2>
				<p><?php _p('Find events by records and authorities connected to them.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('People / organizations'), _t('Search related people and organizations.'), 'ca_entities_preferred_labels_displayname', '{{{ca_entities.preferred_labels.displayname%width=220px&label=People_organizations}}}');
				tadlAdvancedField(_t('Places'), _t('Search related places.'), 'ca_places_preferred_labels_name', '{{{ca_places.preferred_labels.name%width=220px&label=Places}}}');
				tadlAdvancedField(_t('Collections'), _t('Search related collections.'), 'ca_collections_preferred_labels_name', '{{{ca_collections.preferred_labels.name%width=220px&label=Collections}}}');
				tadlAdvancedField(_t('Objects'), _t('Search related object titles.'), 'ca_objects_preferred_labels_name', '{{{ca_objects.preferred_labels.name%width=220px&label=Objects}}}');
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
	});
</script>
