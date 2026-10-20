<?php
// The index's set list has already been access/type filtered by GalleryController.
$id = (int)$this->getVar('featured_set_id');
$sets = (array)$this->getVar('sets');
if (!isset($sets[$id])) { return; }
$set = new ca_sets();
if (!$set->load($id) || $set->get('deleted') || (int)$set->get('access') !== 1 || !$set->isReadable($this->request)) { return; }
$access = caGetUserAccessValues($this->request);
$items = $set->getPrimaryItemsFromSets([$id], ['version'=>'large', 'checkAccess'=>$access]);
$item = isset($items[$id]) ? reset($items[$id]) : null;
if (!$item) {
	$items = $set->getFirstItemsFromSets([$id], ['version'=>'large', 'checkAccess'=>$access]);
	$item = isset($items[$id]) ? reset($items[$id]) : null;
}
if ($item && empty($item['representation_tag']) && Datamodel::getTableName($set->get('table_num')) !== 'ca_objects') {
	$record = Datamodel::getInstanceByTableNum($set->get('table_num'));
	if ($record && $record->load($item['row_id'])) {
		$item['representation_tag'] = $record->getWithTemplate('<unit relativeTo="ca_objects.related" length="1">^ca_object_representations.media.large</unit>', ['checkAccess'=>$access]);
	}
}
$this->setVar('set_id', $id);
$this->setVar('label', $set->getLabelForDisplay());
$this->setVar('description', $set->get(caGetGalleryConfig()->get('gallery_set_description_element_code'), ['delimiter'=>'<br/><br/>']));
$this->setVar('num_items', $set->getItemCount(['checkAccess'=>$access]));
$this->setVar('set_item', $item);
print $this->render('Gallery/set_info_html.php');
