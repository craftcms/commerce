<?php
namespace Craft;

use Commerce\Seed\Commerce_CountriesSeeder;
use Commerce\Seed\Commerce_InstallSeeder;
use Commerce\Seed\Commerce_StatesSeeder;

/**
 * Seed service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_SeedService extends BaseApplicationComponent
{
    /**
     * Default seeders
     */
    public function afterInstall()
    {
        (new Commerce_InstallSeeder)->seed();
        (new Commerce_CountriesSeeder)->seed();
        (new Commerce_StatesSeeder)->seed();
    }

}