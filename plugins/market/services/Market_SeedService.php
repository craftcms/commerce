<?php
namespace Craft;
use Market\Seed\Market_InstallSeeder;
use Market\Seed\Market_TestSeeder;
use Market\Seed\Market_CountriesSeeder;
use Market\Seed\Market_StatesSeeder;

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

        $countriesSeeder = new Market_CountriesSeeder;
        $countriesSeeder->seed();

        $statesSeeder = new Market_StatesSeeder;
        $statesSeeder->seed();
    }

    /**
     * Create Test Data when in dev mode
     */
    public function testData()
    {
        $testSeeder = new Market_TestSeeder;
        $testSeeder->seed();
    }
} 