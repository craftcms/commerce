<?php

namespace craft\commerce\elements\conditions\addresses;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\commerce\errors\NotImplementedException;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use yii\base\NotSupportedException;

/**
 * Total Price Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 */
class PostCodeFormulaConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Post Code Formula');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount Address Condition does not support element queries.');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        throw new NotImplementedException();
    }

    public function inputHtml(): string
    {
        return 'TODO' . parent::inputHtml();
    }
}