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
use craft\web\twig\TemplateLoaderException;
use yii\base\InvalidConfigException;
use yii\web\Response;
use yii\web\Response as YiiResponse;

/**
 * Class Store Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class StoreController extends BaseStoreSettingsController
{
    /**
     * @return YiiResponse
     * @throws TemplateLoaderException
     * @throws InvalidConfigException
     */
    public function actionEdit(): Response
    {
        $addressesService = Craft::$app->getAddresses();

        // We will always have a store location address.
        $store = Plugin::getInstance()->getStoreSettings()->getStore();
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
            'instructions' => Craft::t('commerce', 'The countries that orders are allowed to be placed from.'),
            'id' => 'countries',
            'name' => 'countries',
            'values' => $store->getCountries(),
            'options' => $allCountries,
            'errors' => $store->getErrors('countries'),
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
     * @return YiiResponse|null
     * @throws InvalidConfigException
     */
    public function actionSave(): ?YiiResponse
    {
        $store = Plugin::getInstance()->getStoreSettings()->getStore();
        if ($locationAddressId = $this->request->getBodyParam('locationAddressId')) {
            /** @var Address|null $locationAddress */
            $locationAddress = Address::find()->id($locationAddressId)->one();
            if ($locationAddress) {
                $store->setLocationAddress($locationAddress);
            }
        }
        $marketAddressCondition = $this->request->getBodyParam('marketAddressCondition') ?? new ZoneAddressCondition();
        $store->setMarketAddressCondition($marketAddressCondition);
        $countries = $this->request->getBodyParam('countries') ?: [];
        $store->setCountries($countries);

        if (!$store->validate() || !Plugin::getInstance()->getStoreSettings()->saveStore($store)) {
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
