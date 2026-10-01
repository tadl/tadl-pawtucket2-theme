<?php
/**
 * Run with php tests/object_detail_video_poster_test.php (PHP DOM and Node.js required).
 * Actual poster helper/viewer bundle; synthetic model, access and player boundaries; no DB.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pInteger', 1);
$GLOBALS['posterAssertions'] = 0;
function checkPoster($condition, $message) {
	$GLOBALS['posterAssertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caGetUserAccessValues($request) { return $request->access; }
class PosterRequest {
	public array $access = array(1);
	public function __construct(private string $controller = 'Detail') {}
	public function getController() { return $this->controller; }
	public function getParameter($name, $type, $source = null, $options = array()) { return ''; }
	public function parameterExists($name, $source = null) { return null; }
}
class PosterSubject {
	public int $metadataQueries = 0;
	public int $primaryQueries = 0;
	public function __construct(public array $rows, private string $table = 'ca_objects') {}
	public function tableName() { return $this->table; }
	public function getPrimaryKey() { return 42; }
	public function getRepresentations($versions, $versionSizes, $options) {
		$this->metadataQueries++;
		checkPoster($versions === array() && $versionSizes === null, 'Representation lookup requested derivatives or custom sizes.');
		checkPoster(($options['simple'] ?? null) === true && ($options['checkAccess'] ?? null) === array(1), 'Representation lookup omitted simple/access options.');
		$rows = array();
		foreach ($this->rows as $id => $row) {
			if (!($row['attached'] ?? true) || !($row['bundleAllowed'] ?? true) || !in_array($row['access'] ?? 1, $options['checkAccess'], true)) { continue; }
			if (($options['return_primary_only'] ?? false) && !($row['is_primary'] ?? false)) { continue; }
			$rows[$id] = $row;
		}
		return $rows;
	}
	public function getPrimaryRepresentation($versions, $versionSizes, $options) {
		$this->primaryQueries++;
		// Native API filters the primary relation through getRepresentations(),
		// then pops its row. It does not return an unfiltered primary model.
		$rows = $this->getRepresentations($versions, $versionSizes, array_merge($options, array('return_primary_only' => 1)));
		return array_pop($rows);
	}
}
class PosterRepresentation {
	private int $id = 0;
	public function load($id) {
		checkPoster(is_int($id) && $id > 0, 'Primary model load did not receive a validated representation ID.');
		Datamodel::$loads[] = $id;
		$this->id = isset(Datamodel::$records[$id]) && (Datamodel::$records[$id]['loadSucceeds'] ?? true) ? $id : 0;
		return $this->id > 0;
	}
	public function getPrimaryKey() { return Datamodel::$records[$this->id]['primaryKey'] ?? $this->id; }
	public function hasMediaVersion($field, $version) {
		checkPoster($field === 'media', 'Wrong primary media field.');
		return isset(Datamodel::$records[$this->id]['versions'][$version]);
	}
	public function getMediaInfo($field, $version = null, $key = null, $options = array()) {
		checkPoster($field === 'media', 'Wrong derivative info field.');
		$info = Datamodel::$records[$this->id]['versions'][$version]['info'] ?? null;
		return $key === null ? $info : ($info[$key] ?? null);
	}
	public function getMediaUrl($field, $version, $page = 1, $options = array()) {
		checkPoster($field === 'media', 'Wrong derivative URL field.');
		return Datamodel::$records[$this->id]['versions'][$version]['url'] ?? '';
	}
}
class Datamodel {
	public static array $records = array();
	public static array $loads = array();
	public static bool $available = true;
	public static function getInstance($table, $cached = false) {
		checkPoster($table === 'ca_object_representations' && $cached === true, 'Wrong native primary representation model lookup.');
		return self::$available ? new PosterRepresentation() : null;
	}
}
class PosterView {
	public function __construct(public PosterRequest $request, private array $values) {}
	public function getVar($name) { return $this->values[$name] ?? null; }
	public function render() {
		ob_start();
		try {
			include getenv('TADL_TEST_BUNDLE') ?: dirname(__DIR__).'/views/bundles/representation_viewer_html.php';
			return ob_get_clean();
		} catch (Throwable $error) { ob_end_clean(); throw $error; }
	}
}
require dirname(__DIR__).'/helpers/object_detail_media.php';

function posterSlide($id, $body) { return "<div data-representation_id='{$id}' class='repViewerContCont'><div class='repViewerCont'>{$body}</div></div>"; }
function posterVideo($id, $posterAttributes = 'poster="https://example.org/own-poster.jpg" data-poster="https://example.org/own-data-poster.jpg"') {
	return posterSlide($id, '<video id="media'.$id.'" '.$posterAttributes.' controls preload="metadata" class="native-player"><source src="https://example.org/synthetic.mp4" type="video/mp4"></video><script>window.syntheticPlayerInit('.$id.');</script>');
}
function posterRow($id, $mime, $extra = array()) { return array_replace(array('representation_id' => $id, 'mimetype' => $mime, 'is_primary' => $id === 101, 'media' => array()), $extra); }
function posterDerivative($url, $extra = array()) { return array('info' => array_replace(array('MIMETYPE' => 'image/jpeg', 'FILENAME' => 'synthetic-primary.jpg'), $extra), 'url' => $url); }
function posterDocument($html) {
	$document = new DOMDocument();
	$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	return $document;
}
function stripVideoPosterAttributes($html) {
	return preg_replace_callback('~<video\b(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>~i', function ($match) {
		return preg_replace('~\s+(?:poster|data-poster)\s*=\s*(?:\"[^\"]*\"|\'[^\']*\'|[^\s>]+)~i', '', $match[0]);
	}, $html);
}
$image = posterSlide(101, '<img src="https://example.org/primary.jpg" alt="Synthetic primary">');
$video = posterVideo(102);
$secondVideo = posterVideo(103, "poster='https://example.org/old-single.jpg' data-poster='https://example.org/old-single-data.jpg'");
$largeUrl = 'https://example.org/primary-large.jpg';
$mediumLargeUrl = 'https://example.org/primary-mediumlarge.jpg';
$mediumUrl = 'https://example.org/primary-medium.jpg';
$hostileUrl = 'https://example.org/poster.jpg?quote="collector\'s"&next=</script>&tag=<synthetic>';
$literalUrl = 'https://example.org/poster.jpg?group=$1&path=\\synthetic\\primary.jpg';
$fakeAttribute = '<img src="https://example.org/still.jpg" alt="Literal <video poster=\'fake-attribute\'>">';
$fakeScript = '<script>window.syntheticVideoText = "<video poster=\'fake-script\' src=\'https://example.org/fake.mp4\'></video>";</script>';
$fakeStyle = '<style>.synthetic::after { content: "<video poster=\'fake-style\'>"; }</style>';
$fakeComment = '<!-- <video poster="fake-comment" src="https://example.org/fake.mp4"></video> -->';
$fakeFragments = array($fakeAttribute, $fakeScript, $fakeStyle, $fakeComment);
$fakeOnlySlide = posterSlide(102, implode('', $fakeFragments));
$mixedSlide = posterSlide(102, implode('', $fakeFragments).'<video id="media102" poster="https://example.org/own-poster.jpg" data-poster="https://example.org/own-data-poster.jpg" controls preload="metadata" class="native-player"><source src="https://example.org/synthetic.mp4" type="video/mp4"></video><script>window.syntheticPlayerInit(102);</script>');
$baseRows = array(101 => posterRow(101, 'image/jpeg'), 102 => posterRow(102, 'video/mp4'), 103 => posterRow(103, 'video/webm'), 104 => posterRow(104, 'audio/mpeg'));
$baseVersions = array('large' => posterDerivative($largeUrl), 'mediumlarge' => posterDerivative($mediumLargeUrl), 'medium' => posterDerivative($mediumUrl));
$cases = array(
	array('name' => 'primary image replaces double-quoted video posters', 'bundle' => true),
	array('name' => 'multiple videos retain switching and single-quoted native wrappers', 'slides' => array($image, $video, $secondVideo), 'bundle' => true),
	array('name' => 'video without poster receives both attributes', 'slides' => array($image, posterVideo(102, '')), 'bundle' => true),
	array('name' => 'hostile URL round trips as an HTML attribute', 'versions' => array('large' => posterDerivative($hostileUrl)), 'expected' => $hostileUrl, 'bundle' => true),
	array('name' => 'literal dollar group and backslashes survive poster replacement', 'versions' => array('large' => posterDerivative($literalUrl)), 'expected' => $literalUrl, 'bundle' => true),
	array('name' => 'video-like script style comment and attribute text avoids lookups', 'slides' => array($image, $fakeOnlySlide), 'unchanged' => true, 'noQuery' => true),
	array('name' => 'actual video plus fake markup changes only actual poster', 'slides' => array($image, $mixedSlide), 'preserveFragments' => $fakeFragments, 'bundle' => true),
	array('name' => 'single representation bundle uses primary still poster', 'slides' => array($video), 'bundle' => true),
	array('name' => 'private primary image retains own poster', 'rowOverrides' => array(101 => posterRow(101, 'image/jpeg', array('access' => 0))), 'unchanged' => true),
	array('name' => 'unattached primary image retains own poster', 'rowOverrides' => array(101 => posterRow(101, 'image/jpeg', array('attached' => false))), 'unchanged' => true),
	array('name' => 'bundle-restricted primary image retains own poster', 'rowOverrides' => array(101 => posterRow(101, 'image/jpeg', array('bundleAllowed' => false))), 'unchanged' => true),
	array('name' => 'primary video retains its own poster', 'rowOverrides' => array(101 => posterRow(101, 'video/mp4')), 'unchanged' => true),
	array('name' => 'primary PDF retains own poster', 'rowOverrides' => array(101 => posterRow(101, 'application/pdf')), 'unchanged' => true),
	array('name' => 'missing primary relation retains own poster', 'rowOverrides' => array(101 => posterRow(101, 'image/jpeg', array('is_primary' => false))), 'unchanged' => true),
	array('name' => 'missing primary representation ID retains own poster', 'rowOverrides' => array(101 => array('mimetype' => 'image/jpeg', 'is_primary' => true)), 'unchanged' => true),
	array('name' => 'zero primary representation ID retains own poster', 'rowOverrides' => array(101 => posterRow(0, 'image/jpeg', array('is_primary' => true))), 'unchanged' => true),
	array('name' => 'primary model failed load retains own poster', 'modelOptions' => array('loadSucceeds' => false), 'unchanged' => true),
	array('name' => 'loaded model missing primary key retains own poster', 'modelOptions' => array('primaryKey' => 0), 'unchanged' => true),
	array('name' => 'unavailable native model retains own poster', 'modelAvailable' => false, 'unchanged' => true),
	array('name' => 'no primary derivatives retains own poster', 'versions' => array(), 'unchanged' => true),
	array('name' => 'only unsupported preview derivative retains own poster', 'versions' => array('preview' => posterDerivative($largeUrl)), 'unchanged' => true),
	array('name' => 'queued large derivative falls back to mediumlarge', 'versions' => array('large' => posterDerivative($largeUrl, array('QUEUED' => true)), 'mediumlarge' => posterDerivative($mediumLargeUrl)), 'expected' => $mediumLargeUrl),
	array('name' => 'icon large derivative falls back to medium', 'versions' => array('large' => posterDerivative($largeUrl, array('USE_ICON' => true)), 'medium' => posterDerivative($mediumUrl)), 'expected' => $mediumUrl),
	array('name' => 'nonimage derivative falls back to mediumlarge', 'versions' => array('large' => posterDerivative($largeUrl, array('MIMETYPE' => 'video/mp4')), 'mediumlarge' => posterDerivative($mediumLargeUrl)), 'expected' => $mediumLargeUrl),
	array('name' => 'missing filename and external URL falls back to medium', 'versions' => array('large' => posterDerivative($largeUrl, array('FILENAME' => '')), 'medium' => posterDerivative($mediumUrl)), 'expected' => $mediumUrl),
	array('name' => 'empty resolved large URL falls back to medium', 'versions' => array('large' => posterDerivative(''), 'medium' => posterDerivative($mediumUrl)), 'expected' => $mediumUrl),
	array('name' => 'external image derivative is usable', 'versions' => array('large' => posterDerivative($largeUrl, array('FILENAME' => '', 'EXTERNAL_URL' => $largeUrl)))),
	array('name' => 'queued derivatives only retain own poster', 'versions' => array('large' => posterDerivative($largeUrl, array('QUEUED' => true))), 'unchanged' => true),
	array('name' => 'icon derivatives only retain own poster', 'versions' => array('large' => posterDerivative($largeUrl, array('USE_ICON' => true))), 'unchanged' => true),
	array('name' => 'private rendered video retains own poster', 'rowOverrides' => array(102 => posterRow(102, 'video/mp4', array('access' => 0))), 'unchanged' => true, 'noPrimary' => true),
	array('name' => 'unattached rendered video retains own poster', 'rowOverrides' => array(102 => posterRow(102, 'video/mp4', array('attached' => false))), 'unchanged' => true, 'noPrimary' => true),
	array('name' => 'stale rendered video retains own poster', 'slides' => array($image, posterVideo(999)), 'unchanged' => true, 'noPrimary' => true),
	array('name' => 'audio rendered with a video tag retains own poster', 'slides' => array($image, posterVideo(104)), 'unchanged' => true, 'noPrimary' => true, 'bundle' => true),
	array('name' => 'image-only slides avoid lookups', 'slides' => array($image), 'unchanged' => true, 'noQuery' => true),
	array('name' => 'audio-only slides avoid lookups', 'slides' => array($image, posterSlide(104, '<audio controls src="https://example.org/audio.mp3"></audio>')), 'unchanged' => true, 'noQuery' => true),
	array('name' => 'other controller retains ordinary poster', 'controller' => 'Search', 'unchanged' => true, 'noQuery' => true),
	array('name' => 'other subject table retains ordinary poster', 'table' => 'ca_collections', 'unchanged' => true, 'noQuery' => true)
);
$nodeFixtures = array();
foreach ($cases as $case) {
	$request = new PosterRequest($case['controller'] ?? 'Detail');
	$subject = new PosterSubject(array_replace($baseRows, $case['rowOverrides'] ?? array()), $case['table'] ?? 'ca_objects');
	$slides = $case['slides'] ?? array($image, $video);
	$originalRows = $subject->rows;
	Datamodel::$records = array(101 => array_replace(array('versions' => $case['versions'] ?? $baseVersions), $case['modelOptions'] ?? array()));
	Datamodel::$loads = array();
	Datamodel::$available = $case['modelAvailable'] ?? true;
	$output = tadlObjectDetailVideoPosters($request, $subject, $slides);
	checkPoster(count($output) === count($slides) && array_keys($output) === array_keys($slides), $case['name'].': slide list changed.');
	checkPoster($subject->rows === $originalRows, $case['name'].': primary representation metadata changed.');
	foreach ($slides as $index => $original) {
		checkPoster(stripVideoPosterAttributes($output[$index]) === stripVideoPosterAttributes($original), $case['name'].': native markup or player script changed.');
		foreach ($case['preserveFragments'] ?? array() as $fragment) {
			checkPoster(substr_count($output[$index], $fragment) === substr_count($original, $fragment), $case['name'].': nonvideo script/style/comment/attribute content changed.');
		}
		if (($case['unchanged'] ?? false) || strpos($original, '<video') === false) {
			checkPoster($output[$index] === $original, $case['name'].': ordinary slide or own poster changed.');
			continue;
		}
		$videos = posterDocument($output[$index])->getElementsByTagName('video');
		checkPoster($videos->length === 1, $case['name'].': video markup broke.');
		$player = $videos->item(0);
		$expected = $case['expected'] ?? $largeUrl;
		checkPoster($player->getAttribute('poster') === $expected && $player->getAttribute('data-poster') === $expected, $case['name'].': poster URL failed to round trip.');
		checkPoster($player->hasAttribute('controls') && $player->getAttribute('preload') === 'metadata' && $player->attributes->length === 6, $case['name'].': native controls changed or unexpected attributes were injected.');
		checkPoster(strpos($output[$index], $hostileUrl) === false, $case['name'].': hostile attribute was not encoded.');
	}
	if ($case['noQuery'] ?? false) { checkPoster(!$subject->metadataQueries && !$subject->primaryQueries && !Datamodel::$loads, $case['name'].': avoidable media lookup.'); }
	if ($case['noPrimary'] ?? false) { checkPoster(!$subject->primaryQueries && !Datamodel::$loads, $case['name'].': nonvideo lookup reached primary media.'); }
	if (!($case['bundle'] ?? false)) { continue; }
	$ids = array_map(function ($slide) { preg_match("/data-representation_id='(\d+)'/", $slide, $match); return (int)$match[1]; }, $slides);
	$html = (new PosterView($request, array('representation_count' => count($slides), 'representation_ids' => $ids, 'display_annotations' => null, 'context' => 'synthetic', 't_subject' => $subject, 'slide_list' => $slides)))->render();
	preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $scripts);
	checkPoster(count($scripts[1]) === 1, $case['name'].': emitted bundle scripts broke.');
	if (count($slides) === 1) {
		checkPoster(strpos($html, $output[0]) !== false, $case['name'].': single-slide bundle did not use decorated media.');
		$player = posterDocument($html)->getElementsByTagName('video')->item(0);
		checkPoster($player->getAttribute('poster') === ($case['expected'] ?? $largeUrl), $case['name'].': single-slide poster differs.');
	}
	$nodeFixtures[] = array('name' => $case['name'], 'scripts' => $scripts[1], 'slides' => $output, 'ids' => $ids, 'expectedIndex' => count($slides) > 1 && !($case['unchanged'] ?? false) ? 1 : 0);
}

$nodeCode = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const fixtures = JSON.parse(fs.readFileSync(0, 'utf8'));
let assertions = 0;
for (const fixture of fixtures) {
    let displayed = null;
    let activeThumb = null;
    const initializedPlayers = [];
    const handlers = new Map();
    let context;
    const rootId = html => Number(html.match(/^\s*<[^>]*data-representation_id='(\d+)'/)[1]);
    const jquery = selector => {
        if (typeof selector !== 'string') return { ready(callback) { callback(); } };
        if (selector === '#repViewerItemDisplay') return {
            html(value) {
                displayed = value;
                for (const match of value.matchAll(/<script\b[^>]*>(.*?)<\/script\s*>/gs)) new vm.Script(match[1]).runInContext(context, { timeout: 1000 });
            },
            children() { return { attr() { return String(rootId(displayed)); } }; }
        };
        if (selector === '.repThumb') return { removeClass() { activeThumb = null; } };
        const byRepresentation = selector.match(/^\.repThumb\[data-representation_id="(\d+)"\]$/);
        if (byRepresentation) return { attr() { return 'repThumb_' + fixture.ids.indexOf(Number(byRepresentation[1])); } };
        const thumb = selector.match(/^#repThumb_(\d+)$/);
        if (thumb) return { attr() { return String(fixture.ids[Number(thumb[1])]); }, addClass() { activeThumb = Number(thumb[1]); } };
        if (selector === '#detailRepNavPrev' || selector === '#detailRepNavNext') return { on(event, callback) { handlers.set(selector, callback); } };
        throw new Error('Unexpected selector: ' + selector);
    };
    context = vm.createContext({ document: {}, jQuery: jquery, window: { syntheticPlayerInit(id) { initializedPlayers.push(id); } } });
    for (const script of fixture.scripts) new vm.Script(script, { filename: fixture.name }).runInContext(context, { timeout: 1000 });
    if (fixture.slides.length === 1) {
        assert.deepEqual(initializedPlayers, [fixture.ids[0]], fixture.name + ': single video initializer changed');
        assertions++;
        continue;
    }
    const checkSlide = index => {
        assert.equal(displayed, fixture.slides[index], fixture.name + ': selected slide differs');
        assert.equal(activeThumb, index, fixture.name + ': thumbnail ID/single quote matching broke');
        assert.equal(vm.runInContext('index', context), index, fixture.name + ': viewer index differs');
        assertions += 3;
    };
    checkSlide(fixture.expectedIndex);
    assert.equal(vm.runInContext('JSON.stringify(slide_list)', context), JSON.stringify(fixture.slides), fixture.name + ': decoration changed order or scripts');
    assertions++;
    for (let index = 0; index < fixture.ids.length; index++) {
        const countBefore = initializedPlayers.length;
        context.setItem(index);
        checkSlide(index);
        assert.equal(initializedPlayers.length, countBefore + (fixture.slides[index].includes('<video') ? 1 : 0), fixture.name + ': native player script did not initialize after thumbnail selection');
        assertions++;
    }
    handlers.get('#detailRepNavPrev')({ preventDefault() {} });
    checkSlide(Math.max(0, fixture.ids.length - 2));
    handlers.get('#detailRepNavNext')({ preventDefault() {} });
    checkSlide(fixture.ids.length - 1);
}
process.stdout.write(JSON.stringify({ assertions }));
JS;
$process = proc_open(array(getenv('TADL_TEST_NODE') ?: 'node', '-e', $nodeCode), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
if (!is_resource($process)) { throw new RuntimeException('Node.js is required for rendered poster regression.'); }
fwrite($pipes[0], json_encode($nodeFixtures, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
checkPoster($status === 0, "Rendered poster viewer failed in Node.js:\n".$stderr);
$nodeResult = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
echo json_encode(array('status' => 'passed', 'assertions' => $GLOBALS['posterAssertions'] + $nodeResult['assertions'], 'posterCases' => count($cases), 'renderedBundleCases' => count($nodeFixtures), 'dependencies' => 'actual helper/bundle; synthetic access-filtered primary/model/player boundaries; PHP DOM and Node.js'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
