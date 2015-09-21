<?php
namespace Craft;

use Commerce\Seed\Commerce_CountriesSeeder;
use Commerce\Seed\Commerce_InstallSeeder;
use Commerce\Seed\Commerce_StatesSeeder;

/**
 * Class Commerce_SeedService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_SeedService extends BaseApplicationComponent
{
	/**
	 * Default seeders
	 */
	public function afterInstall ()
	{
		(new Commerce_InstallSeeder)->seed();
		(new Commerce_CountriesSeeder)->seed();
		(new Commerce_StatesSeeder)->seed();
	}

} 