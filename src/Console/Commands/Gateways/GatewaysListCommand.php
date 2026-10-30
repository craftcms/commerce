<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\Gateways;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use Illuminate\Console\Command;
use Override;

class GatewaysListCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'commerce:gateways:list';

    #[Override]
    protected $description = 'Lists the currently-configured, non-archived gateways.';

    #[Override]
    protected $aliases = ['commerce/gateways', 'commerce/gateways/list'];

    public function handle(Gateways $gateways): int
    {
        $rows = $gateways->getAllGateways()
            ->map(fn($gateway) => [
                $gateway->id,
                $gateway->name,
                $gateway->handle,
                $gateway->getIsFrontendEnabled() ? 'Yes' : 'No',
                $gateway::class,
                $gateway->uid,
            ])
            ->all();

        $this->table(['ID', 'Name', 'Handle', 'Enabled', 'Type', 'UUID'], $rows);

        return self::SUCCESS;
    }
}
