<?php
	$qr_results = $this->getVar('result');
	if (!$qr_results || !($vn_result_count = (int)$qr_results->numHits())) { return; }

	$va_block_info = $this->getVar('blockInfo');
	$vs_block = (string)$this->getVar('block');
	$vs_table = $va_block_info['table'];
	$vs_search = (string)$this->getVar('search');
	$va_access_values = caGetUserAccessValues($this->request);
	$vn_preview_limit = min(6, max(1, (int)$this->getVar('itemsPerPage')));
	$vs_label_field = ($vs_table === 'ca_entities') ? 'displayname' : 'name';
	$va_preview_items = array();

	// Each overview starts with a bounded preview, independent of former carousel state.
	$qr_results->seek(0);
	while ((sizeof($va_preview_items) < $vn_preview_limit) && $qr_results->nextHit()) {
		$vn_id = (int)$qr_results->getPrimaryKey();
		$vs_label = trim((string)$qr_results->get("{$vs_table}.preferred_labels.{$vs_label_field}"));
		$va_preview_items[] = array(
			'id' => $vn_id,
			'label' => $vs_label ? $vs_label : _t('Untitled'),
			'url' => caDetailUrl($this->request, $vs_table, $vn_id),
			'image' => ''
		);
	}

	if ($vs_table === 'ca_objects') {
		$t_object = new ca_objects();
		$va_object_media = $t_object->getPrimaryMediaForIDs(array_column($va_preview_items, 'id'), array('medium'), array('checkAccess' => $va_access_values));
		foreach ($va_preview_items as &$va_item) {
			if ($vs_image_url = $va_object_media[$va_item['id']]['urls']['medium'] ?? '') {
				$va_item['image'] = '<img src="'.htmlspecialchars($vs_image_url, ENT_QUOTES, 'UTF-8').'" alt="" loading="lazy">';
			}
		}
		unset($va_item);
	} elseif ($vs_table === 'ca_collections') {
		$va_collection_ids = array_column($va_preview_items, 'id');
		$va_options = caGetOption('options', $va_block_info, array());
		$va_images = caGetDisplayImagesForAuthorityItems('ca_collections', $va_collection_ids, array(
			'version' => 'small',
			'relationshipTypes' => caGetOption('selectMediaUsingRelationshipTypes', $va_options, null),
			'objectTypes' => caGetOption('selectMediaUsingTypes', $va_options, null),
			'checkAccess' => $va_access_values
		));
		require_once(__DIR__.'/../Browse/collection_thumbnail_helpers.php');
		$va_descendant_images = tadlGetDescendantCollectionImages($va_collection_ids, array('version' => 'small', 'checkAccess' => $va_access_values));
		foreach ($va_preview_items as &$va_item) {
			$va_item['image'] = ($va_images[$va_item['id']] ?? '') ?: ($va_descendant_images[$va_item['id']] ?? '');
		}
		unset($va_item);
	}

	$vs_full_results_url = caNavUrl($this->request, '', 'Search', $vs_block, array(
		'search' => $vs_search,
		'source' => 'multisearch',
		'clear' => 1,
		'_advanced' => 0
	), array('useQueryString' => true));
	$vs_display_name = (string)$va_block_info['displayName'];
	$vb_media_cards = in_array($vs_table, array('ca_objects', 'ca_collections'), true);
	$vs_list_class = ($vs_table === 'ca_collections') ? 'tadl-search-collection-grid' : ($vb_media_cards ? 'tadl-search-grid' : 'tadl-search-list');
?>
<div class="tadl-search-section-header">
	<div>
		<h2 id="<?php print htmlspecialchars($vs_block.'-heading', ENT_QUOTES, 'UTF-8'); ?>"><?php print htmlspecialchars($vs_display_name, ENT_QUOTES, 'UTF-8').' ('.$vn_result_count.')'; ?></h2>
<?php
	if ($vn_result_count > sizeof($va_preview_items)) {
?>
		<small class="tadl-search-preview-count"><?php print htmlspecialchars(_t('Showing %1 of %2', sizeof($va_preview_items), $vn_result_count), ENT_QUOTES, 'UTF-8'); ?></small>
<?php
	}
?>
	</div>
	<a class="tadl-search-view-all" href="<?php print htmlspecialchars($vs_full_results_url, ENT_QUOTES, 'UTF-8'); ?>"><?php print htmlspecialchars(_t('View all %1', $vs_display_name), ENT_QUOTES, 'UTF-8'); ?> <span aria-hidden="true">&rarr;</span></a>
</div>
<ul class="<?php print $vs_list_class; ?>">
<?php
	foreach ($va_preview_items as $va_item) {
		$vs_item_url = htmlspecialchars($va_item['url'], ENT_QUOTES, 'UTF-8');
		$vs_item_label = htmlspecialchars($va_item['label'], ENT_QUOTES, 'UTF-8');
?>
	<li>
<?php
		if ($vb_media_cards) {
?>
		<a class="tadl-search-card<?php print ($vs_table === 'ca_collections') ? ' tadl-search-card--collection' : ''; ?>" href="<?php print $vs_item_url; ?>">
			<span class="tadl-search-card-media" aria-hidden="true">
<?php
			if ($va_item['image']) {
				print $va_item['image'];
			} else {
?>
				<span class="tadl-search-card-placeholder" aria-hidden="true"><i class="fa <?php print ($vs_table === 'ca_collections') ? 'fa-folder' : 'fa-image'; ?>"></i></span>
<?php
			}
?>
			</span>
			<span class="tadl-search-card-title"><?php print $vs_item_label; ?></span>
		</a>
<?php
		} else {
?>
		<a href="<?php print $vs_item_url; ?>"><?php print $vs_item_label; ?></a>
<?php
		}
?>
	</li>
<?php
	}
?>
</ul>
