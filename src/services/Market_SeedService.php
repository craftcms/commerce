<?php
namespace Craft;

use Market\Seed\Market_CountriesSeeder;
use Market\Seed\Market_InstallSeeder;
use Market\Seed\Market_StatesSeeder;

/**
 * Class Market_SeedService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
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