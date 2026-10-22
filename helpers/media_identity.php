<?php
/** Optional covering index; schema inspection is local to this request/connection. */
function tadlIndexedMediaIdentity($db) {
	static $checked = null;
	$checked ??= new WeakMap();
	if (isset($checked[$db])) { return $checked[$db]; }
	$checked[$db] = false;
	try {
		if (!method_exists($db, 'getConfig') || $db->getConfig()->get('db_type') !== 'mysqli') { return false; }
		$result = $db->query("SELECT s.COLUMN_NAME column_name, c.GENERATION_EXPRESSION expression, c.EXTRA extra
			FROM information_schema.STATISTICS s
			INNER JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
			WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME='ca_object_representations'
				AND s.INDEX_NAME='i_tadl_media_identity' AND s.IS_VISIBLE='YES'
			ORDER BY s.SEQ_IN_INDEX");
		if (!$result) { return false; }
		$columns = []; $expressions = [];
		while ($result->nextRow()) {
			$name = $result->get('column_name'); $columns[] = $name;
			if (str_contains((string)$result->get('extra'), 'VIRTUAL GENERATED')) {
				$expression = str_replace("\\'", "'", strtolower((string)$result->get('expression')));
				$expressions[$name] = preg_replace('/[\s`()]+|_(?:utf8mb4|utf8mb3|binary)/i', '', $expression);
			}
		}
		$checked[$db] = $columns === ['representation_id','deleted','access','tadl_has_media','tadl_media_fingerprint']
			&& ($expressions['tadl_media_fingerprint'] ?? null) === 'sha2media,256'
			&& ($expressions['tadl_has_media'] ?? null) === "mediaisnotnullandmedia<>''";
	} catch (Throwable $error) { /* Ordinary installations retain native descriptor hashing. */ }
	return $checked[$db];
}
