<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\errors\SiteNotFoundException;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Class Catalog Pricing Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingController extends BaseStoreSettingsController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('commerce-managePromotions');

        return true;
    }

    /**
     * @return Response|null
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    public function actionGenerateCatalogPrices(): ?Response
    {
        $purchasableId = $this->request->getBodyParam('purchasableId');
        $storeId = $this->request->getBodyParam('storeId');

        if ($purchasableId === null) {
            return $this->asFailure('Purchasable ID is required');
        }

        if ($storeId === null) {
            return $this->asFailure('Store ID is required');
        }

        $basePrice = $this->request->getBodyParam('basePrice');
        $basePromotionalPrice = $this->request->getBodyParam('basePromotionalPrice');

        $basePrice = $basePrice ? (float)$basePrice : null;
        $basePromotionalPrice = $basePromotionalPrice ? (float)$basePromotionalPrice : null;

        $allRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);

        if ($allRules->isEmpty()) {
            return $this->asJson([]);
        }

        $pricesByRuleId = [];
        $allRules->each(function(CatalogPricingRule $cpr) use (&$pricesByRuleId, $basePrice, $basePromotionalPrice) {
            $pricesByRuleId[$cpr->id] = [
                // @TODO review conversion to string if prices change from floats
                // Convert to string to prevent rounding errors in JS
                'price' => (string)Plugin::getInstance()->getCatalogPricingRules()->generateRulePriceFromPrice($basePrice, $basePromotionalPrice, $cpr),
                'isPromotionalPrice' => $cpr->isPromotionalPrice,
            ];
        });

        return $this->asJson($pricesByRuleId);
    }
}
