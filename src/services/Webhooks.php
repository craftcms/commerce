<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\events\WebhookEvent;
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
     * @event WebhookEvent The event that is triggered before a Webhook is processed.
     * ```php
     * use craft\commerce\events\WebhookEvent;
     * use craft\commerce\services\Webhooks;
     * use craft\commerce\base\Gateway;
     * use yii\base\Event;
     *
     * Event::on(
     *     Webhooks::class,
     *     Webhooks::EVENT_BEFORE_PROCESS_WEBHOOK,
     *     function(WebhookEvent $event) {
     *         // @var Gateway $gateway
     *         $gateway = $event->gateway;
     *
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_PROCESS_WEBHOOK = 'beforeProcessWebhook';

    /**
     * @event WebhookEvent The event that is triggered after a Webhook is processed.
     * ```php
     * use craft\commerce\events\WebhookEvent;
     * use craft\commerce\services\Webhooks;
     * use craft\commerce\base\Gateway;
     * use yii\base\Event;
     *
     * Event::on(
     *     Webhooks::class,
     *     Webhooks::EVENT_AFTER_PROCESS_WEBHOOK,
     *     function(WebhookEvent $event) {
     *         // @var Response $response
     *         $response = $event->response;
     *
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_PROCESS_WEBHOOK = 'afterProcessWebhook';

    /**
     * @param Gateway $gateway
     * @return Response
     * @throws \Exception
     */
    public function processWebhook(Gateway $gateway): Response
    {
        // Fire a 'beforeProcessWebhook' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_PROCESS_WEBHOOK)) {
            $this->trigger(self::EVENT_BEFORE_PROCESS_WEBHOOK, new WebhookEvent([
                'gateway' => $gateway
            ]));
        }

        $transactionHash = $gateway->getTransactionHashFromWebhook();
        $useMutex = $transactionHash ? true : false;
        $transactionLockName = 'commerceTransaction:' . $transactionHash;
        $mutex = Craft::$app->getMutex();

        if ($useMutex && !$mutex->acquire($transactionLockName, 15)) {
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

        // Fire a 'afterProcessWebhook' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_PROCESS_WEBHOOK)) {
            $this->trigger(self::EVENT_AFTER_PROCESS_WEBHOOK, new WebhookEvent([
                'response' => $response
            ]));
        }

        return $response;
    }
}
