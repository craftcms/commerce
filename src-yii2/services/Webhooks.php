<?php

namespace craft\commerce\services;

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
}
