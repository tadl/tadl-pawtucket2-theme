<?php
	require_once(__DIR__.'/../Browse/collection_thumbnail_helpers.php');

	$o_collections_config = $this->getVar("collections_config");
	$qr_collections = $this->getVar("collection_results");
	$va_access_values = caGetUserAccessValues($this->request);
	$va_collection_ids = [];
	$vn_collection_count = 0;

	if($qr_collections && $qr_collections->numHits()) {
		$vn_collection_count = (int)$qr_collections->numHits();
		while($qr_collections->nextHit()) {
			$va_collection_ids[] = (int)$qr_collections->get("ca_collections.collection_id");
		}
		$qr_collections->seek(0);
	}

	$va_collection_images = tadlGetDescendantCollectionImages($va_collection_ids, [
		'version' => 'small',
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
						<button type="button" class="btn btn-default tadl-collection-view-toggle active" data-view="tiles" aria-pressed="true">
							<span class="glyphicon glyphicon-th" aria-hidden="true"></span>
							<?php print _t('Tiles'); ?>
						</button>
						<button type="button" class="btn btn-default tadl-collection-view-toggle" data-view="list" aria-pressed="false">
							<span class="glyphicon glyphicon-list" aria-hidden="true"></span>
							<?php print _t('List'); ?>
						</button>
					</div>
				</div>
<?php
	}
?>
			</div>
<?php
	if($qr_collections && $qr_collections->numHits()) {
		print "<div class='tadl-collections-grid' data-collection-view='tiles'>";
		while($qr_collections->nextHit()) {
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
		print "<nav class='tadl-collections-pagination' aria-label='"._t('Collections pages')."'></nav>";
	} else {
		print _t('No collections available');
	}
?>
		</div>
	</div>
	<script type="text/javascript">
		jQuery(function($) {
			var $grid = $('.tadl-collections-grid');
			var $items = $grid.find('[data-collection-item]');
			var $pagination = $('.tadl-collections-pagination');
			var $toggles = $('.tadl-collection-view-toggle');
			var storageKey = 'tadlCollectionsView';
			var state = {
				view: window.localStorage ? (localStorage.getItem(storageKey) || 'tiles') : 'tiles',
				page: 1
			};

			function pageSize() {
				return state.view === 'list' ? 30 : 9;
			}

			function pageCount() {
				return Math.max(1, Math.ceil($items.length / pageSize()));
			}

			function renderPagination() {
				var total = pageCount();
				var html = '';
				var pages = [];
				var lastPage = 0;

				if (total <= 1) {
					$pagination.empty();
					return;
				}

				pages = [1, state.page - 1, state.page, state.page + 1, total].filter(function(value, index, self) {
					return value >= 1 && value <= total && self.indexOf(value) === index;
				}).sort(function(a, b) {
					return a - b;
				});

				html += '<button type="button" class="btn btn-default tadl-collections-page" data-page="prev" ' + (state.page <= 1 ? 'disabled' : '') + ' aria-label="<?php print _t('Previous page'); ?>">&lsaquo;</button>';

				pages.forEach(function(pageNumber) {
					if (lastPage && pageNumber > lastPage + 1) {
						html += '<span class="tadl-collections-page-gap" aria-hidden="true">&hellip;</span>';
					}
					html += '<button type="button" class="btn btn-default tadl-collections-page' + (pageNumber === state.page ? ' active' : '') + '" data-page="' + pageNumber + '" aria-label="<?php print _t('Page'); ?> ' + pageNumber + '" ' + (pageNumber === state.page ? 'aria-current="page"' : '') + '>' + pageNumber + '</button>';
					lastPage = pageNumber;
				});

				html += '<button type="button" class="btn btn-default tadl-collections-page" data-page="next" ' + (state.page >= total ? 'disabled' : '') + ' aria-label="<?php print _t('Next page'); ?>">&rsaquo;</button>';

				$pagination.html(html);
			}

			function render() {
				var size = pageSize();
				var start = (state.page - 1) * size;
				var end = start + size;
				var total = pageCount();

				if (state.page > total) { state.page = total; }
				$grid.attr('data-collection-view', state.view);
				$toggles.removeClass('active').attr('aria-pressed', 'false');
				$toggles.filter('[data-view="' + state.view + '"]').addClass('active').attr('aria-pressed', 'true');

				$items.each(function(index) {
					$(this).toggle(index >= start && index < end);
				});

				renderPagination();
			}

			$toggles.on('click', function() {
				state.view = $(this).data('view');
				state.page = 1;
				if (window.localStorage) { localStorage.setItem(storageKey, state.view); }
				render();
			});

			$pagination.on('click', '.tadl-collections-page', function() {
				var target = $(this).data('page');
				var total = pageCount();

				if (target === 'prev') {
					state.page = Math.max(1, state.page - 1);
				} else if (target === 'next') {
					state.page = Math.min(total, state.page + 1);
				} else {
					state.page = Math.min(total, Math.max(1, parseInt(target, 10) || 1));
				}
				render();
			});

			render();
		});
	</script>
