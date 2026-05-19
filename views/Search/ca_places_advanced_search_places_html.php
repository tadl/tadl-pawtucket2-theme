<?php require_once(__DIR__.'/tadl_advanced_search_helpers.php'); ?>
<div class="tadl-advanced-search">
	<?php tadlAdvancedSearchHero($this->request, 'places', _t('Places Advanced Search'), _t('Search place names, descriptions, coordinates, source information, and related records.')); ?>

	{{{form}}}

	<div class="advancedContainer tadl-advanced-search-card">
		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Place'); ?></h2>
				<p><?php _p('Find places by name, type, source, description, and coordinates.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Keyword'), _t('Search across all indexed place fields.'), '_fulltext', '{{{_fulltext%width=220px&height=1&label=Keyword}}}', 'col-sm-12');
				tadlAdvancedField(_t('Place name'), _t('Search preferred place names.'), 'ca_places_preferred_labels_name', '{{{ca_places.preferred_labels.name%width=220px&label=Place_name}}}');
				tadlAdvancedField(_t('Alternate names'), _t('Search alternate place names.'), 'ca_places_nonpreferred_labels_name', '{{{ca_places.nonpreferred_labels.name%width=220px&label=Alternate_names}}}');
				tadlAdvancedField(_t('Identifier'), _t('Search place identifiers.'), 'ca_places_idno', '{{{ca_places.idno%width=220px&label=Identifier}}}');
				tadlAdvancedField(_t('Type'), _t('Limit results to a place type.'), 'ca_places_type_id', '{{{ca_places.type_id%height=30px&id=ca_places_type_id&label=Type}}}');
				tadlAdvancedField(_t('Description'), _t('Search place descriptions.'), 'ca_places_description', '{{{ca_places.description%width=220px&height=80px&label=Description}}}', 'col-sm-12');
				tadlAdvancedField(_t('Source'), _t('Search place source values.'), 'ca_places_source_id', '{{{ca_places.source_id%height=30px&id=ca_places_source_id&label=Source}}}');
				tadlAdvancedField(_t('Coordinates'), _t('Search coordinate values.'), 'ca_places_georeference', '{{{ca_places.georeference%width=220px&label=Coordinates}}}');
?>
			</div>
		</section>

		<section class="tadl-advanced-section">
			<div class="tadl-advanced-section-heading">
				<h2><?php _p('Related Records'); ?></h2>
				<p><?php _p('Find places by connected collections, people, events, and objects.'); ?></p>
			</div>
			<div class="row">
<?php
				tadlAdvancedField(_t('Collections'), _t('Search related collections.'), 'ca_collections_preferred_labels_name', '{{{ca_collections.preferred_labels.name%width=220px&label=Collections}}}');
				tadlAdvancedField(_t('People / organizations'), _t('Search related people and organizations.'), 'ca_entities_preferred_labels_displayname', '{{{ca_entities.preferred_labels.displayname%width=220px&label=People_organizations}}}');
				tadlAdvancedField(_t('Events'), _t('Search related events.'), 'ca_occurrences_preferred_labels_name', '{{{ca_occurrences.preferred_labels.name%width=220px&label=Events}}}');
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
