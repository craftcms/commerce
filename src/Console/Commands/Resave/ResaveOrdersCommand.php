<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\Resave;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Commerce\Order\Elements\Order;
use Override;

class ResaveOrdersCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:orders'.self::DEFAULT_OPTIONS;

    #[Override]
    protected $description = 'Re-saves completed Commerce orders.';

    #[Override]
    protected $aliases = ['resave/orders'];

    public function handle(): int
    {
        if (!$this->validateResaveOptions()) {
            return self::FAILURE;
        }

        if (!empty($this->resolvedWithFields) && !$this->hasTheFields(Fields::getLayoutByType(Order::class))) {
            $this->components->warn('The order field layout does not satisfy `--with-fields`.');

            return self::FAILURE;
        }

        return $this->resaveElements(Order::class, [
            'isCompleted' => true,
        ]);
    }
}
