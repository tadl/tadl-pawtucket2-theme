<?php
/** Actual shell flow with synthetic executables; never connects to a server. */
error_reporting(E_ALL);
$assertions = 0;
function deployCheck($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
$fixture = sys_get_temp_dir().'/tadl-deploy-test-'.bin2hex(random_bytes(8));
mkdir($fixture, 0700);
$checkout = $fixture.'/Theme checkout with spaces';
foreach (['/support', '/conf', '/views'] as $directory) { mkdir($checkout.$directory, 0700, true); }
mkdir($fixture.'/bin', 0700);
mkdir($fixture.'/elsewhere', 0700);
copy(dirname(__DIR__).'/support/deploy-theme.sh', $checkout.'/support/deploy-theme.sh');
file_put_contents($checkout.'/conf/app.conf', '# Synthetic theme configuration');
$mock = <<<'PHP'
#!/usr/bin/env php
<?php
$tool = basename($argv[0]);
file_put_contents(getenv('TADL_DEPLOY_TEST_TRACE'), json_encode([$tool, array_slice($argv, 1)]).PHP_EOL, FILE_APPEND);
if ($tool === 'ssh') {
	// Run the actual remote command locally, with only synthetic Apache tools.
	$process = proc_open(['bash', '-c', $argv[2]], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
	exit(proc_close($process));
}
$option = match ($tool) {
	'rsync' => 'TADL_DEPLOY_TEST_TRANSFER_EXIT',
	'apache2ctl' => 'TADL_DEPLOY_TEST_CONFIG_EXIT',
	'systemctl' => 'TADL_DEPLOY_TEST_RELOAD_EXIT',
	default => throw new RuntimeException('Unexpected synthetic command.'),
};
exit((int)getenv($option));
PHP;
foreach (['rsync', 'ssh', 'apache2ctl', 'systemctl'] as $tool) {
	file_put_contents($fixture.'/bin/'.$tool, $mock);
	chmod($fixture.'/bin/'.$tool, 0700);
}
function runDeploy(array $arguments, array $failures = []) {
	global $fixture, $checkout;
	$trace = $fixture.'/trace.jsonl';
	file_put_contents($trace, '');
	$environment = array_replace(getenv(), [
		'PATH' => $fixture.'/bin:'.getenv('PATH'),
		'TADL_DEPLOY_TEST_TRACE' => $trace,
		'TADL_DEPLOY_TEST_TRANSFER_EXIT' => '0',
		'TADL_DEPLOY_TEST_CONFIG_EXIT' => '0',
		'TADL_DEPLOY_TEST_RELOAD_EXIT' => '0',
	], $failures);
	$process = proc_open(array_merge(['bash', $checkout.'/support/deploy-theme.sh'], $arguments),
		[0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixture.'/elsewhere', $environment);
	if (!is_resource($process)) { throw new RuntimeException('Could not start synthetic deployment.'); }
	$output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	$status = proc_close($process);
	$calls = array_map(static fn($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($trace, FILE_IGNORE_NEW_LINES));
	return [$status, $output, $calls];
}
try {
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org']);
	deployCheck($status === 0 && str_contains($output, 'Deployment complete (application caches preserved)'), 'Successful transfer and reload must report completion.');
	deployCheck(array_column($calls, 0) === ['rsync', 'ssh', 'apache2ctl', 'systemctl'], 'Deployment must copy, validate and reload only, with no cache operation.');
	$transfer = $calls[0][1];
	deployCheck(array_slice($transfer, -2) === [$checkout.'/', 'root@catalog.example.org:/var/www/pawtucket2/themes/tadl/'], 'Resolve source from the script location, preserving spaces and trailing-slash semantics.');
	deployCheck(array_slice($transfer, 0, -2) === ['--archive', '--verbose', '--itemize-changes', '--exclude=.git/', '--exclude=.gitignore', '--exclude=README.md', '--exclude=.DS_Store'], 'Preserve transfer exclusions without introducing deletion.');
	deployCheck($calls[1][1] === ['root@catalog.example.org', 'apache2ctl configtest && systemctl reload apache2'], 'Validate Apache before retaining the existing reload.');
	deployCheck($calls[2][1] === ['configtest'] && $calls[3][1] === ['reload', 'apache2'], 'Use the expected configuration check and service reload.');
	foreach ([
		'TRANSFER' => [23, ['rsync']],
		'CONFIG' => [1, ['rsync', 'ssh', 'apache2ctl']],
		'RELOAD' => [4, ['rsync', 'ssh', 'apache2ctl', 'systemctl']],
	] as $phase => [$failure, $expectedCalls]) {
		[$status, $output, $calls] = runDeploy(['root@catalog.example.org'], ['TADL_DEPLOY_TEST_'.$phase.'_EXIT' => (string)$failure]);
		deployCheck($status === $failure && !str_contains($output, 'Deployment complete'), 'A failed '.$phase.' step must preserve its exit status and omit completion.');
		deployCheck(array_column($calls, 0) === $expectedCalls, 'A failed '.$phase.' step must stop later operations.');
	}
	foreach ([[], ['-oProxyCommand=unsafe'], ['root@catalog.example.org;unsafe'], ['root@catalog.example.org', 'unexpected']] as $invalid) {
		[$status, $output, $calls] = runDeploy($invalid);
		deployCheck($status !== 0 && !$calls && str_contains($output, 'Usage:'), 'Missing, unsafe and extra arguments must not start deployment.');
	}
	[$status, $output, $calls] = runDeploy(['--help']);
	deployCheck($status === 0 && !$calls && str_contains($output, 'Usage:'), 'Help must never start deployment.');
	unlink($checkout.'/conf/app.conf');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org']);
	deployCheck($status !== 0 && !$calls && str_contains($output, 'incomplete'), 'An incomplete theme source must not start deployment.');
	echo 'Theme deployment passed: '.$assertions.' assertions (synthetic commands only).'.PHP_EOL;
} finally {
	$entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($entries as $entry) {
		if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); }
		else { unlink($entry->getPathname()); }
	}
	rmdir($fixture);
}
