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
			<section class="tadl-resource-section">
				<div class="tadl-panel tadl-resource-panel">
					<div class="tadl-resource-header">
						<div>
							<div class="tadl-eyebrow">Local History Research</div>
							<h2 class="tadl-section-title">Start with the Right Path</h2>
							<p class="tadl-section-lead">The Local History Collection is a closed archive with digital collections, reference support, genealogy resources, and research guides for northern Michigan history.</p>
						</div>
						<div class="tadl-contact-card">
							<strong>Questions or research requests?</strong>
							<span>Contact the Reference Desk at <a href="tel:2319328502">231.932.8502</a> or <a href="mailto:ask@tadl.org">ask@tadl.org</a>.</span>
						</div>
					</div>

					<div class="tadl-resource-grid">
						<a class="tadl-resource-card tadl-resource-card-primary" href="<?= caNavUrl($this->request, '', 'Collections', 'Index'); ?>">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/DigitalCollection.jpg?itok=H8KvYS6y" alt="Vintage photo of a family at the beach"></span>
							<span class="tadl-resource-title">Digital Collection</span>
							<span class="tadl-resource-copy">Browse public collection guides, photographs, records, and descriptive metadata in this archive.</span>
						</a>
						<a class="tadl-resource-card" href="https://www.tadl.org/research?title=&amp;field_db_type_target_id%5B238%5D=238">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Family%20History.jpg?itok=oa1JgQIv" alt="Black and white photo of a family on a porch"></span>
							<span class="tadl-resource-title">Family History</span>
							<span class="tadl-resource-copy">Find genealogy and family-history resources available through TADL.</span>
						</a>
						<a class="tadl-resource-card" href="https://www.tadl.org/posts/newspapers">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/NewspaperThumnail_5_.jpg?itok=WcaJbKKq" alt="Newspaper layout room in black and white"></span>
							<span class="tadl-resource-title">Newspapers</span>
							<span class="tadl-resource-copy">Review local newspaper holdings and newspaper-related research options.</span>
						</a>
						<a class="tadl-resource-card" href="https://www.tadl.org/posts/house-history">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/House%20History.jpg?itok=Gs23FfSO" alt="Black and white photo of a house with a family outside"></span>
							<span class="tadl-resource-title">House History</span>
							<span class="tadl-resource-copy">Research the history of a house, property, or neighborhood.</span>
						</a>
						<a class="tadl-resource-card" href="https://www.tadl.org/posts/lhc-archives">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Archive.jpg?itok=C7V2W3h9" alt="Black and white photo of the Sixth Street Library card catalog area"></span>
							<span class="tadl-resource-title">Archives</span>
							<span class="tadl-resource-copy">Learn how archival collections are preserved, requested, and accessed.</span>
						</a>
						<a class="tadl-resource-card" href="https://www.tadl.org/posts/lhc-donations">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Donation.jpg?itok=y22Gas42" alt="Black and white photo of people loading by a truck"></span>
							<span class="tadl-resource-title">Donations</span>
							<span class="tadl-resource-copy">Review donation information for local-history materials.</span>
						</a>
						<a class="tadl-resource-card" href="https://www.tadl.org/posts/lhc-other-historical-resources">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Other%20Hsitorical%20Resources.jpg?itok=GdqXayvj" alt="Black and white photo of a library reading room"></span>
							<span class="tadl-resource-title">Other Historical Research</span>
							<span class="tadl-resource-copy">Explore related repositories and local-history tools.</span>
						</a>
						<a class="tadl-resource-card" href="https://www.tadl.org/lhcvolunteersthanks">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Volunteer.jpg?itok=t1Ahrs9S" alt="Black and white photo of people working in a production space"></span>
							<span class="tadl-resource-title">Volunteer</span>
							<span class="tadl-resource-copy">See volunteer-related information for the collection.</span>
						</a>
						<a class="tadl-resource-card" href="https://www.tadl.org/posts/lhc-additional-resources">
							<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Media.jpg?itok=4cmUoyzN" alt="Black and white photo of a man with a camera"></span>
							<span class="tadl-resource-title">Additional Resources</span>
							<span class="tadl-resource-copy">Follow related reading and support resources from TADL.</span>
						</a>
					</div>
				</div>
			</section>

			<section class="tadl-home-section tadl-blog-section">
				<div class="tadl-section-header">
					<div>
						<div class="tadl-eyebrow">On Our Blog</div>
						<h2 class="tadl-section-title">Recent Local History Writing</h2>
					</div>
					<a class="tadl-section-link" href="https://www.tadl.org/posts?field_bl_type_target_id%5B295%5D=295&amp;field_bl_tags_target_id%5B414%5D=414">View More</a>
				</div>
				<div class="tadl-blog-grid">
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
					<div class="tadl-eyebrow">Accessing Materials</div>
					<h2 class="tadl-section-title">Online Anytime, In Person by Request</h2>
					<p>The Local History Digital Collection can be viewed at any time. In-person access to closed archival materials is available by request; TADL asks for 72 hours' notice so staff can prepare materials and secure appropriate research space.</p>
				</div>
				<div class="tadl-access-actions">
					<a class="tadl-feature-action" href="https://www.tadl.org/sites/default/files/2022-12/lhc_researcherform_RE.pdf">Research Request Form</a>
					<a class="tadl-feature-action tadl-feature-action-secondary" href="mailto:ask@tadl.org">Ask a Question</a>
				</div>
			</section>
		</div>
