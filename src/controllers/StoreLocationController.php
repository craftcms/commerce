<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\behaviors\StoreLocationBehavior;
use craft\elements\Address as AddressElement;
use craft\commerce\records\Store;
use craft\helpers\Cp;
use yii\web\Response;

class StoreLocationController extends BaseStoreSettingsController
{
    /**
     * @var Store
     */
    private ?Store $_store = null;

    public function beforeAction($action): bool
    {
        $this->_store = Store::find()->one();

        if ($this->_store === null) {
            $this->_store = new Store();
            $this->_store->save();
        }

        return true;
    }

    public function actionEditLocation(): Response
    {
        $view = $this->getView();
        $storeLocation = AddressElement::findOne($this->_store->locationAddressId);

        if (!$storeLocation) {
            $storeLocation = new AddressElement();
        }

        // Save the address now so that it is stored as the store location.
        $storeLocation->attachBehavior('storeLocation', StoreLocationBehavior::class);
        Craft::$app->getElements()->saveElement($storeLocation);

        $storeLocationHtml = Cp::addressCardsHtml(
            addresses: [$storeLocation],
            config: [
                'name' => 'storeLocation',
                'maxAddresses' => 1
            ]
        );

        $variables = [
            'storeLocationHtml' => $storeLocationHtml,
        ];

        return $this->renderTemplate('commerce/store-settings/location/index', $variables);
    }
}
