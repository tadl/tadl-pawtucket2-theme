<?php
/* ----------------------------------------------------------------------
 * themes/default/views/bundles/ca_occurrences_default_html.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2013-2022 Whirl-i-Gig
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

require_once(__DIR__.'/authority_detail_helpers.php');
$t_item = $this->getVar('item');
$authority_table = 'ca_occurrences';
$authority = tadlAuthorityDetailInfo($this->request, $t_item, $authority_table);
$authority_fields = '';
$authority_fields .= tadlDetailFirstAvailableField($this->request, $t_item, 'Dates', ['^ca_occurrences.occurrence_dates.dates_value', '^ca_occurrences.occurrence_dates', '^ca_occurrences.date.dates_value']);
$authority_fields .= tadlDetailField($this->request, $t_item, 'Description', '^ca_occurrences.description');
$authority_fields .= tadlDetailField($this->request, $t_item, 'Source of description', '^ca_occurrences.description_source');
include(__DIR__.'/authority_detail_html.php');
