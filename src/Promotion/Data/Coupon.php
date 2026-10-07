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
                    $existing = DB::table(Table::COUPONS . ' as coupons')
                        ->select(['coupons.code', 'discounts.name'])
                        ->leftJoin(Table::DISCOUNTS . ' as discounts', 'discounts.id', '=', 'coupons.discountId')
                        ->when($this->id, fn($q) => $q->where('coupons.id', '!=', $this->id))
                        ->where(DB::raw('LOWER(coupons.code)'), mb_strtolower($value))
                        ->first();

                    if ($existing) {
                        $fail(t('Coupon code “{code}” is already in use by discount “{name}”.', [
                            'code' => $existing->code,
                            'name' => $existing->name,
                        ], category: 'commerce'));
                    }
                },
            ],
        ];
    }
}
