<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\elements\db\ElementQueryInterface;

/**
 * Order Condition Rule Trait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.5
 */
trait OrderConditionRuleTrait
{
    /**
     * @inheritdoc
     */
    public function getElementType(): string
    {
        return Order::class;
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        // Implement if needed to modify the query - gateway conditions
        // are typically applied at runtime.
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $element instanceof Order && $this->matchOrder($element);
    }

    /**
     * Match the order
     */
    protected function matchOrder(Order $order): bool
    {
        return true;
    }
}