<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit;

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Decides whether an action that saves straight away, such as taking a payment or changing the
 * status, can go ahead on an order.
 *
 * A completed order that has an edit session with changes waiting is left alone, so the session
 * isn't made stale underneath whoever is editing it.
 *
 * @since 6.0.0
 */
final readonly class OrderEditGuard
{
    public function __construct(
        private OrderEditSessions $sessions,
    ) {
    }

    /**
     * Why the order can't take an immediate change right now, or `null` if it can.
     */
    public function blockReason(Order $order, ?int $userId): ?string
    {
        if (!$order->isCompleted || !$order->id) {
            return null;
        }

        $session = $this->sessions->getSessionByOrderId($order->id);

        if (!$session || !$session->hasChanges()) {
            return null;
        }

        if ($session->belongsTo($userId)) {
            return t('Update or discard your changes to this order first.', category: 'commerce');
        }

        $editor = $session->userId ? User::find()->id($session->userId)->status(null)->one() : null;

        return t('This order is being edited by {name}.', [
            'name' => $editor?->getFriendlyName() ?? t('another user', category: 'commerce'),
        ], category: 'commerce');
    }
}
