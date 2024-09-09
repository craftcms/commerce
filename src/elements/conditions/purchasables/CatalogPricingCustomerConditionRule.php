<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\purchasables;

use Craft;
use craft\base\conditions\BaseConditionRule;
use craft\commerce\base\CatalogPricingConditionRuleInterface;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\Html;

/**
 * Catalog Pricing User Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingCustomerConditionRule extends BaseConditionRule implements CatalogPricingConditionRuleInterface
{
    /**
     * @var int|null
     */
    public ?int $customerId = null;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Customer');
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'customerId' => $this->customerId,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['customerId'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(): string
    {
        return Html::hiddenLabel($this->getLabel(), 'customer') .
            Html::tag('div',
                Cp::elementSelectHtml([
                    'name' => 'customerId',
                    'elements' => array_filter([$this->customerId]),
                    'elementType' => User::class,
                    'sources' => null,
                    'criteria' => null,
                    'single' => true,
                ]),
                [
                    'class' => ['flex', 'flex-start'],
                ]
            );
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['customer'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(Query $query): void
    {
        return;

        // Doesn't modify the query as the modification
        // of the query happens in `CatalogPricingCondition::modifyQuery()` for this rule
    }
}
