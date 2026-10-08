<?php
	require_once(__DIR__.'/../Browse/collection_thumbnail_helpers.php');
	require_once(__DIR__.'/index_helpers.php');

	$o_collections_config = $this->getVar("collections_config");
	$qr_collections = $this->getVar("collection_results");
	tadlFilterMediaResult($this->request, $qr_collections);
	$va_access_values = caGetUserAccessValues($this->request);
	$va_collection_ids = [];
	$vn_collection_count = 0;
	$vs_collection_view = $this->request->getParameter('view', pString, ['forcePurify' => true]);
	if (!in_array($vs_collection_view, ['tiles', 'list'], true)) {
		$vs_collection_view = Session::getVar('tadlCollectionsView');
	}
	if (!in_array($vs_collection_view, ['tiles', 'list'], true)) { $vs_collection_view = 'tiles'; }
	Session::setVar('tadlCollectionsView', $vs_collection_view);
	$vn_page_size = $vs_collection_view === 'list' ? 30 : 9;
	$vn_collection_count = $qr_collections ? (int)$qr_collections->numHits() : 0;
	$vn_total_pages = max(1, (int)ceil($vn_collection_count / $vn_page_size));
	$vn_page = min($vn_total_pages, max(1, (int)$this->request->getParameter('page', pInteger)));
	$vn_start = ($vn_page - 1) * $vn_page_size;
	$this->setVar('start', $vn_start);
	tadlMediaResultContext($this, $qr_collections, 'collections');

	if($qr_collections && $qr_collections->numHits()) {
		$qr_collections->seek($vn_start);
		while(count($va_collection_ids) < $vn_page_size && $qr_collections->nextHit()) {
			$va_collection_ids[] = (int)$qr_collections->get("ca_collections.collection_id");
		}
		$qr_collections->seek($vn_start);
	}

	$va_collection_images = tadlGetDescendantCollectionImages($va_collection_ids, [
		'request' => $this->request, 'version' => 'small',
		'checkAccess' => $va_access_values
	]);
?>
	<div class="row">
		<div class='col-md-12 col-lg-12 collectionsList'>
			<div class="tadl-collections-header">
				<div>
					<h1><?php print $this->getVar("section_name"); ?></h1>
					<p><?php print $o_collections_config->get("collections_intro_text"); ?></p>
				</div>
<?php
	if($qr_collections && $qr_collections->numHits()) {
?>
				<div class="tadl-collections-tools" aria-label="<?php print _t('Collection display options'); ?>">
					<div class="tadl-collections-count"><?php print _t('%1 collections', $vn_collection_count); ?></div>
					<div class="tadl-collections-view-buttons">
<?php
		foreach (['tiles' => ['glyphicon-th', _t('Tiles')], 'list' => ['glyphicon-list', _t('List')]] as $vs_view => $va_view_info) {
			$vs_content = '<span class="glyphicon '.$va_view_info[0].'" aria-hidden="true"></span> '.htmlspecialchars($va_view_info[1], ENT_QUOTES, 'UTF-8');
			if ($vs_collection_view === $vs_view) {
				print '<span class="btn btn-default tadl-collection-view-toggle active" aria-current="true">'.$vs_content.'</span>';
			} else {
				print caNavLink($this->request, $vs_content, 'btn btn-default tadl-collection-view-toggle', '', 'Collections', 'Index', ['view' => $vs_view, 'page' => 1]);
			}
		}
?>
					</div>
					<?php print tadlCollectionIndexPager($this->request, $vn_page, $vn_total_pages, $vs_collection_view); ?>
				</div>
<?php
	}
?>
			</div>
<?php
	if($qr_collections && $qr_collections->numHits()) {
		print "<div class='tadl-collections-grid' data-collection-view='".$vs_collection_view."'>";
		$vn_rendered_count = 0;
		while($vn_rendered_count < $vn_page_size && $qr_collections->nextHit()) {
			$vn_rendered_count++;
			$vn_collection_id = (int)$qr_collections->get("ca_collections.collection_id");
			$vs_collection_label = $qr_collections->get("ca_collections.preferred_labels");
			$vs_image = $va_collection_images[$vn_collection_id] ?? '';
			$vs_card_url = caDetailUrl($this->request, "ca_collections", $vn_collection_id);
			$vs_scope = '';

			if (($o_collections_config->get("description_template")) && ($vs_scope = $qr_collections->getWithTemplate($o_collections_config->get("description_template")))) {
				$vs_scope = "<div class='tadl-collection-summary'>".$vs_scope."</div>";
			}

			print "<article class='collectionTile tadl-collection-card' data-collection-item>";
			print "<a class='tadl-collection-card-link' href='".htmlspecialchars($vs_card_url, ENT_QUOTES, 'UTF-8')."'>";
			print "<div class='tadl-collection-thumb'>";
			if ($vs_image) {
				print $vs_image;
			} else {
				print "<div class='tadl-collection-placeholder' aria-hidden='true'><span class='glyphicon glyphicon-folder-open'></span></div>";
			}
			print "</div>";
			print "<div class='tadl-collection-content'>";
			print "<div class='title'>".htmlspecialchars($vs_collection_label, ENT_QUOTES, 'UTF-8')."</div>";
			print $vs_scope;
			print "<span class='tadl-collection-action'>View collection <span aria-hidden='true'>&rarr;</span></span>";
			print "</div>";
			print "</a>";
			print "</article>";
		}
		print "</div>";
		print tadlCollectionIndexPager($this->request, $vn_page, $vn_total_pages, $vs_collection_view);
	} else {
		print _t('No collections available');
	}
?>
		</div>
	</div>
	<script type="text/javascript">
		// Keep the initial history entry tied to its rendered page and view.
		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, '', <?php print json_encode(caNavUrl($this->request, '', 'Collections', 'Index', ['page' => $vn_page, 'view' => $vs_collection_view]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + window.location.hash);
		}
	</script>
