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
						<div class="tadl-eyebrow">Local History Research</div>
						<h2 class="tadl-section-title">Explore the Collection in Research Paths</h2>
						<p class="tadl-section-lead">TADL’s Local History work spans digital collections, genealogy, archives, newspapers, and reference support. These paths pull the most useful starting points into one front-page guide.</p>
					</div>
					<div class="tadl-feature-band">
						<div class="tadl-feature-story">
							<div class="tadl-feature-image">
								<img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/DigitalCollection.jpg?itok=H8KvYS6y" alt="Vintage photo of a family at the beach">
							</div>
							<div class="tadl-feature-kicker">Start Online</div>
							<h3 class="tadl-feature-title">Digital Collection</h3>
							<p class="tadl-feature-copy">Move directly into the public Local History digital collection for photographs, records, and descriptive metadata available online.</p>
							<a class="tadl-feature-action" href="https://localhistory.tadl.org/">Open the Digital Collection</a>
						</div>
						<div class="tadl-feature-stack">
							<a class="tadl-resource-card" href="https://www.tadl.org/research?title=&amp;field_db_type_target_id%5B238%5D=238">
								<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Family%20History.jpg?itok=oa1JgQIv" alt="Black and white photo of a family on a porch"></span>
								<span class="tadl-resource-title">Family History</span>
								<span class="tadl-resource-copy">Start with genealogy and family-history research resources.</span>
							</a>
							<a class="tadl-resource-card" href="https://www.tadl.org/posts/newspapers">
								<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/NewspaperThumnail_5_.jpg?itok=WcaJbKKq" alt="Newspaper layout room in black and white"></span>
								<span class="tadl-resource-title">Newspapers</span>
								<span class="tadl-resource-copy">Find newspaper holdings and related local-history resources.</span>
							</a>
							<a class="tadl-resource-card" href="https://www.tadl.org/posts/house-history">
								<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/House%20History.jpg?itok=Gs23FfSO" alt="Black and white photo of a house with a family outside"></span>
								<span class="tadl-resource-title">House History</span>
								<span class="tadl-resource-copy">Research the history of a house, property, or neighborhood.</span>
							</a>
						</div>
					</div>
					<div class="tadl-feature-band tadl-feature-band-reverse">
						<div class="tadl-feature-story">
							<div class="tadl-feature-image">
								<img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Archive.jpg?itok=C7V2W3h9" alt="Black and white photo of the Sixth Street Library card catalog area">
							</div>
							<div class="tadl-feature-kicker">In Person and Behind the Scenes</div>
							<h3 class="tadl-feature-title">Archives</h3>
							<p class="tadl-feature-copy">Learn how the archival collections work, where to begin with research requests, and how local-history materials are preserved and made accessible.</p>
							<a class="tadl-feature-action" href="https://www.tadl.org/posts/lhc-archives">Learn About the Archives</a>
						</div>
						<div class="tadl-feature-stack">
							<a class="tadl-resource-card" href="https://www.tadl.org/posts/lhc-other-historical-resources">
								<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Other%20Hsitorical%20Resources.jpg?itok=GdqXayvj" alt="Black and white photo of a library reading room"></span>
								<span class="tadl-resource-title">Other Historical Research</span>
								<span class="tadl-resource-copy">Explore related repositories and local-history tools.</span>
							</a>
							<a class="tadl-resource-card" href="https://www.tadl.org/posts/lhc-donations">
								<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Donation.jpg?itok=y22Gas42" alt="Black and white photo of people loading by a truck"></span>
								<span class="tadl-resource-title">Donations</span>
								<span class="tadl-resource-copy">Review donation information for local-history materials.</span>
							</a>
							<a class="tadl-resource-card" href="/lhcvolunteersthanks">
								<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Volunteer.jpg?itok=t1Ahrs9S" alt="Black and white photo of people working in a production space"></span>
								<span class="tadl-resource-title">Volunteer</span>
								<span class="tadl-resource-copy">See volunteer-related information for the collection.</span>
							</a>
						</div>
					</div>
					<div class="row tadl-resource-grid tadl-resource-grid-secondary">
						<div class="col-sm-6">
							<a class="tadl-resource-card tadl-resource-card-secondary" href="https://www.tadl.org/posts/lhc-additional-resources">
								<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Media.jpg?itok=4cmUoyzN" alt="Black and white photo of a man with a camera"></span>
								<span class="tadl-resource-title">Additional Resources</span>
								<span class="tadl-resource-copy">Follow related reading and support resources from TADL.</span>
							</a>
						</div>
						<div class="col-sm-6">
							<a class="tadl-resource-card tadl-resource-card-secondary" href="https://www.tadl.org/posts/lhc-archives">
								<span class="tadl-resource-image"><img src="https://www.tadl.org/sites/default/files/styles/linked_image/public/2025-05/Archive.jpg?itok=C7V2W3h9" alt="Black and white photo of archival shelves"></span>
								<span class="tadl-resource-title">Reference and Research Help</span>
								<span class="tadl-resource-copy">Connect archival access, local-history services, and staff guidance in one place.</span>
							</a>
						</div>
					</div>
				</div>
			</section>
		</div>
