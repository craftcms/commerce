<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Database\Table;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\t;

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
            'code' => [
                'required',
                'string',
                function($attribute, $value, \Closure $fail) {
                    $isPgsql = DB::connection()->getDriverName() === 'pgsql';

                    $exists = DB::table(Table::COUPONS)
                        ->when($this->id, fn($q) => $q->where('id', '!=', $this->id))
                        ->when(
                            $isPgsql,
                            fn($q) => $q->whereRaw('LOWER(code) = LOWER(?)', [$value]),
                            fn($q) => $q->where('code', $value),
                        )
                        ->exists();

                    if ($exists) {
                        $fail(t('Coupon code "{code}" is already in use.', ['code' => $value], category: 'commerce'));
                    }
                },
            ],
        ];
    }
}
