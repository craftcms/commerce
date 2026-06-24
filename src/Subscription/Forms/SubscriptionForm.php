<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Forms;

use CraftCms\Cms\Component\Component;

class SubscriptionForm extends Component
{
    public int $trialDays = 0;

    #[\Override]
    public function getRules(): array
    {
        return [
            'trialDays' => ['integer', 'min:0'],
        ];
    }
}
