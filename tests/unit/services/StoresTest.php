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
use craftcommercetests\fixtures\StoreFixture;
use Illuminate\Support\Collection;
use UnitTester;

/**
 * StoresTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class StoresTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Stores
     */
    protected Stores $service;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'stores' => [
                'class' => StoreFixture::class,
            ],
        ];
    }

    /**
     * @return void
     */
    public function testGetAllStores(): void
    {
        $stores = $this->service->getAllStores();

        self::assertCount(3, $stores);
        self::assertInstanceOf(Collection::class, $stores);
        self::assertEquals('primary', $stores->firstWhere('primary', true)->handle);
        self::assertCount(2, $stores->where('primary', false)->all());
    }

    /**
     * @param int $siteId
     * @param string|null $storeHandle
     * @return void
     * @dataProvider getStoreBySiteIdDataProvider
     */
    public function testGetStoreBySiteId(int $siteId, ?string $storeHandle): void
    {
        $store = $this->service->getStoreBySiteId($siteId);

        if ($storeHandle === null) {
            self::assertNull($store);
        } else {
            self::assertEquals($storeHandle, $store->handle);
        }
    }

    /**
     * @return array[]
     */
    public function getStoreBySiteIdDataProvider(): array
    {
        return [
            'us' => [1000, 'primary'],
            'nl' => [1001, 'euStore'],
            'uk' => [1002, 'ukStore'],
            'nonExistent' => [1003, null],
        ];
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
