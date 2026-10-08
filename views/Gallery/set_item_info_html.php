<?php
	require_once(__DIR__.'/../Details/detail_field_helpers.php');
	$object = $this->getVar('object');
	print '<p class="tadl-gallery-position">'.(int)$this->getVar('set_item_num').' / '.(int)$this->getVar('set_num_items').'</p>';
	print '<div class="detail"><h2>'.htmlspecialchars((string)$this->getVar('label'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</h2>';
	print tadlDetailField($this->request, $object, 'Identifier', '^ca_objects.idno');
	print tadlDetailField($this->request, $object, 'Description', '^ca_objects.description');
	print tadlDetailField($this->request, $object, 'Related people', '<unit relativeTo="ca_entities" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l> (^relationship_typename)</unit>');
	print '</div>';
?>


<?php print caDetailLink($this->request, _t("View record"), 'btn btn-default', $this->getVar("table"),  $this->getVar("row_id")); ?>
