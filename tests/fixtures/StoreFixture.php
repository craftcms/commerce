<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\commerce\models\Store;
use craft\commerce\models\StoreSettings as StoreSettingsModel;
use craft\commerce\Plugin;
use craft\commerce\services\Stores;
use craft\commerce\services\StoreSettings;
use craft\elements\Address;

/**
 * Class StoreFixture
 * @package craftcommercetests\fixtures
 * @since 5.0.0
 */
class StoreFixture extends BaseModelFixture
{
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
    private $_storeSettings = [];

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
            $this->_settingsService->saveStore($storeSettings);
        }
    }

    /**
     * @inheritdoc
     */
    public function unload(): void
    {
        unset($this->data['primary']);
        parent::unload();

        // Delete store location addresses
        foreach ($this->_storeSettings as $key => $settings) {
            if (!empty($settings['_storeLocationAddressId'])) {
                Craft::$app->getElements()->deleteElementById($settings['_storeLocationAddressId'], hardDelete: true);
            }
        }
    }
}
