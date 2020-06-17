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
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = ['process-webhook'];

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    // Public Methods
    // =========================================================================

    /**
     * @return Response
     * @throws HttpException If webhook not expected.
     */
    public function actionProcessWebhook(): Response
    {
        $gatewayId = Craft::$app->getRequest()->getRequiredParam('gateway');
        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        $response = null;

        try {
            if ($gateway && $gateway->supportsWebhooks()) {
                $transactionHash = $gateway->getTransactionHashFromWebhook();
                $useMutex = $transactionHash ? true : false;
                $transactionLockName = 'commerceTransaction:' . $transactionHash;
                $mutex = Craft::$app->getMutex();

                if ($useMutex && !$mutex->acquire($transactionLockName, 5)) {
                    throw new \Exception('Unable to acquire a lock for transaction: ' . $transactionHash);
                }

                $response = $gateway->processWebHook();

                if ($useMutex) {
                    $mutex->release($transactionLockName);
                }
            }
        } catch (Throwable $exception) {
            $message = 'Exception while processing webhook: ' . $exception->getMessage() . "\n";
            $message .= 'Exception thrown in ' . $exception->getFile() . ':' . $exception->getLine() . "\n";
            $message .= 'Stack trace:' . "\n" . $exception->getTraceAsString();

            Craft::error($message, 'commerce');

            $response = Craft::$app->getResponse();
            $response->setStatusCodeByException($exception);
        }

        return $response;
    }
}
