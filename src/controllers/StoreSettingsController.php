<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\models\StoreSettings;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\helpers\Cp;
use craft\web\twig\TemplateLoaderException;
use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\Response as YiiResponse;

/**
 * Class Store Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class StoreSettingsController extends BaseStoreSettingsController
{
    /**
     * @return YiiResponse
     * @throws TemplateLoaderException
     * @throws InvalidConfigException
     */
    public function actionEdit(StoreSettings $storeSettings = null, ?string $storeHandle = null): Response
    {
        $variables = compact('storeSettings', 'storeHandle');

        if (!$variables['storeSettings']) {
            if ($variables['storeHandle']) {
                // Store has the same ID as Store Settings
                $variables['store'] = Plugin::getInstance()->getStores()->getStoreByHandle($variables['storeHandle']);

                if (!$variables['store']) {
                    throw new HttpException(404);
                }

                $variables['storeSettings'] = $variables['store']->getSettings();
            } else {
                return $this->redirect(Plugin::getInstance()->getStores()->getPrimaryStore()->getStoreSettingsUrl());
            }
        }

        $addressesService = Craft::$app->getAddresses();
        $allCountries = $addressesService->getCountryRepository()->getList(Craft::$app->language);

        $locationFieldHtml = Cp::elementCardHtml($variables['storeSettings']->getLocationAddress(), [
            'context' => 'field',
            'inputName' => 'locationAddressId',
        ]);

        // Countries market condition field HTML
        $condition = $variables['storeSettings']->getMarketAddressCondition();
        $condition->mainTag = 'div';
        $condition->name = 'marketAddressCondition';
        $condition->id = 'marketAddressCondition';
        $marketAddressConditionFieldHtml = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => Craft::t('app', 'Order Address Condition'),
            'instructions' => Craft::t('app', 'Only allow orders with addresses that match the following rules:'),
        ]);

        // Countries allowed field HTML
        $countriesField = Cp::multiSelectFieldHtml([
            'class' => 'selectize',
            'label' => Craft::t('commerce', 'Country List'),
            'instructions' => Craft::t('commerce', 'The countries that orders are allowed to be placed from.'),
            'id' => 'countries',
            'name' => 'countries',
            'values' => $variables['storeSettings']->getCountries(),
            'options' => $allCountries,
            'errors' => $variables['storeSettings']->getErrors('countries'),
            'allowEmptyOption' => true,
        ]);
        $js = <<<JS
$('#countries').selectize({
    plugins: ['remove_button'],
});
JS;
        Craft::$app->getView()->registerJs($js);

        // Variables
        $variables['locationField'] = $locationFieldHtml;
        $variables['marketAddressConditionField'] = $marketAddressConditionFieldHtml;
        $variables['countriesField'] = $countriesField;
        $variables['storeSettingsNav'] = $this->getStoreSettingsNav();

        return $this->renderTemplate('commerce/store-settings/general/_edit', $variables);
    }

    /**
     * @return YiiResponse|null
     * @throws InvalidConfigException
     */
    public function actionSave(): ?YiiResponse
    {
        $storeId = Craft::$app->getRequest()->getBodyParam('id');
        $storeSettings = Plugin::getInstance()->getStoreSettings()->getStoreSettingsById($storeId);
        if ($locationAddressId = $this->request->getBodyParam('locationAddressId')) {
            /** @var Address|null $locationAddress */
            $locationAddress = Address::find()->id($locationAddressId)->one();
            if ($locationAddress) {
                $storeSettings->setLocationAddress($locationAddress);
            }
        }
        $marketAddressCondition = $this->request->getBodyParam('marketAddressCondition') ?? new ZoneAddressCondition();
        $storeSettings->setMarketAddressCondition($marketAddressCondition);
        $countries = $this->request->getBodyParam('countries') ?: [];
        $storeSettings->setCountries($countries);

        if (!$storeSettings->validate() || !Plugin::getInstance()->getStoreSettings()->saveStore($storeSettings)) {
            return $this->asModelFailure(
                model: $storeSettings,
                message: Craft::t('commerce', 'Couldn’t save store.'),
                modelName: 'storeSettings',
            );
        }

        return $this->asModelSuccess(
            model: $storeSettings,
            message: Craft::t('commerce', 'Store saved.'),
            modelName: 'storeSettings',
        );
    }
}
