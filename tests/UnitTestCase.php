<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

class UnitTestCase extends Orchestra
{
    use WithWorkbench;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        tap(app(ConfigRepository::class), function(ConfigRepository $config) {
            $config->set('database.default', 'sqlite');
            $config->set('database.connections.sqlite', array_merge(
                $config->get('database.connections.sqlite', []),
                [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                    'prefix' => '',
                ],
            ));
        });

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
    }
}
