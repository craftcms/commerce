<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Variant;
use craft\commerce\helpers\Purchasable;
use yii\web\BadRequestHttpException;
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
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('commerce/variants/_index');
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @since 5.0.0
     */
    public function actionCardHtml(): Response
    {
        $this->requireAcceptsJson();

        $variantId = $this->request->getRequiredBodyParam('variantId');
        $siteHandle = $this->request->getQueryParam('site');

        $variantQuery = Variant::find()
            ->id($variantId);

        if ($siteHandle) {
            $variantQuery->site($siteHandle);
        }

        $variant = $variantQuery->one();

        if (!$variant) {
            throw new BadRequestHttpException("Invalid variant ID: $variantId");
        }

        if (!Craft::$app->getElements()->canView($variant)) {
            throw new ForbiddenHttpException('User not authorized to view this variant.');
        }

        $html = Purchasable::purchasableCardHtml($variant);

        return $this->asJson([
            'html' => $html,
        ]);
    }
}
