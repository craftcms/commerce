<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use CraftCms\Commerce\Payment\Events\WebhookProcessed;
use CraftCms\Commerce\Payment\Events\WebhookProcessing;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

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
        $beforeEvent = new WebhookProcessing(gateway: $gateway);
        event($beforeEvent);

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

            $statusCode = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
            $response = new Response('', $statusCode);
        }

        if ($useMutex) {
            $lock->release();
        }

        // Fire a 'afterProcessWebhook' event
        $afterEvent = new WebhookProcessed(gateway: $gateway);
        $afterEvent->response = $response;
        event($afterEvent);

        return $response;
    }
}
