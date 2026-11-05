<?php

/** Order positive numeric Omeka IDs, retaining empty or absent IDs at the end. */
function tadlOmekaIDOrder($db, array $ids, int $tableNum, string $direction = 'asc'): array {
	if (count($ids) < 2) { return $ids; }
	$descending = strtolower($direction) === 'desc';
	$compare = static fn(string $left, string $right) => (strlen($left) <=> strlen($right)) ?: strcmp($left, $right);
	$populated = [];
	foreach (array_chunk($ids, 500) as $chunk) {
		$rows = $db->query('SELECT a.row_id, e.datatype, v.value_integer1, v.value_longtext1
			FROM ca_attributes a
			INNER JOIN ca_attribute_values v ON v.attribute_id = a.attribute_id
			INNER JOIN ca_metadata_elements e ON e.element_id = v.element_id
			WHERE a.table_num = ? AND a.row_id IN (?) AND e.element_code = ?', [$tableNum, $chunk, 'omeka_id']);
		if (!$rows) { throw new RuntimeException('Unable to read Omeka IDs for sorting.'); }
		while ($rows->nextRow()) {
			$row = $rows->getRow();
			$value = match ((int)$row['datatype']) {
				11 => $row['value_integer1'],
				1 => $row['value_longtext1'],
				default => null
			};
			$value = trim((string)$value);
			if (!ctype_digit($value) || ($value = ltrim($value, '0')) === '') { continue; }
			$id = (int)$row['row_id'];
			if (!isset($populated[$id]) || ($descending ? $compare($value, $populated[$id]) > 0 : $compare($value, $populated[$id]) < 0)) {
				$populated[$id] = $value;
			}
		}
	}
	$withID = $withoutID = [];
	foreach ($ids as $id) {
		if (isset($populated[(int)$id])) { $withID[] = $id; }
		else { $withoutID[] = $id; }
	}
	// Repeated blank and populated attributes can put a numbered record out of order natively.
	// PHP 8's stable sort retains the existing order for equal numeric IDs.
	usort($withID, static function ($left, $right) use ($compare, $populated, $descending) {
		$order = $compare($populated[(int)$left], $populated[(int)$right]);
		return $descending ? -$order : $order;
	});
	return array_merge($withID, $withoutID);
}

/** Reorder only the selected object sort, before paging and result-context storage. */
function tadlOrderOmekaResult($result, $sortField, $direction = 'asc') {
	if ($sortField !== 'ca_objects.omeka_id' || !($result instanceof SearchResult) || $result->tableName() !== 'ca_objects') {
		return $result;
	}
	$ids = $result->getPrimaryKeyValues();
	$db = $result->getDb();
	$ordered = tadlOmekaIDOrder($db, $ids, $result->tableNum(), (string)$direction);
	if ($ordered !== $ids) {
		if (!class_exists('WLPlugSearchEngineBrowseEngine')) { require_once(__CA_LIB_DIR__.'/Browse/BrowseResult.php'); }
		$result->init(new WLPlugSearchEngineBrowseEngine($ordered, [], $result->tableNum()), [], ['db' => $db]);
	}
	return $result;
}
