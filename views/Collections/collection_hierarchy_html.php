<?php
	require_once __DIR__.'/hierarchy_helpers.php';
	$va_access_values = (array)$this->getVar("access_values");
	$vb_only_media = (tadlMediaPreference($this->request) === 'only');
	$o_collections_config = $this->getVar("collections_config");
	$t_item = $this->getVar("item");
	$va_exclude_collection_type_ids = $this->getVar("exclude_collection_type_ids");
	$va_non_linkable_collection_type_ids = $this->getVar("non_linkable_collection_type_ids");
	$va_collection_type_icons = $this->getVar("collection_type_icons");
	$vs_child_collection_sort = $o_collections_config->get("detail_child_collection_sort");
	if(!$vs_child_collection_sort) {
		$vs_child_collection_sort = "ca_collections.preferred_labels.name";
	}
	$vb_has_children = false;
	$vb_has_grandchildren = false;
	$va_grandchildren_by_collection = array();
	$va_collection_children = (array)$t_item->get('ca_collections.children.collection_id', array('returnAsArray' => true, 'checkAccess' => $va_access_values, 'sort' => $vs_child_collection_sort));
	$va_collection_media = $va_collection_children ? tadlCollectionContentsMediaPresence($this->request, $t_item) : [];
	$va_collection_counts = !$o_collections_config->get('always_link_to_detail') && $va_collection_children ? tadlCollectionContentsCounts($this->request, $t_item) : [];
	$va_collection_children = array_values(array_intersect($va_collection_children, array_keys($va_collection_media)));
	if ($vb_only_media) {
		$va_collection_children = array_values(array_filter($va_collection_children, static fn($id) => $va_collection_media[$id]));
	}
	$va_collection_children = tadlCollectionHierarchyIDs($va_collection_children, $vs_child_collection_sort);
	if($va_collection_children){
		$vb_has_children = true;
		$qr_collection_children = caMakeSearchResult("ca_collections", $va_collection_children);
		if($qr_collection_children->numHits()){
			while($qr_collection_children->nextHit()){
				$va_grand_child_ids = (array)$qr_collection_children->get("ca_collections.children.collection_id", array('returnAsArray' => true, 'checkAccess' => $va_access_values, 'sort' => $vs_child_collection_sort));
				$va_grand_child_ids = array_values(array_intersect($va_grand_child_ids, array_keys($va_collection_media)));
				if ($vb_only_media) {
					$va_grand_child_ids = array_values(array_filter($va_grand_child_ids, static fn($id) => $va_collection_media[$id]));
				}
				$va_grandchildren_by_collection[(int)$qr_collection_children->get("ca_collections.collection_id")] = $va_grand_child_ids;
				if($va_grand_child_ids){
					$vb_has_grandchildren = true;
				}
			}
		}
		$qr_collection_children->seek(0);
	}
	$branches = array_keys(array_filter($va_grandchildren_by_collection));
	$selected = (int)$this->request->getParameter('collection_branch', pInteger);
	if (!in_array($selected, $branches, true)) { $selected = $branches[0] ?? 0; }
	if($vb_has_children){
?>					
			<div class="text-right"><button type="button" class="btn btn-default showHide" aria-controls="collectionsWrapper" aria-expanded="<?= $o_collections_config->get('browser_closed') ? 'false' : 'true'; ?>" onclick="var panel = jQuery('#collectionsWrapper'); panel.toggle(); jQuery(this).attr('aria-expanded', panel.is(':visible') ? 'true' : 'false');"><?= _t('Show/Hide Collection Browser'); ?></button></div>
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
							$va_grand_child_ids = $va_grandchildren_by_collection[$vn_child_collection_id];
							$vn_rel_object_count = $va_collection_counts[$vn_child_collection_id] ?? 0;
							$vs_title = tadlCollectionHierarchyTitle($qr_collection_children->get('ca_collections.preferred_labels'), $va_collection_media[$vn_child_collection_id] ?? false);
							if(sizeof($va_grand_child_ids)){
								$branch_url = caNavUrl($this->request, '', 'Detail', 'collections/'.(int)$t_item->getPrimaryKey(), [
									'collection_view'=>'hierarchy', 'collection_branch'=>$vn_child_collection_id,
									'view'=>tadlDisplayView($this->request), 'per_page'=>tadlResultPageChoice($this->request),
									'sort'=>$this->request->getParameter('sort', pString) ?: 'Identifier', 'direction'=>$this->request->getParameter('direction', pString) === 'desc' ? 'desc' : 'asc', 's'=>0
								]).'#collectionHierarchy';
								print '<a href="'.htmlspecialchars($branch_url, ENT_QUOTES, 'UTF-8').'" data-collection-branch="'.$vn_child_collection_id.'" class="openCollection'.($selected === $vn_child_collection_id ? ' active' : '').'"'.($selected === $vn_child_collection_id ? ' aria-current="true"' : '').'>'.$vs_icon.' '.$vs_title.'</a>';
							}else{
								$vb_link_to_detail = true;
								if(is_array($va_non_linkable_collection_type_ids) && (in_array($qr_collection_children->get("ca_collections.type_id"), $va_non_linkable_collection_type_ids))){
									$vb_link_to_detail = false;
								}
								if(!$o_collections_config->get("always_link_to_detail") && !$vn_rel_object_count){
									$vb_link_to_detail = false;
								}

								if($vb_link_to_detail){
									print caDetailLink($this->request, $vs_icon." ".$vs_title." ".(($o_collections_config->get("link_out_icon")) ? $o_collections_config->get("link_out_icon") : ""), '', 'ca_collections', $vn_child_collection_id);
								}else{
									print "<div class='listItem'>".$vs_icon." ".$vs_title."</div>";
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
?>
							<div id="collectionLoad" class="col-xs-12 col-sm-8 col-md-8 col-lg-8" role="region" aria-label="<?= _t('Selected collection contents'); ?>">
								<?php
								$this->setVar('collection_id', $selected);
								$this->setVar('collection_media', $va_collection_media);
								$this->setVar('collection_counts', $va_collection_counts);
								print $this->render('Collections/child_list_html.php');
								?>
							</div>
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

<?php if ($vb_has_children && $vb_has_grandchildren): ?>
<script>
(function () {
    function restoreLegacyBranch() {
        var legacy = /^#collection-(\d+)$/.exec(window.location.hash);
        if (!legacy) { return; }
        var link = document.querySelector('#collectionHierarchy a[data-collection-branch="' + legacy[1] + '"]');
        if (link) { window.location.replace(link.href); }
    }
    window.addEventListener('hashchange', restoreLegacyBranch);
    restoreLegacyBranch();
}());
</script>
<?php endif; ?>
