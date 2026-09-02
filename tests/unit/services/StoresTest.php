<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\Plugin;
use craft\commerce\records\SiteStore as SiteStoreRecord;
use craft\commerce\services\Stores;
use craft\events\SiteEvent;
use craft\services\ProjectConfig;
use craftcommercetests\fixtures\StoreFixture;
use Illuminate\Support\Collection;
use ReflectionClass;
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
     * While a project config apply (e.g. `craft up`) is in progress, the incoming sitestores
     * config is responsible for creating the mapping via handleChangedSiteStore(). If
     * afterSaveCraftSiteHandler() also created one here, it would assign the wrong (primary)
     * store and trigger an unwanted project config write.
     *
     * @return void
     */
    public function testAfterSaveCraftSiteHandlerSkipsWhileApplyingExternalChanges(): void
    {
        $site = Craft::$app->getSites()->getSiteById(1002);

        // Remove the fixture's existing mapping so the handler would normally recreate one.
        SiteStoreRecord::deleteAll(['siteId' => $site->id]);

        $this->_withApplyingExternalChanges(true, function() use ($site) {
            $this->service->afterSaveCraftSiteHandler(new SiteEvent(['site' => $site]));
        });

        self::assertNull(SiteStoreRecord::findOne(['siteId' => $site->id]));
    }

    /**
     * Outside of a project config apply, the handler should still create a mapping to the
     * primary store for a site that doesn't have one yet.
     *
     * @return void
     */
    public function testAfterSaveCraftSiteHandlerCreatesMappingOutsideOfApply(): void
    {
        $site = Craft::$app->getSites()->getSiteById(1002);

        SiteStoreRecord::deleteAll(['siteId' => $site->id]);

        $this->_withApplyingExternalChanges(false, function() use ($site) {
            $this->service->afterSaveCraftSiteHandler(new SiteEvent(['site' => $site]));
        });

        $siteStore = SiteStoreRecord::findOne(['siteId' => $site->id]);
        self::assertNotNull($siteStore);
        self::assertEquals($this->service->getPrimaryStore()->id, $siteStore->storeId);
    }

    /**
     * Runs $callback with ProjectConfig::getIsApplyingExternalChanges() forced to $isApplying,
     * restoring its original value afterwards.
     *
     * @param bool $isApplying
     * @param callable $callback
     * @return void
     */
    private function _withApplyingExternalChanges(bool $isApplying, callable $callback): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $prop = (new ReflectionClass(ProjectConfig::class))->getProperty('_applyingExternalChanges');
        $prop->setAccessible(true);
        $originalValue = $prop->getValue($projectConfig);

        $prop->setValue($projectConfig, $isApplying);

        try {
            $callback();
        } finally {
            $prop->setValue($projectConfig, $originalValue);
        }
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
