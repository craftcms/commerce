<?php

namespace craft\commerce\services;

use craft\commerce\Plugin;
use CraftCms\Commerce\Payment\Events\WebhookProcessed;
use CraftCms\Commerce\Payment\Events\WebhookProcessing;
use Illuminate\Support\Facades\Event;

use craft\commerce\base\GatewayInterface;
use Illuminate\Http\Response;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Payment\Webhooks::class)` instead.
 */
class Webhooks extends Component
{
    public const EVENT_BEFORE_PROCESS_WEBHOOK = \CraftCms\Commerce\Payment\Webhooks::EVENT_BEFORE_PROCESS_WEBHOOK;

    public const EVENT_AFTER_PROCESS_WEBHOOK = \CraftCms\Commerce\Payment\Webhooks::EVENT_AFTER_PROCESS_WEBHOOK;

    /**
     * @throws \Exception
     */
    public function processWebhook(GatewayInterface $gateway): Response
    {
        return app(\CraftCms\Commerce\Payment\Webhooks::class)->processWebhook($gateway);
    }

    public static function registerEvents(): void
    {
        Event::listen(WebhookProcessing::class, static function(WebhookProcessing $event) {
            $legacy = Plugin::getInstance()->getWebhooks();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_PROCESS_WEBHOOK)) {
                $legacy->trigger(self::EVENT_BEFORE_PROCESS_WEBHOOK, $event);
            }
        });

        Event::listen(WebhookProcessed::class, static function(WebhookProcessed $event) {
            $legacy = Plugin::getInstance()->getWebhooks();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_PROCESS_WEBHOOK)) {
                $legacy->trigger(self::EVENT_AFTER_PROCESS_WEBHOOK, $event);
            }
        });
    }
}
