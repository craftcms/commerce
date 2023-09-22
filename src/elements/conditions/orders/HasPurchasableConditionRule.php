<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;

/**
 * Has Purchasables Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @method array|string|null paramValue(?callable $normalizeValue = null)
 */
class HasPurchasableConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var string
     */
    public string $purchasableType = Variant::class;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Has Purchasable');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['hasPurchasable'];
    }

    /**
     * @inheritdoc
     */
    protected function elementType(): string
    {
        return $this->purchasableType;
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        if ($this->getElementId() === null) {
            return;
        }

        /** @var OrderQuery $query */
        $query->hasPurchasables([(int)$this->getElementId()]);
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return Order::find()
            ->id($element->id)
            ->hasPurchasables([$this->getElementId()])
            ->exists();
    }

    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
           'purchasableType' => $this->purchasableType,
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['purchasableType'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(): string
    {
        $id = 'purchasable-type';
        return Html::hiddenLabel($this->getLabel(), $id) .
            Html::tag('div',
                Cp::selectHtml([
                    'id' => $id,
                    'name' => 'purchasableType',
                    'options' => $this->_purchasableTypeOptions(),
                    'value' => $this->purchasableType,
                    'inputAttributes' => [
                        'hx' => [
                            'post' => UrlHelper::actionUrl('conditions/render'),
                        ],
                    ],
                ]) .
                parent::inputHtml(),
                [
                    'class' => ['flex', 'flex-start'],
                ]
            );
    }


    protected function selectionCondition(): ?ElementConditionInterface
    {
        return Craft::$app->getConditions()->createCondition(['class' => OrderCondition::class]);
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    private function _purchasableTypeOptions(): array
    {
        $options = [];

        foreach (Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes() as $elementType) {
            /** @var string|ElementInterface $elementType */
            /** @phpstan-var class-string<ElementInterface>|ElementInterface $elementType */
            $options[] = [
                'value' => $elementType,
                'label' => $elementType::displayName(),
            ];
        }

        return $options;
    }
}
