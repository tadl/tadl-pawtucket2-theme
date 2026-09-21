<?php
	$va_access_values = $this->getVar("access_values");
	$o_collections_config = $this->getVar("collections_config");
	$t_item = $this->getVar("item");
	$va_exclude_collection_type_ids = $this->getVar("exclude_collection_type_ids");
	$va_non_linkable_collection_type_ids = $this->getVar("non_linkable_collection_type_ids");
	$va_collection_type_icons = $this->getVar("collection_type_icons");
	$vs_child_collection_sort = $o_collections_config->get("detail_child_collection_sort");
	if(!$vs_child_collection_sort) {
		$vs_child_collection_sort = "ca_collections.rank";
	}
	$vb_has_children = false;
	$vb_has_grandchildren = false;
	if($va_collection_children = $t_item->get('ca_collections.children.collection_id', array('returnAsArray' => true, 'checkAccess' => $va_access_values, 'sort' => $vs_child_collection_sort))){
		$vb_has_children = true;
		$qr_collection_children = caMakeSearchResult("ca_collections", $va_collection_children);
		if($qr_collection_children->numHits()){
			while($qr_collection_children->nextHit()){
				if($qr_collection_children->get("ca_collections.children.collection_id", array('returnAsArray' => true, 'checkAccess' => $va_access_values, 'sort' => $vs_child_collection_sort))){
					$vb_has_grandchildren = true;
				}
			}
		}
		$qr_collection_children->seek(0);
	}
	if($vb_has_children){
?>					
			<div class='text-right'><a href='#' onclick='$("#collectionsWrapper").toggle(300);return false;' class='showHide'>Show/Hide Collection Browser</a></div>
				<div class="row" id="collectionsWrapper" <?php print ($o_collections_config->get("browser_closed")) ? "style='display:none;'" : ""; ?>>			
					<div class='col-sm-12'>
					
					
						<div class='unit row'>
							<div class='col-xs-12 col-sm-12 col-md-12 col-lg-12'>
								<hr class='divide' style='margin-bottom:0px; margin-top:3px;'></hr>
							</div>
						</div>
						<div class='unit row'>
							<div class='col-xs-12<?php print ($vb_has_grandchildren) ? " col-sm-4 col-md-4 col-lg-4" : ""; ?>'>
								<div class='collectionsContainer'><div class='label'><?php print ucFirst($t_item->get("ca_collections.type_id", array('convertCodesToDisplayText' => true))); ?> Contents</div>
<?php
					if($qr_collection_children->numHits()){
						while($qr_collection_children->nextHit()) {
							$vs_icon = "";
							if(is_array($va_collection_type_icons)){
								$vs_icon = $va_collection_type_icons[$qr_collection_children->get("ca_collections.type_id")];
							}
							print "<div style='margin-left:0px;margin-top:5px;'>";
							$vn_child_collection_id = (int)$qr_collection_children->get("ca_collections.collection_id");
							$va_grand_children_type_ids = $qr_collection_children->get("ca_collections.children.type_id", array('returnAsArray' => true, 'checkAccess' => $va_access_values));
							$vn_rel_object_count = sizeof($qr_collection_children->get("ca_objects.object_id", array('returnAsArray' => true, 'checkAccess' => $va_access_values)));
							$vs_record_count = "";
							if($vn_rel_object_count){
								$vs_record_count = "<br/><small>(".$vn_rel_object_count." record".(($vn_rel_object_count == 1) ? "" : "s").")</small>";
							}
							if(sizeof($va_grand_children_type_ids)){
								# Keep a real detail URL as the non-JavaScript and modified-click fallback.
								$vs_detail_url = caDetailUrl($this->request, 'ca_collections', $vn_child_collection_id);
								$vs_child_list_url = caNavUrl($this->request, '', 'Collections', 'childList', array('collection_id' => $vn_child_collection_id));
								print "<a href=\"".htmlspecialchars($vs_detail_url, ENT_QUOTES, 'UTF-8')."\" class=\"openCollection openCollection{$vn_child_collection_id}\" data-collection-id=\"{$vn_child_collection_id}\" data-child-list-url=\"".htmlspecialchars($vs_child_list_url, ENT_QUOTES, 'UTF-8')."\" aria-controls=\"collectionLoad\" aria-expanded=\"false\">".$vs_icon." ".$qr_collection_children->get('ca_collections.preferred_labels')."</a>".$vs_record_count;
							}else{
								$vb_link_to_detail = true;
								if(is_array($va_non_linkable_collection_type_ids) && (in_array($qr_collection_children->get("ca_collections.type_id"), $va_non_linkable_collection_type_ids))){
									$vb_link_to_detail = false;
								}
								if(!$o_collections_config->get("always_link_to_detail") && !$vn_rel_object_count){
									$vb_link_to_detail = false;
								}

								if($vb_link_to_detail){
									print caDetailLink($this->request, $vs_icon." ".$qr_collection_children->get('ca_collections.preferred_labels')." ".(($o_collections_config->get("link_out_icon")) ? $o_collections_config->get("link_out_icon") : ""), '', 'ca_collections', $vn_child_collection_id).$vs_record_count;
								}else{
									print "<div class='listItem'>".$vs_icon." ".$qr_collection_children->get('ca_collections.preferred_labels').$vs_record_count."</div>";
								}
							}
							print "</div>";
						}
					}
?>
								</div><!-- end findingAidContainer -->
							</div><!-- end col -->
<?php
					if($vb_has_grandchildren){
						$vs_loading_html = caBusyIndicatorIcon($this->request).' '._t('Loading...');
?>
							<div id='collectionLoad' class='col-xs-12 col-sm-8 col-md-8 col-lg-8' role='region' aria-live='polite' aria-busy='false'>
								<i class='fa fa-arrow-left' aria-hidden='true'></i> Click a <?php print ucFirst($t_item->get("ca_collections.type_id", array('convertCodesToDisplayText' => true))); ?> container to the left to see its contents.
							</div>
							<script>
								jQuery(function($) {
									var $panel = $('#collectionLoad');
									var $links = $('#collectionsWrapper .openCollection');
									var initialPanelHtml = $panel.html();
									var baseUrl = window.location.pathname + window.location.search;
									var loadingHtml = <?php print json_encode($vs_loading_html); ?>;

									function collectionIdFromHash() {
										var match = window.location.hash.match(/^#collection-(\d+)$/);
										return match ? match[1] : null;
									}

									function linkForCollection(collectionId) {
										return $links.filter(function() {
											return String($(this).data('collection-id')) === String(collectionId);
										}).first();
									}

									function resetPanel() {
										$links.removeClass('active').attr('aria-expanded', 'false');
										$panel.attr('aria-busy', 'false').html(initialPanelHtml);
									}

									function loadCollection($link, addHistory) {
										if (!$link.length) {
											resetPanel();
											return;
										}

										var collectionId = String($link.data('collection-id'));
										var childListUrl = $link.data('child-list-url');
										if (addHistory && collectionIdFromHash() === collectionId && $link.hasClass('active')) {
											return;
										}

										$links.removeClass('active').attr('aria-expanded', 'false');
										$link.addClass('active').attr('aria-expanded', 'true');
										$panel.attr('aria-busy', 'true').html(loadingHtml).load(childListUrl, function(responseText, status) {
											$panel.attr('aria-busy', 'false');
											if (status === 'error') {
												if (addHistory) {
													window.location.assign($link.attr('href'));
												}else{
													resetPanel();
												}
											}
										});

										if (addHistory && window.history && window.history.pushState) {
											window.history.pushState(
												{ collectionHierarchyId: collectionId },
												'',
												baseUrl + '#collection-' + collectionId
											);
										}
									}

									$links.on('click', function(event) {
										if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || (event.which && event.which !== 1)) {
											return;
										}
										event.preventDefault();
										loadCollection($(this), true);
									});

									window.addEventListener('popstate', function() {
										var collectionId = collectionIdFromHash();
										if (collectionId) {
											loadCollection(linkForCollection(collectionId), false);
										}else{
											resetPanel();
										}
									});

									var initialCollectionId = collectionIdFromHash();
									if (initialCollectionId) {
										loadCollection(linkForCollection(initialCollectionId), false);
									}
								});
							</script>
<?php
					}
?>
						</div><!--end row -->	
						<div class='unit row'>
							<div class='col-sm-12 col-md-12 col-lg-12'><hr class='divide' style='margin-top:0px; margin-bottom:25px;'></hr></div>
						</div><!-- end row -->
				
					</div><!-- end col -->
				</div><!-- end row -->						
<?php
	}
?>
