<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use craft\db\Query;
use craft\web\Controller;
use yii\db\conditions\LikeCondition;
use Craft;

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
        if (is_numeric($query)) {
            $result = (new Query())
                ->select(['id','price','description','sku'])
                ->from('{{%commerce_purchasables}}')
                ->where(['id' => $query])
                ->all();

            if (!$result) {
                return $this->asJson([]);
            }

            return $this->asJson($result);
        }

        $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
        $sqlQuery = (new Query())
            ->select(['id','price','description','sku'])
            ->from('{{%commerce_purchasables}}');

        if ($query) {
            $sqlQuery->where(['or',
                [$likeOperator, 'description', $query],
                [$likeOperator, 'SKU', $query]
            ]);
        }

        $result = $sqlQuery->all();

        if (!$result) {
            return $this->asJson([]);
        }

        return $this->asJson($result);
    }
}
