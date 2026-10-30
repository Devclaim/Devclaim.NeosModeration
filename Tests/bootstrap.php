<?php

declare(strict_types=1);

// the autoloader of the distribution: the package is in DistributionPackages/ or installed in Packages/Application/
$directory = dirname(__DIR__);
while (!is_file($directory . '/Packages/Libraries/autoload.php')) {
    if (dirname($directory) === $directory) {
        throw new RuntimeException('Run the tests inside a Neos distribution (no Packages/Libraries/autoload.php found).');
    }
    $directory = dirname($directory);
}
require $directory . '/Packages/Libraries/autoload.php';
spl_autoload_register(static function (string $className): void {
    $prefix = 'Devclaim\\NeosModeration\\Tests\\';
    if (!str_starts_with($className, $prefix)) {
        return;
    }
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
