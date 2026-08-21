<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\Gateways;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use Illuminate\Console\Command;
use Override;

class GatewaysWebhookUrlCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'commerce:gateways:webhook-url {handle : The handle of the gateway}';

    #[Override]
    protected $description = 'Gets a webhook URL for the provided gateway.';

    #[Override]
    protected $aliases = ['commerce/gateways/webhook-url'];

    public function handle(Gateways $gateways): int
    {
        $handle = (string)$this->argument('handle');
        $gateway = $gateways->getGatewayByHandle($handle);

        if (!$gateway) {
            $this->components->error("A gateway with handle `$handle` does not exist.");

            return self::FAILURE;
        }

        $this->line("Webhook URL for the {$gateway->name} gateway:");
        $this->line("<fg=blue>{$gateway->getWebhookUrl()}</>");

        return self::SUCCESS;
    }
}
