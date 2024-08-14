<?php

namespace craft\commerce\elements\conditions\orders;

use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\StoreTrait;
use craft\elements\db\ElementQueryInterface;
use yii\base\NotSupportedException;

/**
 * Discount Order condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class DiscountOrderCondition extends OrderCondition implements HasStoreInterface
{
    use StoreTrait;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['storeId'], 'safe'];

        return $rules;
    }

    /**
     * @return array
     */
    protected function config(): array
    {
        return array_merge(parent::config(), $this->toArray(['storeId']));
    }

    /**
     * @inheritdoc
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), []);
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     * @throws NotSupportedException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount Order Condition does not support element queries.');
    }
}
