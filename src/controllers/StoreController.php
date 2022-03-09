<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\helpers\Cp;
use craft\web\Response as CraftResponse;
use yii\web\Response;
use yii\web\Response as YiiResponse;

class StoreController extends BaseStoreSettingsController
{
    public function actionEdit(): Response
    {
        $addressesService = Craft::$app->getAddresses();

        // We will always have a store location address.
        $store = Plugin::getInstance()->getStore()->getStore();
        $allCountries = $addressesService->getCountryRepository()->getList(Craft::$app->language);

        $locationField = Cp::addressCardsHtml(
            addresses: [$store->getLocationAddress()],
            config: [
                'name' => 'locationAddressId',
                'maxAddresses' => 1,
            ]
        );

        $condition = $store->getMarketAddressCondition();
        $condition->mainTag = 'div';
        $condition->name = 'marketAddressCondition';
        $condition->id = 'marketAddressCondition';
        $marketAddressConditionField = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => Craft::t('app', 'Order Address Condition'),
            'instructions' => Craft::t('app', 'Only allow orders with addresses that match the following rules:'),
        ]);

        $js = <<<JS
$('#countries').selectize({
    plugins: ['remove_button'],
});
JS;
        Craft::$app->getView()->registerJs($js);

        $countriesField = Cp::multiSelectFieldHtml([
            'class' => 'selectize',
            'label' => Craft::t('commerce', 'Country List'),
            'instructions' => Craft::t('commerce', 'The list of countries available for selection by customers.'),
            'id' => 'countries',
            'name' => 'countries',
            'values' => $store->getCountries(),
            'options' => $allCountries,
            'allowEmptyOption' => true,
        ]);
        $variables = [];
        $variables['locationField'] = $locationField;
        $variables['marketAddressConditionField'] = $marketAddressConditionField;
        $variables['countriesField'] = $countriesField;
        $variables['store'] = $store;

        return $this->renderTemplate('commerce/store-settings/store/index', $variables);
    }

    /**
     * @return CraftResponse
     */
    public function actionSave(): YiiResponse
    {
        $store = Plugin::getInstance()->getStore()->getStore();
        if ($locationAddressId = Craft::$app->getRequest()->getBodyParam('locationAddressId')) {
            $locationAddress = Address::find()->id($locationAddressId)->one();
            if ($locationAddress) {
                $store->setLocationAddress($locationAddress);
            }
        }
        $marketAddressCondition = Craft::$app->getRequest()->getBodyParam('marketAddressCondition') ?? new ZoneAddressCondition();
        $store->setMarketAddressCondition($marketAddressCondition);
        $store->setCountries(Craft::$app->getRequest()->getBodyParam('countries', []));

        if (!$store->validate() || !Plugin::getInstance()->getStore()->saveStore($store)) {
            return $this->asFailure(
                message: Craft::t('commerce', 'Couldn’t save store.'),
                data: ['store' => $store]
            );
        }

        return $this->asSuccess(
            message: Craft::t('commerce', 'Store saved.'),
            data: ['store' => $store],
        );
    }
}
