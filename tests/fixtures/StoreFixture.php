<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\Store;
use craft\commerce\models\StoreSettings as StoreSettingsModel;
use craft\commerce\Plugin;
use craft\commerce\records\SiteStore;
use craft\commerce\services\Stores;
use craft\commerce\services\StoreSettings;
use craft\db\Query;
use craft\elements\Address;
use craft\test\DbFixtureTrait;

/**
 * Class StoreFixture
 * @package craftcommercetests\fixtures
 * @since 5.0.0
 */
class StoreFixture extends BaseModelFixture
{
    use DbFixtureTrait;
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/stores.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Store::class;

    /**
     * @var string|Stores
     */
    public $service = 'stores';

    /**
     * @var string|StoreSettings
     */
    private $_settingsService = 'storeSettings';

    /**
     * @inheritdoc
     */
    public string $saveMethod = 'saveStore';

    /**
     * @inheritdoc
     */
    public string $deleteMethod = 'deleteStoreById';

    /**
     * @var array
     */
    private array $_storeSettings = [];

    /**
     * @var array
     */
    private array $_storeSites = [];

    /**
     * @inheritdoc
     */
    public $depends = [SitesFixture::class];

    public function init(): void
    {
        $this->service = Plugin::getInstance()->get($this->service);
        $this->_settingsService = Plugin::getInstance()->get($this->_settingsService);

        parent::init();
    }

    /**
     * @inheritdoc
     */
    protected function getData(): array
    {
        $data = parent::getData();

        foreach ($data as $key => &$store) {
            $this->_storeSettings[$key] = $store['settings'];
            unset($store['settings']);

            if (isset($store['_sites'])) {
                $this->_storeSites[$key] = $store['_sites'];
                unset($store['_sites']);
            }

            if (isset($store['_load']) && $store['_load'] === false) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function load(): void
    {
        parent::load();
        $this->data['primary'] = ['id' => 1];

        // Save store settings
        foreach ($this->_storeSettings as $key => &$settings) {
            $storeSettings = $this->_settingsService->getStoreSettingsById($this->data[$key]['id']) ?? new StoreSettingsModel();
            $storeSettings->id = $this->data[$key]['id'];

            if (!empty($settings['_storeLocationAddress'])) {
                $address = Craft::createObject(Address::class, [
                    'config' => ['attributes' => $settings['_storeLocationAddress']],
                ]);

                Craft::$app->getElements()->saveElement($address, false);
                $settings['_storeLocationAddressId'] = $address->id;
            }

            $storeSettings->setCountries($settings['countries'] ?? []);
            $this->_settingsService->saveStoreSettings($storeSettings);
        }

        foreach ($this->_storeSites as $key => $siteIds) {
            foreach ($siteIds as $siteId) {
                $uid = (new Query())
                    ->select('uid')
                    ->from(Table::SITESTORES)
                    ->where(['siteId' => $siteId])
                    ->limit(1)
                    ->scalar();

                if (!$uid) {
                    $siteStore = new \craft\commerce\models\SiteStore();
                } else {
                    $siteStore = $this->service->getAllSiteStores()->firstWhere('uid', $uid);
                }

                $siteStore->siteId = $siteId;
                $siteStore->storeId = $this->data[$key]['id'];
                $this->service->saveSiteStore($siteStore);
            }
        }

        // Because the Stores() class memoizes on initialization we need to set() a new stores class
        Plugin::getInstance()->set('stores', new Stores());
    }

    /**
     * @inheritdoc
     */
    public function unload(): void
    {
        $this->checkIntegrity(true);
        unset($this->data['primary']);
        parent::unload();
        $this->checkIntegrity(false);

        // Delete store location addresses
        // foreach ($this->_storeSettings as $key => $settings) {
        //     if (!empty($settings['_storeLocationAddressId'])) {
        //         Craft::$app->getElements()->deleteElementById($settings['_storeLocationAddressId'], hardDelete: true);
        //     }
        // }

        // // Delete site stores records
        // foreach ($this->_storeSites as $siteIds) {
        //     foreach ($siteIds as $siteId) {
        //         $siteStoreRecord = SiteStore::findOne(['siteId' => $siteId]);
        //         if ($siteStoreRecord) {
        //             $siteStoreRecord->delete();
        //         }
        //     }
        // }
    }
}
