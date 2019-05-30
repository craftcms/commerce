<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\Currency;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\web\Controller;

/**
 * Class Purchasables Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class PurchasablesController extends Controller
{
    public $enableCsrfValidation = false;
    public $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    public function actionSearch($query = null)
    {
        // Prepare purchasables query
        $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
        $sqlQuery = (new Query())
            ->select(['id', 'price', 'description', 'sku'])
            ->from('{{%commerce_purchasables}}');

        // Are they searching for a purchasable ID?
        if (is_numeric($query)) {
            $result = $sqlQuery->where(['id' => $query])->all();
            if (!$result) {
                return $this->asJson([]);
            }
            return $this->asJson($result);
        }

        // Are they searching for a SKU or purchasable description?
        if ($query) {
            $sqlQuery->where([
                'or',
                [$likeOperator, 'description', $query],
                [$likeOperator, 'SKU', $query]
            ]);
        }

        $result = $sqlQuery->limit(3)->all();

        if (!$result) {
            return $this->asJson([]);
        }

        $purchasables = [];

        // Add the currency formatted price
        $baseCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        foreach($result as $row)
        {
            $row['priceAsCurrency'] = Craft::$app->getFormatter()->asCurrency($row['price'], $baseCurrency, [], [], true);
            $purchasables[] = $row;
        }

        return $this->asJson($purchasables);
    }
}
