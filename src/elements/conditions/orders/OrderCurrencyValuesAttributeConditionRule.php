<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\ConditionInterface;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionInterface;
use craft\fields\conditions\MoneyFieldConditionRule;
use craft\fields\Money;
use craft\models\Site;
use Money\Currency;
use yii\db\QueryInterface;

/**
 * Order Number Attribute Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @method ElementConditionInterface|HasStoreInterface getCondition()
 * @property-read float|int $orderAttributeValue
 */
abstract class OrderCurrencyValuesAttributeConditionRule extends MoneyFieldConditionRule
{
    /**
     * @var string
     */
    public string $orderAttribute = '';

    /**
     * @var Currency|null
     */
    public ?Currency $currency = null;

    /**
     * @var int|null
     */
    public ?int $subUnit = null;

    public function __construct($config = [])
    {
        $this->setFieldUid('not-applicable');
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function setCondition(ConditionInterface $condition): void
    {
        parent::setCondition($condition);

        if ($this->getCondition() instanceof HasStoreInterface) {
            $this->currency = $this->getCondition()->getStore()->getCurrency();
        } else {
            /** @var Site|StoreBehavior|null $currentSite */
            $currentSite = Craft::$app->getSites()->getCurrentSite();
            $this->currency = $currentSite?->getStore()->getCurrency();
        }

        if ($this->currency) {
            $this->subUnit = Plugin::getInstance()->getCurrencies()->getSubunitFor($this->currency);
        }
    }

    /**
     * @inheritdoc
     */
    protected function field(): FieldInterface
    {
        // Mock a Money field
        $field = new Money();
        $field->currency = $this->currency?->getCode();

        return $field;
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [$this->orderAttribute];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return 'Label not implemented';
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->{$this->orderAttribute});
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(QueryInterface $query): void
    {
        $query->{$this->orderAttribute}($this->paramValue());
    }
}
