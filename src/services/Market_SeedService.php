<?php
namespace Craft;
use Market\Seed\Market_InstallSeeder;
use Market\Seed\Market_TestSeeder;

/**
 * Class Market_SeedService
 *
 * @package Craft
 */
class Market_SeedService extends BaseApplicationComponent
{
    /**
     * Default seeders
     */
    public function afterInstall()
    {
        $installSeeder = new Market_InstallSeeder;
        $installSeeder->seed();
    }

    public function testData()
    {
        $testSeeder = new Market_TestSeeder;
        $testSeeder->seed();
    }
} 