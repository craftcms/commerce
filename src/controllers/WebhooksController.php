<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Webhook Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class WebhooksController extends BaseController
{
    /**
     * @inheritdoc
     */
    protected $allowAnonymous = ['process-webhook'];

    /**
     * @return Response
     * @throws HttpException If webhook not expected.
     */
    public function actionProcessWebhook(): Response
    {
        $gatewayId = Craft::$app->getRequest()->getRequiredParam('gateway');
        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        $response = null;

        if ($gateway && $gateway->supportsWebhooks()) {
            $response = $gateway->processWebHook();
        }

        if (!$response)  {
            throw new HttpException(400);
        }

        // TODO audit trail, please.
        return $this->asRaw($response);
    }
}
