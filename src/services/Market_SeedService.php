<?php
namespace Craft;

use Market\Seed\Market_CountriesSeeder;
use Market\Seed\Market_InstallSeeder;
use Market\Seed\Market_StatesSeeder;
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
        (new Market_InstallSeeder)->seed();
        (new Market_CountriesSeeder)->seed();
        (new Market_StatesSeeder)->seed();
    }

} 