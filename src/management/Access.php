<?php

declare(strict_types=1);

namespace craft\commerce\management;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\mcp\Authorization;
use yii\web\ForbiddenHttpException;

/** Authorization shared by user-facing Commerce integrations. */
class Access
{
    public function permission(User $actor, string ...$permissions): void
    {
        foreach (['accessPlugin-commerce', ...$permissions] as $permission) {
            if (!$actor->can($permission)) {
                throw new ForbiddenHttpException('Resource not found.');
            }
        }
    }

    public function configuration(User $actor, bool $write = false): void
    {
        $this->permission($actor);

        if (!$actor->admin || ($write && !Craft::$app->getConfig()->getGeneral()->allowAdminChanges)) {
            throw new ForbiddenHttpException('You are not authorized to manage Commerce configuration.');
        }
    }

    public function store(User $actor, int $storeId): void
    {
        if (!Plugin::getInstance()->getStores()->getStoresByUserId($actor->id)->contains('id', $storeId)) {
            throw new ForbiddenHttpException('Store not found.');
        }
    }

    public static function allows(User $actor, string $ability, ElementInterface $element): bool
    {
        // This is a narrow MCP cart path, not a grant of native order-save permission.
        if ($element instanceof Order && in_array($ability, ['view', 'updateCart'], true) && self::ownsCart($actor, $element)) {
            return true;
        }
        if ($ability === 'updateCart') {
            $ability = 'save';
        }
        return self::canAccess($actor, $ability, $element)
            && (in_array($ability, ['capture', 'refund'], true) || Authorization::allows($actor, $ability, $element));
    }

    public static function ownsCart(User $actor, Order $order): bool
    {
        return $actor->id !== null && $order->id !== null && !$order->isCompleted
            && $order->getCustomerId() === $actor->id;
    }

    /** Additional integration restrictions, also applied to CMS tools that bypass adapters. */
    public static function canAccess(User $actor, string $ability, ElementInterface $element): bool
    {
        if (!$actor->can('accessPlugin-commerce')) {
            return false;
        }
        // Saved drafts remain shared. Provisional drafts belong only to their creator.
        // A shared variant's contextual owner can differ from its canonical primary owner.
        $subjects = $element instanceof Variant ? [$element, $element->getOwner(), $element->getPrimaryOwner()] : [$element];
        foreach ($subjects as $subject) {
            if (!$subject || ($subject->isProvisionalDraft && $subject->getBehavior('draft')?->creatorId !== $actor->id)) {
                return false;
            }
            if ($subject instanceof Product && ($ability === 'save' || ($element instanceof Variant && $ability === 'delete'))
                && !$actor->can('commerce-saveProductType:' . $subject->getType()->uid)) {
                return false;
            }
        }
        if ($element instanceof Order) {
            $permission = match ($ability) {
                'view' => 'commerce-manageOrders',
                'save' => 'commerce-editOrders',
                'delete' => 'commerce-deleteOrders',
                'capture' => 'commerce-capturePayment',
                'refund' => 'commerce-refundPayment',
                default => null,
            };
            return $permission !== null && $actor->can($permission)
                && Plugin::getInstance()->getStores()->getStoresByUserId($actor->id)->contains('id', $element->storeId);
        }
        return true;
    }
}
