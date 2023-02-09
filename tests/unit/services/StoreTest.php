<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\Plugin;
use craft\commerce\services\Stores;
use craft\elements\Address;
use UnitTester;

/**
 * StoreTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class StoreTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Store
     */
    protected Stores $service;

    public function testGetStore(): void
    {
        $store = $this->service->getPrimaryStore();

        self::assertInstanceOf(Address::class, $store->getSettings()->getLocationAddress());
        self::assertEquals('US', $store->getSettings()->getLocationAddress()->countryCode);
        self::assertEquals('Store', $store->getSettings()->getLocationAddress()->title);
    }

    public function testGetAllEnabledCountriesAsList(): void
    {
        $store = $this->service->getPrimaryStore();
        $store->getSettings()->setCountries(['US', 'AU', 'PH', 'GB']);
        $countriesAsList = $store->getSettings()->getCountriesList();

        self::assertIsArray($countriesAsList);
        self::assertArrayHasKey('US', $countriesAsList);
        self::assertSame('United States', $countriesAsList['US']);
        self::assertCount(4, $countriesAsList);
    }

    /**
     *
     */
    public function _before(): void
    {
        parent::_before();

        $this->service = Plugin::getInstance()->getStores();
    }
}
