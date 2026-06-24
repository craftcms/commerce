<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Models;

use CraftCms\Cms\Component\Component;

class Coupon extends Component
{
    public ?int $id = null;

    public ?int $discountId = null;

    public ?string $code = null;

    public int $uses = 0;

    public ?int $maxUses = null;

    #[\Override]
    public function getRules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
