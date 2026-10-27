<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/Packages/Libraries/autoload.php';
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
