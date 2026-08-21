<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Support\Html;
use Override;

class GatewayOrderCondition extends OrderCondition
{
    #[Override]
    public function getBuilderHtml(bool $readOnly = false): string
    {
        if ($readOnly) {
            return Html::disableInputs(fn() => parent::getBuilderHtml());
        }

        return parent::getBuilderHtml();
    }
}
