<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\SetCouponCode;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class SetCouponCodeForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetCouponCode::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Coupon Code', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return Ui::make([
            Field::make(t('Coupon Code', category: 'commerce'), Text::make('couponCode')->monospace()->autofocus())
                ->instructions(t('Leave blank to remove the coupon.', category: 'commerce')),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return ['couponCode' => (string)$order->couponCode];
    }

    public function operationData(Order $order, array $input): array
    {
        $couponCode = trim((string)($input['couponCode'] ?? ''));

        return ['couponCode' => $couponCode === '' ? null : $couponCode];
    }
}
