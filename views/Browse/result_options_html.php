			<div class="btn-group">
				<button type="button" class="tadl-results-action tadl-results-options" data-toggle="dropdown" aria-label="<?php print _t('Result options'); ?>" aria-haspopup="true" aria-expanded="false"><i class="fa fa-cog bGear" aria-hidden="true"></i><span class="tadl-results-action-label"><?php print _t('Options'); ?></span></button>
				<ul class="dropdown-menu" role="menu">
<?php
					if($vs_sort_control_type == 'dropdown'){
						if(is_array($va_sorts = $this->getVar('sortBy')) && sizeof($va_sorts)) {
							print "<li class='dropdown-header' role='menuitem'>"._t("Sort by:")."</li>\n";
							foreach($va_sorts as $vs_sort => $vs_sort_flds) {
								if ($vs_current_sort === $vs_sort) {
									print "<li role='menuitem'><a href='#'><em>".htmlspecialchars($vs_sort, ENT_QUOTES, 'UTF-8')."</em></a></li>\n";
								} else {
									print "<li role='menuitem'>".tadlBrowseResultLink($this->request, htmlspecialchars($vs_sort, ENT_QUOTES, 'UTF-8'), '', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'sort' => $vs_sort, 'direction' => $vs_sort_dir, '_advanced' => $vn_is_advanced ? 1 : 0))."</li>\n";
								}
							}
							print "<li class='divider' role='menuitem'></li>\n";
							print "<li class='dropdown-header' role='menuitem'>"._t("Sort order:")."</li>\n";
							print "<li role='menuitem'>".tadlBrowseResultLink($this->request, (($vs_sort_dir == 'asc') ? '<em>' : '')._t("Ascending").(($vs_sort_dir == 'asc') ? '</em>' : ''), '', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'sort' => $vs_current_sort, 'direction' => 'asc', '_advanced' => $vn_is_advanced ? 1 : 0))."</li>";
							print "<li role='menuitem'>".tadlBrowseResultLink($this->request, (($vs_sort_dir == 'desc') ? '<em>' : '')._t("Descending").(($vs_sort_dir == 'desc') ? '</em>' : ''), '', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'sort' => $vs_current_sort, 'direction' => 'desc', '_advanced' => $vn_is_advanced ? 1 : 0))."</li>";
						}
						
						if ((sizeof($va_criteria) > ($vb_is_search ? 1 : 0)) && is_array($va_sorts) && sizeof($va_sorts)) {
?>
						<li class="divider" role='menuitem'></li>
<?php
						}
					}
					if (sizeof($va_criteria) > ($vb_is_search ? 1 : 0)) {
						print "<li role='menuitem'>".caNavLink($this->request, _t("Start Over"), '', '*', '*', '*', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'clear' => 1, '_advanced' => $vn_is_advanced ? 1 : 0))."</li>";
					}
					if(($vs_media_preference === 'all') && is_array($va_export_formats) && sizeof($va_export_formats)){
						// Native exports bypass theme filtering; offer them in All items mode.
						print "<li class='divider' role='menuitem'></li>\n";
						print "<li class='dropdown-header' role='menuitem'>"._t("Download results as:")."</li>\n";
						foreach($va_export_formats as $va_export_format){
							print "<li class='".$va_export_format["code"]."' role='menuitem'>".caNavLink($this->request, $va_export_format["name"], "", "*", "*", "*", array("view" => "pdf", "download" => true, "export_format" => $va_export_format["code"], "key" => $vs_browse_key))."</li>";
						}
					}
?>
				</ul>
			</div><!-- end btn-group -->
