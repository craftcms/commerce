<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use craft\commerce\Plugin;
use CraftCms\Commerce\Payment\Events\WebhookEvent;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

#[Singleton]
class Webhooks
{
    public const string EVENT_BEFORE_PROCESS_WEBHOOK = 'beforeProcessWebhook';

    public const string EVENT_AFTER_PROCESS_WEBHOOK = 'afterProcessWebhook';

    /**
     * @throws \Exception
     */
    public function processWebhook(GatewayInterface $gateway): Response
    {
        // Fire a 'beforeProcessWebhook' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getWebhooks()->hasEventHandlers(self::EVENT_BEFORE_PROCESS_WEBHOOK)) {
            $beforeEvent = new WebhookEvent(gateway: $gateway);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getWebhooks()->trigger(self::EVENT_BEFORE_PROCESS_WEBHOOK, $beforeEvent);
        }

        $transactionHash = $gateway->getTransactionHashFromWebhook();
        $useMutex = (bool)$transactionHash;
        $transactionLockName = 'commerceTransaction:' . $transactionHash;
        $lock = null;

        if ($useMutex) {
            $lock = Cache::lock($transactionLockName, 60);
            try {
                $lock->block(15);
            } catch (LockTimeoutException) {
                throw new \Exception('Unable to acquire a lock for transaction: ' . $transactionHash);
            }
        }

        try {
            if ($gateway->supportsWebhooks()) {
                $response = $gateway->processWebHook();
            } else {
                throw new BadRequestHttpException('Gateway not found or does not support webhooks.');
            }
        } catch (Throwable $exception) {
            $message = 'Exception while processing webhook: ' . $exception->getMessage() . "\n";
            $message .= 'Exception thrown in ' . $exception->getFile() . ':' . $exception->getLine() . "\n";
            $message .= 'Stack trace:' . "\n" . $exception->getTraceAsString();

            Log::error($message);

            $response = \Craft::$app->getResponse();
            $response->setStatusCodeByException($exception);
        }

        if ($useMutex) {
            $lock->release();
        }

        // Fire a 'afterProcessWebhook' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getWebhooks()->hasEventHandlers(self::EVENT_AFTER_PROCESS_WEBHOOK)) {
            $afterEvent = new WebhookEvent(gateway: $gateway);
            $afterEvent->response = $response;
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getWebhooks()->trigger(self::EVENT_AFTER_PROCESS_WEBHOOK, $afterEvent);
        }

        return $response;
    }
}
