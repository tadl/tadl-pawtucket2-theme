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
				<div class="tadl-panel tadl-intro-panel">
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
			<section class="tadl-resource-section">
				<div class="tadl-panel tadl-resource-panel">
					<div class="tadl-resource-header">
						<div>
							<h2 class="tadl-section-title">Start with the Right Path</h2>
							<p class="tadl-section-lead">The Local History Collection is a closed archive with digital collections, reference support, genealogy resources, and research guides for northern Michigan history.</p>
						</div>
					</div>
				</div>
			</section>

			<section class="tadl-home-section tadl-blog-section">
				<div class="tadl-section-header">
					<div>
						<h2 class="tadl-section-title">Recent Local History Writing</h2>
					</div>
					<a class="tadl-section-link" href="https://www.tadl.org/posts?field_bl_type_target_id%5B295%5D=295&amp;field_bl_tags_target_id%5B414%5D=414">View More</a>
				</div>
				<div class="tadl-blog-grid" id="tadl-recent-writing">
					<a class="tadl-blog-card" href="https://www.tadl.org/posts/traverse-city-psychiatrist-makes-history-shocking-career-dr-paul-h-wilcox">
						<span class="tadl-blog-image"><img src="https://www.tadl.org/sites/default/files/styles/post_gallery_teaser/public/2026-02/ArticleThumbnail.png?itok=t_f2Rgl3" alt="Traverse City Psychiatrist Makes History: The Shocking Career of Dr. Paul H. Wilcox"></span>
						<span class="tadl-blog-title">Traverse City Psychiatrist Makes History: The "Shocking" Career of Dr. Paul H. Wilcox</span>
					</a>
					<a class="tadl-blog-card" href="https://www.tadl.org/posts/dont-miss-northwestern-michigan-fair-first-half-twentieth-century">
						<span class="tadl-blog-image"><img src="https://www.tadl.org/sites/default/files/styles/post_gallery_teaser/public/2025-08/Northwestern%20Michigan%20Fair%20Square%20%281%29.png?itok=jOYf4UE8" alt="Don't Miss the Northwestern Michigan Fair: The First Half of the Twentieth Century"></span>
						<span class="tadl-blog-title">"Don't Miss the Northwestern Michigan Fair:" The First Half of the Twentieth Century</span>
					</a>
				</div>
			</section>

			<section class="tadl-home-section tadl-access-section">
				<div class="tadl-access-card">
					<h2 class="tadl-section-title">Online Anytime, In Person by Request</h2>
					<p>The Local History Digital Collection can be viewed at any time. In-person access to closed archival materials is available by request; TADL asks for 72 hours' notice so staff can prepare materials and secure appropriate research space.</p>
				</div>
				<div class="tadl-access-actions">
					<a class="tadl-feature-action" href="https://www.tadl.org/sites/default/files/2022-12/lhc_researcherform_RE.pdf">Research Request Form</a>
					<a class="tadl-feature-action tadl-feature-action-secondary" href="mailto:ask@tadl.org">Ask a Question</a>
				</div>
			</section>
		</div>
