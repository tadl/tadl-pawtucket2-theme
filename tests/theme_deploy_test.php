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
$workspace = $fixture.'/Theme workspace with spaces';
$checkout = $workspace.'/tadl';
foreach (['/support', '/conf', '/views'] as $directory) { mkdir($checkout.$directory, 0700, true); }
foreach (['/conf', '/views'] as $directory) { mkdir($workspace.'/kcl'.$directory, 0700, true); }
mkdir($fixture.'/bin', 0700);
mkdir($fixture.'/elsewhere', 0700);
copy(dirname(__DIR__).'/support/deploy-theme.sh', $checkout.'/support/deploy-theme.sh');
copy(dirname(__DIR__).'/support/deploy-themes.sh', $checkout.'/support/deploy-themes.sh');
file_put_contents($checkout.'/conf/app.conf', '# Synthetic theme configuration');
file_put_contents($workspace.'/kcl/conf/app.conf', '# Synthetic KCL configuration');
$mock = <<<'PHP'
#!/usr/bin/env php
<?php
$tool = basename($argv[0]);
file_put_contents(getenv('TADL_DEPLOY_TEST_TRACE'), json_encode([$tool, array_slice($argv, 1)]).PHP_EOL, FILE_APPEND);
if ($tool !== 'rsync') { exit(91); }
$calls = file(getenv('TADL_DEPLOY_TEST_TRACE'), FILE_IGNORE_NEW_LINES);
if (count($calls) === (int)getenv('TADL_DEPLOY_TEST_FAIL_CALL')) {
	exit((int)getenv('TADL_DEPLOY_TEST_TRANSFER_EXIT'));
}
exit(0);
PHP;
foreach (['rsync', 'ssh', 'apache2ctl', 'systemctl'] as $tool) {
	file_put_contents($fixture.'/bin/'.$tool, $mock);
	chmod($fixture.'/bin/'.$tool, 0700);
}
function runDeploy(array $arguments, array $failures = [], string $script = 'deploy-theme.sh') {
	global $fixture, $checkout;
	$trace = $fixture.'/trace.jsonl';
	file_put_contents($trace, '');
	$environment = array_replace(getenv(), [
		'PATH' => $fixture.'/bin:'.getenv('PATH'),
		'TADL_DEPLOY_TEST_TRACE' => $trace,
		'TADL_DEPLOY_TEST_TRANSFER_EXIT' => '0',
		'TADL_DEPLOY_TEST_FAIL_CALL' => '1',
	], $failures);
	$process = proc_open(array_merge(['bash', $checkout.'/support/'.$script], $arguments),
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
	deployCheck($status === 0 && str_contains($output, 'Deployment complete (application caches preserved; no service reload)'), 'Successful transfer must report completion without a reload.');
	deployCheck(array_column($calls, 0) === ['rsync'], 'Deployment must only copy; no SSH, cache operation or service reload.');
	$transfer = $calls[0][1];
	deployCheck(array_slice($transfer, -2) === [$checkout.'/', 'root@catalog.example.org:/var/www/pawtucket2/themes/tadl/'], 'Resolve source from the script location, preserving spaces and trailing-slash semantics.');
	deployCheck(array_slice($transfer, 0, -2) === ['--archive', '--verbose', '--itemize-changes', '--exclude=.git/', '--exclude=.gitignore', '--exclude=README.md', '--exclude=.DS_Store'], 'Preserve transfer exclusions without introducing deletion.');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org'], ['TADL_DEPLOY_TEST_TRANSFER_EXIT' => '23']);
	deployCheck($status === 23 && !str_contains($output, 'Deployment complete'), 'A failed transfer must preserve its exit status and omit completion.');
	deployCheck(array_column($calls, 0) === ['rsync'], 'A failed transfer must not run any later operation.');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org', 'kcl']);
	deployCheck($status === 0 && count($calls) === 1 && array_slice($calls[0][1], -2) === [$workspace.'/kcl/', 'root@catalog.example.org:/var/www/kcl/themes/kcl/'], 'KCL must copy only its own checkout into its own instance.');
	[$status, $output, $calls] = runDeploy(['--dry-run', 'root@catalog.example.org', 'tadl', 'kcl']);
	deployCheck($status === 0 && count($calls) === 2 && str_contains($output, 'Dry run complete') && !str_contains($output, 'Deployment complete'), 'Dry-run must cover the independent themes without reporting an actual deployment.');
	foreach ($calls as $call) {
		deployCheck($call[0] === 'rsync' && in_array('--dry-run', $call[1], true), 'Every dry-run transfer must carry the native rsync dry-run flag.');
	}
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org'], [], 'deploy-themes.sh');
	deployCheck($status === 0 && count($calls) === 2, 'Workspace dispatcher without a selector must deploy the two independent themes.');
	deployCheck(array_slice($calls[0][1], -2) === [$checkout.'/', 'root@catalog.example.org:/var/www/pawtucket2/themes/tadl/'] && array_slice($calls[1][1], -2) === [$workspace.'/kcl/', 'root@catalog.example.org:/var/www/kcl/themes/kcl/'], 'Workspace targets must remain independent and ordered.');
	foreach (['tadl', 'kcl'] as $theme) {
		[$status, $output, $calls] = runDeploy(['root@catalog.example.org', $theme], [], 'deploy-themes.sh');
		deployCheck($status === 0 && count($calls) === 1 && str_ends_with($calls[0][1][count($calls[0][1]) - 1], '/themes/'.$theme.'/'), 'An explicit workspace selector must transfer only the selected theme.');
	}
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org', '--dry-run', 'kcl'], [], 'deploy-themes.sh');
	deployCheck($status === 0 && count($calls) === 1 && in_array('--dry-run', $calls[0][1], true), 'Workspace dry-run must cover only the selected theme.');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org'], ['TADL_DEPLOY_TEST_TRANSFER_EXIT' => '23'], 'deploy-themes.sh');
	deployCheck($status === 23 && count($calls) === 1 && !str_contains($output, 'Deployment complete'), 'A failed first transfer must stop before the second theme.');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org'], ['TADL_DEPLOY_TEST_TRANSFER_EXIT' => '23', 'TADL_DEPLOY_TEST_FAIL_CALL' => '2'], 'deploy-themes.sh');
	deployCheck($status === 23 && count($calls) === 2 && !str_contains($output, 'Deployment complete'), 'A failed second transfer must retain its failure status and omit overall completion.');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org', 'kcl'], ['TADL_DEPLOY_TEST_TRANSFER_EXIT' => '23'], 'deploy-themes.sh');
	deployCheck($status === 23 && count($calls) === 1 && !str_contains($output, 'Deployment complete'), 'A failed KCL transfer must not report deployment completion.');
	foreach ([[], ['-oProxyCommand=unsafe'], ['root@catalog.example.org;unsafe'], ['root@catalog.example.org', 'unexpected']] as $invalid) {
		[$status, $output, $calls] = runDeploy($invalid);
		deployCheck($status !== 0 && !$calls && str_contains($output, 'Usage:'), 'Missing, unsafe and extra arguments must not start deployment.');
	}
	foreach ([['root@catalog.example.org', 'tadl', 'kcl'], ['root@catalog.example.org', '--unknown'], ['root@catalog.example.org', 'kcl;unsafe'], ['root@catalog.example.org', '../tadl']] as $invalid) {
		[$status, $output, $calls] = runDeploy($invalid, [], 'deploy-themes.sh');
		deployCheck($status !== 0 && !$calls && str_contains($output, 'Usage:'), 'The workspace dispatcher must reject invalid selectors before any transfer.');
	}
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org', 'tadl', 'tadl']);
	deployCheck($status !== 0 && !$calls && str_contains($output, 'more than once'), 'Duplicate themes must be rejected before any transfer.');
	[$status, $output, $calls] = runDeploy(['--help']);
	deployCheck($status === 0 && !$calls && str_contains($output, 'Usage:'), 'Help must never start deployment.');
	unlink($workspace.'/kcl/conf/app.conf');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org'], [], 'deploy-themes.sh');
	deployCheck($status !== 0 && !$calls && str_contains($output, 'incomplete'), 'Missing KCL source must stop both-theme deployment before TADL is copied.');
	file_put_contents($workspace.'/kcl/conf/app.conf', '# Synthetic KCL configuration');
	unlink($checkout.'/conf/app.conf');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org']);
	deployCheck($status !== 0 && !$calls && str_contains($output, 'incomplete'), 'An incomplete theme source must not start deployment.');
	[$status, $output, $calls] = runDeploy(['root@catalog.example.org', 'kcl']);
	deployCheck($status === 0 && count($calls) === 1 && array_slice($calls[0][1], -2) === [$workspace.'/kcl/', 'root@catalog.example.org:/var/www/kcl/themes/kcl/'], 'KCL deployment must not require a complete sibling TADL theme.');
	echo 'Theme deployment passed: '.$assertions.' assertions (synthetic commands only).'.PHP_EOL;
} finally {
	$entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($entries as $entry) {
		if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); }
		else { unlink($entry->getPathname()); }
	}
	rmdir($fixture);
}
