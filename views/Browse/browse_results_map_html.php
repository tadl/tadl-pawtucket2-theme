<?php
/* ----------------------------------------------------------------------
 * views/Browse/browse_results_images_html.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2014 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
 
	$qr_res 			= $this->getVar('result');				// browse results (subclass of SearchResult)
	$va_facets 			= $this->getVar('facets');				// array of available browse facets
	$va_criteria 		= $this->getVar('criteria');			// array of browse criteria
	$vs_browse_key 		= $this->getVar('key');					// cache key for current browse
	$va_access_values 	= $this->getVar('access_values');		// list of access values for this user
	$vn_hits_per_block 	= (int)$this->getVar('hits_per_block');	// number of hits to display per block
	$vn_start		 	= (int)$this->getVar('start');			// offset to seek to before outputting results
	
	$va_views			= $this->getVar('views');
	$vs_current_view	= $this->getVar('view');
	$va_view_icons		= $this->getVar('viewIcons');
	$vs_current_sort	= $this->getVar('sort');
	
	$t_instance			= $this->getVar('t_instance');
	$vs_table 			= $this->getVar('table');
	$vs_pk				= $this->getVar('primaryKey');
	$o_config = $this->getVar("config");	
	
	$va_options			= $this->getVar('options');
	$vs_extended_info_template = caGetOption('extendedInformationTemplate', $va_options, null);

	$vb_ajax			= (bool)$this->request->isAjax();
	
	
	if (tadlMediaPreference($this->request) === 'only' && in_array($vs_table, array('ca_objects', 'ca_collections'), true)) {
		// The controller built its map before the theme filtered the full result.
		$va_map_info = $va_views['map'];
		$va_display = $va_map_info['display'];
		$o_map = new GeographicMap(caGetOption('width', $va_map_info, '100%'), caGetOption('height', $va_map_info, '600px'));
		$qr_res->seek(0);
		$o_map->mapFrom($qr_res, $va_map_info['data'], array(
			'renderLabelAsLink' => false,
			'request' => $this->request,
			'labelTemplate' => caGetOption('labelTemplate', $va_display, null),
			'contentTemplate' => caGetOption('contentTemplate', $va_display, null),
			'excludeRelationshipTypes' => caGetOption('excludeRelationshipTypes', $va_display, null)
		));
		print $o_map->render('HTML', array(
			'labelTemplate' => caGetOption('labelTemplate', $va_display, null),
			'circle' => 0,
			'cluster' => caGetOption('cluster', $va_map_info, false),
			'minZoomLevel' => caGetOption('minZoomLevel', $va_map_info, 2),
			'maxZoomLevel' => caGetOption('maxZoomLevel', $va_map_info, 12),
			'noWrap' => caGetOption('noWrap', $va_map_info, null),
			'request' => $this->request
		));
	} else {
		print $this->getVar('map');
	}
