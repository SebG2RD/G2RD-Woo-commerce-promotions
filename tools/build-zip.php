<?php
/**
 * Construit g2rd-woocommerce-promotions.zip (layout wp.org, sans outillage de dev).
 * Usage : php tools/build-zip.php
 */
if (PHP_SAPI !== 'cli') {
	exit(1);
}

$root = dirname(__DIR__);
$slug = 'g2rd-woocommerce-promotions';
$zip_path = $root . '/' . $slug . '.zip';

$exclude_dirs = [
	'.git',
	'.github',
	'.ci-refs',
	'.claude',
	'docs',
	'dist',
	'tools',
	'tests',
	'vendor',
	'node_modules',
	'.phpunit.cache',
	'.phpstan.cache',
];
$exclude_files = [
	'composer.json',
	'composer.lock',
	'phpcs.xml.dist',
	'phpcs-security.xml',
	'phpstan.neon.dist',
	'.gitignore',
	'.gitattributes',
	'README.md',
	'CHANGELOG.md',
	'CLAUDE.md',
	$slug . '.zip',
];
$exclude_suffixes = ['.zip', '.log', '.sh', '.ps1', '.pem', '.key', '.bak', '.swp'];

if (file_exists($zip_path)) {
	unlink($zip_path);
}

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
	fwrite(STDERR, "Impossible de créer l'archive.\n");
	exit(1);
}

$count = 0;
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
	/** @var SplFileInfo $file */
	$absolute = $file->getPathname();
	$relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
	$parts = explode('/', $relative);

	if (in_array($parts[0], $exclude_dirs, true)) {
		continue;
	}
	if (in_array(basename($relative), $exclude_files, true) && !str_contains($relative, '/')) {
		continue;
	}
	foreach ($exclude_suffixes as $suffix) {
		if (str_ends_with(strtolower($relative), $suffix)) {
			continue 2;
		}
	}
	if (str_starts_with(basename($relative), 'phpcs') || str_starts_with(basename($relative), 'phpstan')) {
		continue;
	}

	$zip->addFile($absolute, $slug . '/' . $relative);
	++$count;
}

$zip->close();
printf("%s : %d fichiers, %d octets\n", basename($zip_path), $count, filesize($zip_path));
