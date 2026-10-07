<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\ApplicationBuilder;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Env;

use function Orchestra\Testbench\default_skeleton_path;

// Feature tests clean project config and runtime files in their application directory.
$basePath = Env::get('APP_ENV') === 'testing' ? default_skeleton_path() : dirname(__DIR__);

if ($basePath === dirname(__DIR__)) {
    // Testbench caches framework defaults while preparing its vendor symlink.
    Dotenv::create(Env::getRepository(), $basePath)->safeLoad();
}

$app = new Application($basePath);
// Keep Testbench's kernels so Laravel does not bootstrap the configuration a second time.
$app->singleton(Illuminate\Contracts\Console\Kernel::class, Orchestra\Testbench\Console\Kernel::class);
$app->singleton(Illuminate\Contracts\Http\Kernel::class, Orchestra\Testbench\Http\Kernel::class);

return new ApplicationBuilder($app)
    ->withProviders()
    ->withMiddleware(static fn(Middleware $middleware) => $middleware->trustProxies(at: ['127.0.0.1', '::1']))
    ->withCommands()
    ->create();
