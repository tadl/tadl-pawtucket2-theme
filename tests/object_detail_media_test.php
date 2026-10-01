<?php
/**
 * Run with php tests/object_detail_media_test.php (PHP and Node.js required).
 * Actual theme helper and bundle, synthetic request/model/jQuery boundaries, no DB.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pInteger', 1);
$GLOBALS['objectMediaAssertions'] = 0;
function checkObjectMedia($condition, $message) {
	$GLOBALS['objectMediaAssertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text, ...$values) {
	foreach ($values as $i => $value) { $text = str_replace('%'.($i + 1), (string)$value, $text); }
	return $text;
}
function caGetUserAccessValues($request) { return $request->access; }
class ObjectMediaRequest {
	public array $access = array(1);
	public function __construct(private array $params = array(), private string $controller = 'Detail') {}
	public function getController() { return $this->controller; }
	public function getParameter($name, $type = null, $source = null, $options = array()) { return $this->params[$name] ?? ''; }
	public function parameterExists($name, $source = null) { return $this->params[$name] ?? null; }
	public function getParameters($sources = null) { return $this->params; }
}
class ObjectMediaSubject {
	public int $metadataQueries = 0;
	public int $primaryRepresentation = 101;
	public function __construct(public array $representations, private string $table = 'ca_objects') {}
	public function tableName() { return $this->table; }
	public function getPrimaryKey() { return 42; }
	public function getRepresentations($versions, $versionSizes, $options) {
		$this->metadataQueries++;
		checkObjectMedia($versions === array() && $versionSizes === null, 'Metadata query requested media derivatives or custom version sizes.');
		checkObjectMedia(($options['simple'] ?? null) === true && ($options['checkAccess'] ?? null) === array(1), 'Metadata query omitted native simple/access options.');
		$rows = array();
		foreach ($this->representations as $id => $row) {
			if (($row['attached'] ?? true) && in_array($row['access'] ?? 1, $options['checkAccess'], true)) { $rows[$id] = $row; }
		}
		return $rows;
	}
}
class ObjectMediaView {
	public function __construct(public ObjectMediaRequest $request, private array $values) {}
	public function getVar($name) { return $this->values[$name] ?? null; }
	public function render() {
		ob_start();
		try {
			include getenv('TADL_TEST_BUNDLE') ?: dirname(__DIR__).'/views/bundles/representation_viewer_html.php';
			return ob_get_clean();
		} catch (Throwable $error) {
			ob_end_clean();
			throw $error;
		}
	}
}
require dirname(__DIR__).'/helpers/object_detail_media.php';

function objectMediaSlide($id, $tag) { return "<div data-representation_id='{$id}' class='repViewerContCont'><div class='repViewerCont'>{$tag}</div></div>"; }
function objectMediaRow($id, $mime, $extra = array()) {
	return array_replace(array('representation_id' => $id, 'mimetype' => $mime, 'media' => array('original' => array('FILENAME' => 'synthetic-media'))), $extra);
}
$image = objectMediaSlide(101, '<img src="https://example.org/still.jpg" alt="Synthetic still">');
$video = objectMediaSlide(102, '<video controls><source src="https://example.org/movie.mp4" type="video/mp4"></video>');
$secondVideo = objectMediaSlide(103, '<video controls src="https://example.org/other.webm"></video>');
$audio = objectMediaSlide(104, '<audio controls src="https://example.org/audio.mp3"></audio>');
$pdf = objectMediaSlide(105, '<object data="https://example.org/document.pdf" type="application/pdf"></object>');
$secondImage = objectMediaSlide(106, '<img src="https://example.org/other.jpg" alt="Other synthetic still">');
$videoAsImage = objectMediaSlide(102, '<img src="https://example.org/video-poster.jpg" alt="Missing playback derivative">');
$rows = array(
	101 => objectMediaRow(101, 'image/jpeg'),
	102 => objectMediaRow(102, 'video/mp4'),
	103 => objectMediaRow(103, 'video/webm'),
	104 => objectMediaRow(104, 'audio/mpeg'),
	105 => objectMediaRow(105, 'application/pdf'),
	106 => objectMediaRow(106, 'image/jpeg')
);
$fixtures = array(
	array('name' => 'primary still then playable video', 'slides' => array($image, $video), 'expected' => 1),
	array('name' => 'representation ID order differs from slides', 'slides' => array($image, $video), 'ids' => array(102, 101), 'expected' => 1),
	array('name' => 'double-quoted native wrappers support thumbnail selection', 'slides' => array($image, '<div data-representation_id="102"><video src="https://example.org/movie.mp4"></video></div>'), 'expected' => 1),
	array('name' => 'native rank collision retains thumbnail callback for one rendered slide', 'slides' => array($image), 'ids' => array(101, 102), 'advertisedCount' => 2, 'expected' => 0, 'noQuery' => true),
	array('name' => 'rendered count wins over underreported native count', 'slides' => array($image, $secondImage), 'advertisedCount' => 1, 'expected' => 0, 'noQuery' => true),
	array('name' => 'first rendered video wins regardless of metadata order', 'slides' => array($image, $secondVideo, $video), 'expected' => 1),
	array('name' => 'already first video remains first', 'slides' => array($video, $image), 'expected' => 0),
	array('name' => 'audio PDF and image keep primary still', 'slides' => array($image, $audio, $pdf, $secondImage), 'expected' => 0, 'noQuery' => true),
	array('name' => 'only still images keep primary', 'slides' => array($image, $secondImage), 'expected' => 0, 'noQuery' => true),
	array('name' => 'missing video derivative falls back to still', 'slides' => array($image, $videoAsImage), 'expected' => 0, 'noQuery' => true),
	array('name' => 'video player with no source keeps primary still', 'slides' => array($image, objectMediaSlide(102, '<video controls></video>')), 'expected' => 0, 'noQuery' => true),
	array('name' => 'empty video source keeps primary still', 'slides' => array($image, objectMediaSlide(102, '<video controls src=""><source src=""></video>')), 'expected' => 0, 'noQuery' => true),
	array('name' => 'whitespace video source keeps primary still', 'slides' => array($image, objectMediaSlide(102, '<video controls src=" "><source src="   "></video>')), 'expected' => 0, 'noQuery' => true),
	array('name' => 'direct video source opens video', 'slides' => array($image, $secondVideo), 'expected' => 1),
	array('name' => 'private rendered video cannot become default', 'slides' => array($image, $video), 'rowOverrides' => array(102 => objectMediaRow(102, 'video/mp4', array('access' => 0))), 'expected' => 0),
	array('name' => 'unattached rendered video cannot become default', 'slides' => array($image, $video), 'rowOverrides' => array(102 => objectMediaRow(102, 'video/mp4', array('attached' => false))), 'expected' => 0),
	array('name' => 'stale rendered representation ID', 'slides' => array($image, objectMediaSlide(999, '<video src="https://example.org/stale.mp4"></video>')), 'expected' => 0),
	array('name' => 'rendered video tag with image metadata', 'slides' => array($image, $video), 'rowOverrides' => array(102 => objectMediaRow(102, 'image/jpeg')), 'expected' => 0),
	array('name' => 'private first video skips to next accessible video', 'slides' => array($image, $video, $secondVideo), 'rowOverrides' => array(102 => objectMediaRow(102, 'video/mp4', array('access' => 0))), 'expected' => 2),
	array('name' => 'explicit still wins over automatic video', 'slides' => array($image, $video), 'params' => array('representation_id' => 101), 'expected' => 0),
	array('name' => 'explicit secondary still wins over automatic video', 'slides' => array($image, $video, $secondImage), 'params' => array('representation_id' => 106), 'expected' => 2),
	array('name' => 'explicit video wins over earlier video', 'slides' => array($image, $video, $secondVideo), 'params' => array('representation_id' => 103), 'expected' => 2),
	array('name' => 'explicit audio is honored', 'slides' => array($image, $audio, $video), 'params' => array('representation_id' => 104), 'expected' => 1),
	array('name' => 'explicit missing derivative is honored as rendered still', 'slides' => array($image, $videoAsImage), 'params' => array('representation_id' => 102), 'expected' => 1),
	array('name' => 'explicit private ID falls back to primary', 'slides' => array($image, $video, $secondVideo), 'params' => array('representation_id' => 102), 'rowOverrides' => array(102 => objectMediaRow(102, 'video/mp4', array('access' => 0))), 'expected' => 0),
	array('name' => 'explicit unattached ID falls back to primary', 'slides' => array($image, $video), 'params' => array('representation_id' => 102), 'rowOverrides' => array(102 => objectMediaRow(102, 'video/mp4', array('attached' => false))), 'expected' => 0),
	array('name' => 'explicit attached but unrendered ID falls back to primary', 'slides' => array($image, $video), 'params' => array('representation_id' => 103), 'expected' => 0),
	array('name' => 'explicit nonexistent ID falls back to primary', 'slides' => array($image, $video), 'params' => array('representation_id' => 999), 'expected' => 0),
	array('name' => 'explicit zero ID falls back to primary', 'slides' => array($image, $video), 'params' => array('representation_id' => 0), 'expected' => 0),
	array('name' => 'other controller retains ordinary viewer behavior', 'slides' => array($image, $video), 'controller' => 'Search', 'expected' => 0, 'noQuery' => true),
	array('name' => 'other subject table retains ordinary viewer behavior', 'slides' => array($image, $video), 'table' => 'ca_collections', 'expected' => 0, 'noQuery' => true)
);
$nodeFixtures = array();
foreach ($fixtures as $fixture) {
	$request = new ObjectMediaRequest($fixture['params'] ?? array(), $fixture['controller'] ?? 'Detail');
	$subject = new ObjectMediaSubject(array_replace($rows, $fixture['rowOverrides'] ?? array()), $fixture['table'] ?? 'ca_objects');
	$originalRows = $subject->representations;
	$ids = $fixture['ids'] ?? array_map(function ($slide) {
		preg_match('/data-representation_id=[\'\"](\d+)[\'\"]/', $slide, $match);
		return (int)$match[1];
	}, $fixture['slides']);
	$html = (new ObjectMediaView($request, array(
		'representation_count' => $fixture['advertisedCount'] ?? count($fixture['slides']), 'representation_ids' => $ids,
		't_subject' => $subject, 'slide_list' => $fixture['slides'], 'display_annotations' => null, 'context' => 'synthetic'
	)))->render();
	preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $scripts);
	checkObjectMedia(count($scripts[1]) === 1, $fixture['name'].': viewer script did not render.');
	checkObjectMedia((strpos($html, 'id="detailRepNavPrev"') !== false) === (count($fixture['slides']) > 1), $fixture['name'].': arrows do not match rendered media count.');
	checkObjectMedia((strpos($html, 'id="detailRepCounter"') !== false) === (count($fixture['slides']) > 1), $fixture['name'].': counter does not match rendered media count.');
	checkObjectMedia($subject->primaryRepresentation === 101 && $subject->representations === $originalRows, $fixture['name'].': primary representation or metadata changed.');
	if ($fixture['noQuery'] ?? false) { checkObjectMedia($subject->metadataQueries === 0, $fixture['name'].': avoidable metadata query.'); }
	$nodeFixtures[] = array('name' => $fixture['name'], 'scripts' => $scripts[1], 'slides' => $fixture['slides'], 'thumbIds' => $ids, 'expected' => $fixture['expected']);
}

$nodeCode = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const fixtures = JSON.parse(fs.readFileSync(0, 'utf8'));
let assertions = 0;
for (const fixture of fixtures) {
    let displayedHtml = null;
    let activeThumb = null;
    const currentThumbs = new Map();
    const disabled = new Map();
    let counter = null;
    let pausedSinceReplacement = 0;
    let pauseCalls = 0;
    let replacements = 0;
    const hasControls = fixture.slides.length > 1;
    const handlers = new Map();
    const rootId = html => html.match(/^\s*<[^>]*\bdata-representation_id=['"](\d+)['"]/)[1];
    const mediaCount = html => html ? [...html.matchAll(/<(?:video|audio)\b/gi)].length : 0;
    const thumbnails = {
        removeClass(name) { assert.equal(name, 'active'); activeThumb = null; return thumbnails; },
        removeAttr(name) { assert.equal(name, 'aria-current'); currentThumbs.clear(); return thumbnails; },
        filter(selector) {
            const match = selector.match(/^\[data-representation_id="(\d+)"\]$/);
            assert.ok(match, 'Unexpected thumbnail filter: ' + selector);
            const thumbIndex = fixture.thumbIds.indexOf(Number(match[1]));
            const selected = {
                addClass(name) { assert.equal(name, 'active'); if (thumbIndex !== -1) activeThumb = thumbIndex; return selected; },
                attr(name, value) { assert.equal(name, 'aria-current'); if (thumbIndex !== -1) currentThumbs.set(thumbIndex, value); return selected; }
            };
            return selected;
        }
    };
    const jquery = selector => {
        if (typeof selector !== 'string') return { ready(callback) { callback(); } };
        if (selector === '#repViewerItemDisplay video, #repViewerItemDisplay audio') return {
            each(callback) {
                for (let i = 0; i < mediaCount(displayedHtml); i++) callback.call({ pause() { pausedSinceReplacement++; pauseCalls++; } });
            }
        };
        if (selector === '#repViewerItemDisplay') return {
            html(value) {
                assert.equal(pausedSinceReplacement, mediaCount(displayedHtml), fixture.name + ': outgoing media was not paused before replacement');
                assertions++;
                pausedSinceReplacement = 0;
                replacements++;
                displayedHtml = value;
            },
            children() { return { attr(name) { assert.equal(name, 'data-representation_id'); return rootId(displayedHtml); } }; }
        };
        if (selector === '#detailRepresentationThumbnails .repThumb') return thumbnails;
        if (selector === '.repThumb') return { removeClass(name) { assert.equal(name, 'active'); activeThumb = null; } };
        const representation = selector.match(/^\.repThumb\[data-representation_id="(\d+)"\]$/);
        if (representation) return { attr(name) { assert.equal(name, 'id'); return 'repThumb_' + fixture.thumbIds.indexOf(Number(representation[1])); } };
        const thumb = selector.match(/^#repThumb_(\d+)$/);
        if (thumb) return {
            attr(name) { assert.equal(name, 'data-representation_id'); return String(fixture.thumbIds[Number(thumb[1])]); },
            addClass(name) { assert.equal(name, 'active'); activeThumb = Number(thumb[1]); }
        };
        if (selector === '#detailRepNavPrev' || selector === '#detailRepNavNext') return {
            on(event, callback) { assert.equal(event, 'click'); handlers.set(selector, callback); },
            prop(name, value) { assert.equal(name, 'disabled'); if (hasControls) disabled.set(selector, value); }
        };
        if (selector === '#detailRepCounter') return { text(value) { if (hasControls) counter = value; } };
        throw new Error('Unexpected jQuery selector: ' + selector);
    };
    const context = vm.createContext({ document: {}, jQuery: jquery });
    for (const script of fixture.scripts) new vm.Script(script, { filename: fixture.name }).runInContext(context, { timeout: 1000 });
    const checkSelected = (index, message) => {
        assert.equal(displayedHtml, fixture.slides[index], fixture.name + ': ' + message);
        assert.equal(activeThumb, fixture.thumbIds.indexOf(Number(rootId(fixture.slides[index]))), fixture.name + ': active thumbnail differs');
        assert.equal(vm.runInContext('index', context), index, fixture.name + ': current slide index differs');
        assertions += 3;
        assert.equal(currentThumbs.get(activeThumb), 'true', fixture.name + ': selected thumbnail lacks aria-current');
        assert.equal(currentThumbs.size, 1, fixture.name + ': inactive thumbnail retained aria-current');
        if (hasControls) {
            assert.equal(disabled.get('#detailRepNavPrev'), index === 0, fixture.name + ': previous boundary state differs');
            assert.equal(disabled.get('#detailRepNavNext'), index === fixture.slides.length - 1, fixture.name + ': next boundary state differs');
            assert.equal(counter, `Media ${index + 1} of ${fixture.slides.length}`, fixture.name + ': counter differs');
            assertions += 3;
        } else {
            assert.equal(counter, null, fixture.name + ': one rendered slide should not gain a counter');
            assert.equal(disabled.size, 0, fixture.name + ': one rendered slide should not gain arrows');
            assertions += 2;
        }
        assertions += 2;
    };
    checkSelected(fixture.expected, 'wrong initial media');
    assert.equal(vm.runInContext('JSON.stringify(slide_list)', context), JSON.stringify(fixture.slides), fixture.name + ': slide ordering changed');
    assertions++;
    for (let i = fixture.expected; i < fixture.slides.length; i++) {
        let prevented = false;
        const countBefore = replacements;
        handlers.get('#detailRepNavNext')({ preventDefault() { prevented = true; } });
        checkSelected(Math.min(i + 1, fixture.slides.length - 1), 'Next control failed');
        assert.equal(prevented, true);
        if (i === fixture.slides.length - 1) { assert.equal(replacements, countBefore, fixture.name + ': next boundary needlessly replaced media'); assertions++; }
        assertions++;
    }
    for (let i = fixture.slides.length - 1; i >= 0; i--) {
        let prevented = false;
        const countBefore = replacements;
        handlers.get('#detailRepNavPrev')({ preventDefault() { prevented = true; } });
        checkSelected(Math.max(i - 1, 0), 'Previous control failed');
        assert.equal(prevented, true);
        if (i === 0) { assert.equal(replacements, countBefore, fixture.name + ': previous boundary needlessly replaced media'); assertions++; }
        assertions++;
    }
    fixture.thumbIds.forEach((id, thumbIndex) => {
        const previousIndex = vm.runInContext('index', context);
        const countBefore = replacements;
        assert.equal(context.setItem(thumbIndex), false, fixture.name + ': thumbnail handler stopped preventing navigation');
        const selectedIndex = fixture.slides.findIndex(slide => rootId(slide) === String(id));
        checkSelected(selectedIndex === -1 ? previousIndex : selectedIndex, 'Thumbnail control failed');
        if (selectedIndex === -1) { assert.equal(replacements, countBefore, fixture.name + ': unrendered thumbnail changed the displayed media'); assertions++; }
        assertions++;
    });
    if (fixture.slides.some(slide => mediaCount(slide))) { assert.ok(pauseCalls > 0, fixture.name + ': outgoing audio/video pause path was not exercised'); assertions++; }
}
process.stdout.write(JSON.stringify({ assertions }));
JS;
$process = proc_open(array(getenv('TADL_TEST_NODE') ?: 'node', '-e', $nodeCode), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
if (!is_resource($process)) { throw new RuntimeException('Node.js is required for the rendered viewer regression.'); }
fwrite($pipes[0], json_encode($nodeFixtures, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
checkObjectMedia($status === 0, "Rendered media viewer failed in Node.js:\n".$stderr);
$nodeResult = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

foreach (array(
	array('name' => 'ordinary empty viewer keeps placeholder', 'slides' => array()),
	array('name' => 'ordinary single still renders directly', 'slides' => array($image)),
	array('name' => 'ordinary single video renders directly', 'slides' => array($video))
) as $fixture) {
	$subject = new ObjectMediaSubject($rows);
	$html = (new ObjectMediaView(new ObjectMediaRequest(), array(
		'representation_count' => count($fixture['slides']), 'representation_ids' => count($fixture['slides']) ? array(101) : array(),
		't_subject' => $subject, 'slide_list' => $fixture['slides'], 'display_annotations' => null, 'context' => 'synthetic'
	)))->render();
	checkObjectMedia(strpos($html, 'id="detailRepNavPrev"') === false && strpos($html, 'id="detailRepNavNext"') === false, $fixture['name'].': unnecessary navigation buttons.');
	checkObjectMedia(strpos($html, 'id="detailRepCounter"') === false && strpos($html, 'function setItem') === false, $fixture['name'].': unnecessary counter or multi-slide callback.');
	checkObjectMedia(strpos($html, count($fixture['slides']) ? $fixture['slides'][0] : '{{{placeholder}}}') !== false, $fixture['name'].': direct media or placeholder output changed.');
	checkObjectMedia($subject->metadataQueries === 0, $fixture['name'].': avoidable metadata query.');
}

// Root wrapper IDs are authoritative, including double-quoted native markup.
// Additional helper boundaries remain independent from thumbnail navigation.
foreach (array(
	array('name' => 'double quoted rendered root ID', 'slides' => array($image, '<div data-representation_id="102"><video src="https://example.org/movie.mp4"></video></div>'), 'expected' => 1),
	array('name' => 'nested ID cannot impersonate rendered root', 'slides' => array($image, "<div class='repViewerContCont'><div data-representation_id='102'><video src='https://example.org/movie.mp4'></video></div></div>"), 'expected' => 0),
	array('name' => 'no rendered media', 'slides' => array(), 'expected' => 0),
	array('name' => 'only one rendered video', 'slides' => array($video), 'expected' => 0)
) as $fixture) {
	$subject = new ObjectMediaSubject($rows);
	checkObjectMedia(tadlObjectDetailInitialMediaIndex(new ObjectMediaRequest(), $subject, $fixture['slides']) === $fixture['expected'], $fixture['name'].': incorrect helper selection.');
}

echo json_encode(array('status' => 'passed', 'assertions' => $GLOBALS['objectMediaAssertions'] + $nodeResult['assertions'], 'viewerCases' => count($fixtures), 'bundle' => 'actual representation viewer', 'dependencies' => 'synthetic access-filtered representations/request/jQuery; Node.js syntax and execution'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
