<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Address\Conditions;

use CraftCms\Cms\Address\Conditions\AddressCondition;
use CraftCms\Cms\Support\Html;
use Override;

class GatewayAddressCondition extends AddressCondition
{
    #[Override]
    public function getBuilderHtml(bool $readOnly = false): string
    {
        if ($readOnly) {
            return Html::disableInputs(fn() => parent::getBuilderHtml()) ?? '';
        }

        return parent::getBuilderHtml();
    }
}
