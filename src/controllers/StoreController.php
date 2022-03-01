<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\behaviors\StoreLocationBehavior;
use craft\commerce\Plugin;
use craft\elements\Address as AddressElement;
use craft\commerce\records\Store;
use craft\helpers\Cp;
use Illuminate\Support\Collection;
use yii\web\Response;

class StoreController extends BaseStoreSettingsController
{
    public function actionEditLocation(): Response
    {
        // We will always have a store location address.
        $storeLocation = Plugin::getInstance()->getStore()->getStoreLocationAddress();

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

    public function actionSaveMarketLocations()
    {
        $marketLocations = $this->request->getBodyParam('marketLocations');
        return $this->asSuccess();
    }

    public function actionEditMarketLocations()
    {
        $countryCols = [
            'label' => [
                'heading' => Craft::t('app', 'Country'),
                'type' => 'heading',
                'autopopulate' => 'value',
                'class' => 'option-label',
            ],
            'enabled' => [
                'heading' => Craft::t('commerce', 'Enabled?'),
                'type' => 'checkbox',
                'radioMode' => false,
                'class' => 'option-default thin',
            ],
        ];

        $countries = Craft::$app->getAddresses()->getCountryRepository()->getAll(Craft::$app->language);
        $countryRows = [];
        foreach ($countries as $country) {
            $countryRows[$country->getCountryCode()] = [
                'label' => $country->getName(),
                'enabled' => false,
            ];
//            $administrativeAreas = Craft::$app->getAddresses()->getSubdivisionRepository()->getAll([$country->getCountryCode()]);
//            foreach ($administrativeAreas as $administrativeArea){
//                $countryRows[$country->getCountryCode().'-'.$administrativeArea->getCode()] = [
//                    'label' => '-'.$administrativeArea->getName(),
//                    'enabled' => false,
//                ];
//            }
        }

        $countryMarketsTableHtml = Cp::editableTableFieldHtml([
            'label' => Craft::t('commerce', 'Market Locations'),
            'instructions' => Craft::t('commerce', 'Define the markets that should be available to your customers.'),
            'id' => 'marketLocations',
            'name' => 'marketLocations',
            'allowAdd' => false,
            'allowReorder' => true,
            'allowDelete' => false,
            'cols' => $countryCols,
            'rows' => $countryRows,
        ]);

        $variables = [
            'countryMarketsTableHtml' => $countryMarketsTableHtml
        ];

        return $this->renderTemplate('commerce/store-settings/markets/index', $variables);
    }
}
