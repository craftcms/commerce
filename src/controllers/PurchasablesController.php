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
        if ($query === null) {
            return $this->asJson([]);
        }

        if (is_numeric($query)) {
            $result = (new Query())
                ->select(['*'])
                ->from('{{%commerce_purchasables}}')
                ->where(['id' => $query])
                ->all();

            if (!$result) {
                return $this->asJson([]);
            }

            return $this->asJson($result);
        }

        $result = (new Query())
            ->select(['*'])
            ->from('{{%commerce_purchasables}}')
            ->where(['or',
                new LikeCondition('description', 'LIKE', '%'.$query.'%'),
                new LikeCondition('SKU', 'LIKE', '%'.$query.'%'),
                ])
            ->all();

        if (!$result) {
            return $this->asJson([]);
        }

        return $this->asJson($result);
    }
}
