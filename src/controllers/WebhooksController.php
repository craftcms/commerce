<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class Webhooks Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class WebhooksController extends BaseController
{
    /**
     * @inheritdoc
     */
    protected $allowAnonymous = ['process-webhook'];

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @param null $gatewayId
     * @return Response
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionProcessWebhook($gatewayId = null): Response
    {
        if ($gatewayId == null) {
            $gatewayId = Craft::$app->getRequest()->getRequiredParam('gateway');
        }

        if (!$gatewayId) {
            throw new BadRequestHttpException('Invalid gateway ID: ' . $gatewayId);
        }

        if (!$gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId)) {
            throw new NotFoundHttpException('Gateway not found');
        }

        return Plugin::getInstance()->getWebhooks()->processWebhook($gateway);
    }
}
