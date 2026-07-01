<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\records\Coupon as CouponRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Promotion\Models\Coupon;
use CraftCms\Commerce\Promotion\Models\Discount;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Singleton]
class Coupons
{
    public const COUPON_FORMAT_REPLACEMENT_CHAR = '#';
    public const DEFAULT_COUPON_FORMAT = '######';
    public const CHARS_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const CHARS_LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const CHARS_NUMBERS = '0123456789';
    public const CHARS_SPECIAL = '!@#$%^&*()-_=+[]{}|;:,.<>/?~';

    private ?array $allCodes = null;

    public function getAllCodes(): ?array
    {
        if ($this->allCodes !== null) {
            return $this->allCodes;
        }

        $this->allCodes = $this->query()
            ->select(['id', 'code'])
            ->get()
            ->keyBy('id')
            ->map(fn($row) => $row->code)
            ->all();

        return $this->allCodes;
    }

    public function getCouponByCode(string $code): ?Coupon
    {
        $row = $this->query()->where('code', $code)->first();

        return $row ? new Coupon((array) $row) : null;
    }

    /**
     * @return Coupon[]
     */
    public function getCouponsByDiscountId(int $discountId): array
    {
        return $this->query()
            ->where('discountId', $discountId)
            ->get()
            ->map(fn($row) => new Coupon((array) $row))
            ->all();
    }

    /**
     * @param string[] $existingCodes
     * @return string[]
     * @throws \Exception
     */
    public function generateCouponCodes(int $count = 1, string $format = self::DEFAULT_COUPON_FORMAT, array $existingCodes = []): array
    {
        $numReplacementChars = strlen($format) - strlen(str_replace(self::COUPON_FORMAT_REPLACEMENT_CHAR, '', $format));
        $numPossibleCodes = strlen(self::CHARS_UPPER) ** $numReplacementChars;

        if ($numPossibleCodes < $count) {
            throw new \Exception('The format is too restrictive to generate enough unique codes.');
        }

        $existingCodes = array_unique([...$existingCodes, ...$this->getAllCodes()]);
        $coupons = [];

        for ($i = 1; $i <= $count; $i++) {
            $code = preg_replace_callback('/([' . self::COUPON_FORMAT_REPLACEMENT_CHAR . ']+)/', static function($matches) {
                return self::randomStringWithChars(self::CHARS_UPPER, strlen($matches[0]));
            }, $format);

            if (!empty($existingCodes) && in_array($code, $existingCodes, true)) {
                $i--;
                continue;
            }
            $coupons[] = $code;
            $existingCodes[] = $code;
        }

        return $coupons;
    }

    public function deleteCouponById(int $id): bool
    {
        /** @phpstan-ignore-next-line */
        $record = CouponRecord::findOne($id);

        if (!$record) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        return (bool) $record->delete();
    }

    public function saveDiscountCoupons(Discount $discount): bool
    {
        if (!$discount->id) {
            throw new \RuntimeException('Discount must be saved before it can have coupons');
        }

        $existingCouponIds = $this->query()
            ->where('discountId', $discount->id)
            ->pluck('id')
            ->all();

        $couponIds = [];
        foreach ($discount->getCoupons() as $key => $coupon) {
            $coupon->discountId = $discount->id;

            if (!$this->saveCoupon($coupon)) {
                $discount->addModelErrors($coupon, 'coupon.' . $key);
            }

            if ($coupon->id) {
                $couponIds[] = $coupon->id;
            }
        }

        $return = !$discount->hasErrors();

        if (empty($existingCouponIds) || $existingCouponIds === $couponIds) {
            return $return;
        }

        foreach (array_diff($existingCouponIds, $couponIds) as $deleteId) {
            $this->deleteCouponById($deleteId);
        }

        return $return;
    }

    public function saveCoupon(Coupon $coupon, bool $runValidation = true): bool
    {
        if ($coupon->id) {
            /** @phpstan-ignore-next-line */
            $record = CouponRecord::findOne($coupon->id);

            if (!$record) {
                throw new \RuntimeException("Invalid coupon ID: {$coupon->id}");
            }
        } else {
            $record = new CouponRecord();
        }

        if ($runValidation && !$coupon->validate()) {
            Log::info('Coupon not saved due to validation error.');
            return false;
        }

        /** @phpstan-ignore-next-line */
        $record->code = $coupon->code;
        /** @phpstan-ignore-next-line */
        $record->discountId = $coupon->discountId;
        /** @phpstan-ignore-next-line */
        $record->uses = $coupon->uses;
        /** @phpstan-ignore-next-line */
        $record->maxUses = $coupon->maxUses;

        /** @phpstan-ignore-next-line */
        $record->save(false);

        $coupon->id = $record->id;

        $this->clearCaches();

        return true;
    }

    protected function clearCaches(): void
    {
        $this->allCodes = null;
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::COUPONS)
            ->select([
                'id',
                'code',
                'uses',
                'maxUses',
                'discountId',
            ]);
    }

    private static function randomStringWithChars(string $chars, int $length): string
    {
        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }
}
