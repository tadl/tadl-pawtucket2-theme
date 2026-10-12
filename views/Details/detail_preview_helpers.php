<?php

/** Clone display markup only; previews must not duplicate IDs or active widgets. */
function tadlDetailPreviewNode($node, &$remaining) {
	if ($remaining !== null && $remaining <= 0) { return null; }
	if ($node instanceof DOMText) {
		$text = $node->nodeValue;
		if ($remaining !== null && mb_strlen($text) > $remaining) {
			$text = mb_substr($text, 0, $remaining);
			if (($space = mb_strrpos($text, ' ')) !== false && $space > 0) { $text = mb_substr($text, 0, $space); }
			$remaining = 0;
		} elseif ($remaining !== null) { $remaining -= mb_strlen($text); }
		return $node->ownerDocument->createTextNode($text);
	}
	if (!($node instanceof DOMElement) || in_array(strtolower($node->tagName), [
		'script', 'style', 'template', 'iframe', 'object', 'embed', 'form', 'button',
		'input', 'select', 'textarea', 'img', 'svg', 'canvas', 'video', 'audio'
	], true)) { return null; }
	$copy = $node->cloneNode(false);
	$copy->removeAttribute('id');
	// Keep complete link labels; the relationship-list path also keeps their roles.
	if (strtolower($node->tagName) === 'a' && $remaining !== null) {
		$remaining -= mb_strlen($node->textContent);
		$childBudget = null;
	} else { $childBudget =& $remaining; }
	foreach ($node->childNodes as $child) {
		if ($childCopy = tadlDetailPreviewNode($child, $childBudget)) { $copy->appendChild($childCopy); }
	}
	return $copy;
}

function tadlDetailPreviewHTML($node) {
	$html = '';
	foreach ($node->childNodes as $child) { $html .= $node->ownerDocument->saveHTML($child); }
	return $html;
}

/** Preserve complete linked rows/list items, or a balanced formatted prose excerpt. */
function tadlDetailRichPreview($value, $entries = false) {
	$document = new DOMDocument('1.0', 'UTF-8');
	if (!$document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$value.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) { return null; }
	$body = $document->getElementsByTagName('body')->item(0);
	if (!$body) { return null; }
	$preview = $document->createElement('div');
	$rows = [[]]; $inline = true; $hasLinks = false;
	foreach ($body->childNodes as $node) {
		if ($node instanceof DOMElement && strtolower($node->tagName) === 'br') { $rows[] = []; continue; }
		if ($node instanceof DOMElement) {
			$inline = $inline && in_array(strtolower($node->tagName), ['a', 'span', 'strong', 'em', 'b', 'i', 'u', 's', 'small', 'sup', 'sub', 'code'], true);
			$hasLinks = $hasLinks || strtolower($node->tagName) === 'a' || $node->getElementsByTagName('a')->length > 0;
		}
		$rows[array_key_last($rows)][] = $node;
	}
	$rows = array_values(array_filter($rows, static fn($nodes) => preg_match('/[^\s\p{Z}\x{200B}\x{FEFF}]/u', join('', array_map(static fn($node) => $node->textContent, $nodes))) === 1));
	if ($entries && $inline && $hasLinks && count($rows) > 0) {
		if (count($rows) <= 5) { return ['complete' => true]; }
		$budget = null;
		foreach (array_slice($rows, 0, 5) as $index => $nodes) {
			if ($index) { $preview->appendChild($document->createElement('br')); }
			foreach ($nodes as $node) { if ($copy = tadlDetailPreviewNode($node, $budget)) { $preview->appendChild($copy); } }
		}
		return ['html' => tadlDetailPreviewHTML($preview), 'complete' => false];
	}
	$elements = array_values(array_filter(iterator_to_array($body->childNodes), static fn($node) => !($node instanceof DOMText) || trim($node->textContent) !== ''));
	if (count($elements) === 1 && $elements[0] instanceof DOMElement && in_array(strtolower($elements[0]->tagName), ['ul', 'ol'], true) && $elements[0]->getElementsByTagName('a')->length) {
		$items = array_values(array_filter(iterator_to_array($elements[0]->childNodes), static fn($node) => $node instanceof DOMElement && strtolower($node->tagName) === 'li'));
		if (count($items) <= 5) { return ['complete' => true]; }
		$list = $elements[0]->cloneNode(false); $list->removeAttribute('id'); $budget = null;
		foreach (array_slice($items, 0, 5) as $item) { $list->appendChild(tadlDetailPreviewNode($item, $budget)); }
		$preview->appendChild($list);
		return ['html' => tadlDetailPreviewHTML($preview), 'complete' => false];
	}
	$budget = 320;
	foreach ($body->childNodes as $node) { if ($copy = tadlDetailPreviewNode($node, $budget)) { $preview->appendChild($copy); } }
	if (!tadlDetailHasContent(tadlDetailPreviewHTML($preview))) { return null; }
	if ($preview->textContent === $body->textContent) { return ['complete' => true]; }
	$tail = $preview;
	while ($tail->lastChild) { $tail = $tail->lastChild; }
	$container = $tail instanceof DOMElement ? $tail : $tail->parentNode;
	while ($container !== $preview && !in_array(strtolower($container->nodeName), ['p', 'div', 'li', 'dt', 'dd', 'blockquote', 'pre', 'td', 'th'], true)) { $container = $container->parentNode; }
	$container->appendChild($document->createTextNode('…'));
	return ['html' => tadlDetailPreviewHTML($preview), 'complete' => false];
}
