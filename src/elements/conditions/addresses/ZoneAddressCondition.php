<?php

namespace craft\commerce\elements\conditions\addresses;

use craft\elements\Address;
use craft\elements\conditions\addresses\AddressCondition as ElementAddressCondition;
use craft\elements\db\ElementQueryInterface;
use yii\base\NotSupportedException;

/**
 * Addresses condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class ZoneAddressCondition extends ElementAddressCondition
{
    /**
     * @inheritdoc
     */
    public ?string $elementType = Address::class;

    /**
     * @inheritdoc
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(),
            [
                PostalCodeFormulaConditionRule::class,
            ]);
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     * @throws NotSupportedException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount Address Condition does not support element queries.');
    }
}
