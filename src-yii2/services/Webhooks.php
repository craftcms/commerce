<?php

namespace craft\commerce\services;

use craft\commerce\base\GatewayInterface;
use yii\base\Component;
use yii\web\Response;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Webhooks::class)` instead.
 */
class Webhooks extends Component
{
    public const EVENT_BEFORE_PROCESS_WEBHOOK = \CraftCms\Commerce\Services\Webhooks::EVENT_BEFORE_PROCESS_WEBHOOK;

    public const EVENT_AFTER_PROCESS_WEBHOOK = \CraftCms\Commerce\Services\Webhooks::EVENT_AFTER_PROCESS_WEBHOOK;

    /**
     * @throws \Exception
     */
    public function processWebhook(GatewayInterface $gateway): Response
    {
        return app(\CraftCms\Commerce\Services\Webhooks::class)->processWebhook($gateway);
    }
}
