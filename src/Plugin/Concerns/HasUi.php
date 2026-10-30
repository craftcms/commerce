<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Ui\UiControlTypes;
use CraftCms\Cms\Ui\UiNodeTypes;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Ui\Controls\SiteStores;
use CraftCms\Commerce\Ui\Controls\TransferDetails;
use CraftCms\Commerce\Ui\Controls\TransferReceive;
use CraftCms\Commerce\Ui\Nodes\CouponGenerator;
use CraftCms\Commerce\Ui\Nodes\UsageCounter;

/**
 * @mixin Plugin
 */
trait HasUi
{
    private function registerUiComponents(): void
    {
        app(UiNodeTypes::class)->register(CouponGenerator::class);
        app(UiNodeTypes::class)->register(UsageCounter::class);
        app(UiControlTypes::class)->register(SiteStores::class);
        app(UiControlTypes::class)->register(TransferDetails::class);
        app(UiControlTypes::class)->register(TransferReceive::class);
    }
}
