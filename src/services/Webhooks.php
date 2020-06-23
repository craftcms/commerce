<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Gateway;
use Throwable;
use yii\base\Component;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Webhooks service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.9
 */
class Webhooks extends Component
{
    /**
     * @param Gateway $gateway
     * @return Response
     * @throws \Exception
     */
    public function processWebhook(Gateway $gateway): Response
    {
        $transactionHash = $gateway->getTransactionHashFromWebhook();
        $useMutex = $transactionHash ? true : false;
        $transactionLockName = 'commerceTransaction:' . $transactionHash;
        $mutex = Craft::$app->getMutex();

        if ($useMutex && !$mutex->acquire($transactionLockName, 5)) {
            throw new \Exception('Unable to acquire a lock for transaction: ' . $transactionHash);
        }

        $response = null;

        try {
            if ($gateway && $gateway->supportsWebhooks()) {
                $response = $gateway->processWebhook();
            } else {
                throw new BadRequestHttpException('Gateway not found or does not support webhooks.');
            }
        } catch (Throwable $exception) {
            $message = 'Exception while processing webhook: ' . $exception->getMessage() . "\n";
            $message .= 'Exception thrown in ' . $exception->getFile() . ':' . $exception->getLine() . "\n";
            $message .= 'Stack trace:' . "\n" . $exception->getTraceAsString();

            Craft::error($message, 'commerce');

            $response = Craft::$app->getResponse();
            $response->setStatusCodeByException($exception);
        }

        if ($useMutex) {
            $mutex->release($transactionLockName);
        }

        return $response;
    }
}