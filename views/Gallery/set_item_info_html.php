<?php print "(".$this->getVar("set_item_num")."/".$this->getVar("set_num_items").")<br/>"; ?>
<H2><?php print $this->getVar("label"); ?></H2>

{{{<ifdef code="ca_objects.idno"><label>Identifer:</label>^ca_objects.idno<br/><br/></ifdef>}}}

{{{<ifdef code="ca_objects.description">^ca_objects.description<br/><br/></ifdef>}}}

<?php
	require_once(__DIR__.'/../Details/detail_field_helpers.php');
	print tadlDetailField($this->request, $this->getVar('object'), 'Related people', '<unit relativeTo="ca_entities" delimiter=", "><l>^ca_entities.preferred_labels.displayname</l></unit>');
?>


<?php print caDetailLink($this->request, _t("VIEW RECORD"), 'btn btn-default', $this->getVar("table"),  $this->getVar("row_id")); ?>
