<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use craft\commerce\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class Variants Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class VariantsController extends BaseController
{
    /**
     * @inheritdoc
     * @throws ForbiddenHttpException
     */
    public function init(): void
    {
        parent::init();

        if (empty(Plugin::getInstance()->getProductTypes()->getViewableProductTypeIds(true))) {
            throw new ForbiddenHttpException('User is not permitted to view any product types.');
        }
    }

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('commerce/variants/_index');
    }
}
