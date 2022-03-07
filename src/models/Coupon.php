<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\records\Coupon as CouponRecord;
use craft\validators\UniqueValidator;

/**
 * Class Coupon
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Coupon extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int|null Discount ID
     */
    public ?int $discountId = null;

    /**
     * @var string|null The coupon code
     */
    public ?string $code = null;

    /**
     * @var int Number of times the coupon has been used
     */
    public int $uses = 0;

    /**
     * @var int|null Number of times the coupon has been used
     */
    public ?int $maxUses = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['id', 'code', 'discountId', 'uses', 'maxUses'], 'safe'];
        $rules[] = [['code'], 'required'];
        $rules[] = [['code'], UniqueValidator::class, 'targetClass' => CouponRecord::class];

        return $rules;
    }
}