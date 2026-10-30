<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Actions;

use CraftCms\Cms\Element\Actions\ElementAction;

use function CraftCms\Cms\t;

class CopyLoadCartUrl extends ElementAction
{
    #[\Override]
    public static function supportsBulk(): bool
    {
        return false;
    }

    public function getTriggerLabel(): string
    {
        return t('Share cart…', category: 'commerce');
    }
}
