<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use craft\commerce\helpers\Purchasable;
use craft\commerce\models\CatalogPricing;
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
    public function actionGenerateCatalogPrices(): ?string
    {
        $purchasableId = $this->request->getBodyParam('purchasableId');
        $storeId = $this->request->getBodyParam('storeId');

        if ($purchasableId === null) {
            return $this->asFailure('Purchasable ID is required');
        }

        if ($storeId === null) {
            return $this->asFailure('Store ID is required');
        }

        $isPriceRecalculation = array_key_exists('basePrice', $this->request->getBodyParams()) || array_key_exists('basePromotionalPrice', $this->request->getBodyParams());

        if (!$isPriceRecalculation) {
            // No need to generate prices if we are just getting the standard price list
            return Purchasable::catalogPricingRulesTableByPurchasableId($purchasableId, $storeId);
        }

        $basePrice = $this->request->getBodyParam('basePrice');
        $basePromotionalPrice = $this->request->getBodyParam('basePromotionalPrice');

        $basePrice = $basePrice ? (float)$basePrice : null;
        $basePromotionalPrice = $basePromotionalPrice ? (float)$basePromotionalPrice : null;

        $allPurchasableRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);
        $catalogPricing = Plugin::getInstance()->getCatalogPricing()->getCatalogPricesByPurchasableId($purchasableId);

        $catalogPricing->each(function(CatalogPricing $cp) use ($basePrice, $basePromotionalPrice, $allPurchasableRules) {
            $rule = $allPurchasableRules->firstWhere('id', $cp->catalogPricingRuleId);
            if (!$rule) {
                return;
            }

            $cp->price = Plugin::getInstance()->getCatalogPricingRules()->generateRulePriceFromPrice($basePrice, $basePromotionalPrice, $rule);
        });

        return Purchasable::catalogPricingRulesTableByPurchasableId($purchasableId, $storeId, $catalogPricing);
    }
}
