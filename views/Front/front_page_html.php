<?php
/** ---------------------------------------------------------------------
 * themes/default/Front/front_page_html : Front page of site 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2013 Whirl-i-Gig
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
 * @package CollectiveAccess
 * @subpackage Core
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */
		print $this->render("Front/featured_set_slideshow_html.php");
?>
	<div class="tadl-front-copy">
		<div class="tadl-front-grid">
			<div class="tadl-panel">
				<div class="tadl-eyebrow">Digital Collections</div>
				<h1 class="tadl-hero-title">Discover the stories, places, and people that shaped our region.</h1>
				<p class="tadl-lead">Explore photographs, documents, and community memory preserved by Traverse Area District Library. Browse archival collections, search across materials, and connect records back to the history of northern Michigan.</p>
			</div>
			<div class="tadl-quicklinks">
				<a class="tadl-quicklink" href="<?= caNavUrl($this->request, '', 'Collections', 'Index'); ?>">
					Browse Collections
					<small>Move through collection guides and archival groupings.</small>
				</a>
				<a class="tadl-quicklink" href="<?= caNavUrl($this->request, '', 'Search', 'advanced/objects'); ?>">
					Advanced Search
					<small>Search by title, creator, identifier, and more.</small>
				</a>
				<a class="tadl-quicklink" href="<?= caNavUrl($this->request, '', 'Gallery', 'Index'); ?>">
					Featured Galleries
					<small>Start with curated highlights from the collection.</small>
				</a>
			</div>
		</div>
	</div>
