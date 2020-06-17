<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use Throwable;
use yii\web\HttpException;
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
     * @return Response
     * @throws HttpException If webhook not expected.
     */
    public function actionProcessWebhook(): Response
    {
        $gatewayId = Craft::$app->getRequest()->getRequiredParam('gateway');
        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        return Plugin::getInstance()->getWebhooks()->processWebhook($gateway);
    }
}
