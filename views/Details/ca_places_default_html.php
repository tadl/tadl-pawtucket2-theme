<?php
/* ----------------------------------------------------------------------
 * themes/default/views/bundles/ca_placess_default_html.php : 
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
$authority_table = 'ca_places';
$authority = tadlAuthorityDetailInfo($this->request, $t_item, $authority_table);
$authority_fields = '';
$authority_fields .= tadlDetailField($this->request, $t_item, 'Alternate names', '<unit relativeTo="ca_places.nonpreferred_labels" delimiter="<br/>">^ca_places.nonpreferred_labels.name<ifdef code="ca_places.nonpreferred_labels.type_id"> (^ca_places.nonpreferred_labels.type_id)</ifdef></unit>');
$authority_fields .= tadlDetailField($this->request, $t_item, 'Place hierarchy', '^ca_places.hierarchy.preferred_labels.name%delimiter= &gt; ');
$authority_fields .= tadlDetailField($this->request, $t_item, 'Description', '^ca_places.description');
$authority_fields .= tadlDetailField($this->request, $t_item, 'Source', '^ca_places.source_id');
$authority_fields .= tadlDetailField($this->request, $t_item, 'Coordinates', '^ca_places.georeference');
$authority_fields .= tadlDetailField($this->request, $t_item, 'Vocabulary terms', '<unit relativeTo="ca_list_items" delimiter="<br/>"><l>^ca_list_items.preferred_labels.name_singular</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
include(__DIR__.'/authority_detail_html.php');
