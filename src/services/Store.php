<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\elements\Address;
use craft\commerce\records\Store as StoreRecord;
use craft\elements\Address as AddressElement;
use yii\base\Component;

/**
 * Stores service. This manages the store level settings.
 *
 * @property-read Address $storeLocationAddress
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Store extends Component
{
    /**
     * @var ?StoreRecord
     */
    private ?StoreRecord $_store = null;

    public function init()
    {
        $this->_store = StoreRecord::find()->one();

        if ($this->_store === null) {
            $this->_store = new StoreRecord();
            $this->_store->save();
        }

        return true;
    }
    /**
     * @var ?Address
     */
    private ?Address $_storeLocationAddress = null;

    /**
     * Returns the store location address
     *
     * @return Address
     */
    public function getStoreLocationAddress(): Address
    {
        if ($this->_storeLocationAddress !== null) {
            return $this->_storeLocationAddress;
        }

        $this->_storeLocationAddress = AddressElement::findOne($this->_store->locationAddressId);

        if ($this->_storeLocationAddress === null) {
            $this->_storeLocationAddress = new AddressElement();
            $this->_storeLocationAddress->title = 'Store';
            Craft::$app->getElements()->saveElement($this->_storeLocationAddress, false);
        }

        return $this->_storeLocationAddress;
    }
}