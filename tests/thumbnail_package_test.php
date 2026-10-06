<?php
/** Export real allowlisted code and run its tests from an independent directory. */
error_reporting(E_ALL);
$temp = sys_get_temp_dir().'/thumbnail-package-'.bin2hex(random_bytes(8));
mkdir($temp, 0700);
register_shutdown_function(function () use ($temp) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); }
        else { unlink($file->getPathname()); }
    }
    rmdir($temp);
});
$checks = 0;
function packageCheck($condition, $message) {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function packageCommand(array $arguments, $directory) {
    $process = proc_open($arguments, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $directory);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $output, $error];
}
$source = dirname(__DIR__);
$output = $temp.'/portable package'; $archive = $temp.'/portable package.zip';
$export = ['python3', $source.'/support/package-thumbnail-faces.py', '--output-dir='.$output, '--archive='.$archive];
[$status, $stdout, $stderr] = packageCommand($export, $temp);
packageCheck($status === 0 && is_file($archive), 'Package export failed: '.$stderr);
$manifest = json_decode(file_get_contents($output.'/PROVENANCE.json'), true, 512, JSON_THROW_ON_ERROR);
packageCheck((bool)preg_match('/^[a-f0-9]{40}$/', $manifest['source_commit']), 'Missing source provenance');
packageCheck(count($manifest['sha256']) === 17, 'Unexpected package file list');
foreach ($manifest['sha256'] as $file => $hash) {
    packageCheck(hash_file('sha256', $output.'/'.$file) === $hash, 'Package content hash mismatch: '.$file);
}
foreach (['helpers/thumbnail_focus.php', 'support/detect-thumbnail-faces.php', 'support/thumbnail-faces/detect.py', 'assets/pawtucket/js/thumbnail-focus.js'] as $file) {
    packageCheck(file_get_contents($output.'/'.$file) === file_get_contents($source.'/'.$file), 'Runtime copy drifted: '.$file);
}
packageCheck(!is_dir($output.'/.git') && !is_file($output.'/setup.php') && !is_dir($output.'/app') && !is_dir($output.'/media'), 'Application/private trees leaked into distribution');
file_put_contents($output.'/keep.txt', 'Synthetic user file');
[$status] = packageCommand($export, $temp);
packageCheck($status !== 0 && file_get_contents($output.'/keep.txt') === 'Synthetic user file', 'Exporter replaced an existing directory');
[$status] = packageCommand(['python3', $source.'/support/package-thumbnail-faces.py', '--output-dir='.$temp.'/new', '--archive='.$archive], $temp);
packageCheck($status !== 0 && !is_dir($temp.'/new'), 'Existing ZIP was replaced or caused partial export');
[$status] = packageCommand(['python3', $source.'/support/package-thumbnail-faces.py', '--output-dir='.$temp.'/inside', '--archive='.$temp.'/inside/package.zip'], $temp);
packageCheck($status !== 0 && !is_dir($temp.'/inside'), 'ZIP inside package was accepted');
$zipCheck = <<<'PY'
import json, sys, zipfile
from pathlib import Path
root = Path(sys.argv[1])
manifest = json.loads((root/'PROVENANCE.json').read_text())
with zipfile.ZipFile(sys.argv[2]) as archive:
    expected = {'thumbnail-faces/' + name for name in manifest['sha256']} | {'thumbnail-faces/PROVENANCE.json'}
    assert set(archive.namelist()) == expected
    for name in expected:
        assert archive.read(name) == (root / name.removeprefix('thumbnail-faces/')).read_bytes()
PY;
[$status, $stdout, $stderr] = packageCommand(['python3', '-c', $zipCheck, $output, $archive], $temp);
packageCheck($status === 0, 'ZIP contains different/unexpected content: '.$stderr);
foreach (['thumbnail_focus_test.php','thumbnail_detector_test.php'] as $test) {
    [$status, $stdout, $stderr] = packageCommand([PHP_BINARY, $output.'/tests/'.$test], $temp);
    packageCheck($status === 0, 'Exported PHP suite failed outside theme: '.$stderr.$stdout);
}
[$status, $stdout, $stderr] = packageCommand(['python3', $output.'/tests/thumbnail_faces_test.py'], $temp);
packageCheck($status === 0, 'Exported Python tests failed: '.$stderr.$stdout);
echo "Thumbnail package passed: {$checks} assertions (allowlist, hashes, refusal to overwrite, ZIP and independent tests).\n";
