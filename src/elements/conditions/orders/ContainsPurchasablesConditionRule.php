<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

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
 * Contains Purchasables Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 *
 * @method array|string|null paramValue(?callable $normalizeValue = null)
 */
class ContainsPurchasablesConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var string
     */
    public string $purchasableType = Variant::class;

    /**
     * @var string The match mode: 'any', 'all', or 'only'.
     */
    public string $match = 'any';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Contains Purchasables');
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
        $ids = $this->getElementIds();
        if (empty($ids)) {
            return;
        }

        /** @var OrderQuery $query */
        $query->containsPurchasables(['purchasables' => $ids, 'match' => $this->match]);
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $element->hasPurchasables($this->getElementIds(), $this->match);
    }

    /**
     * @inheritdoc
     */
    protected function allowMultiple(): bool
    {
        return true;
    }

    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
           'purchasableType' => $this->purchasableType,
           'match' => $this->match,
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['purchasableType', 'match'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(): string
    {
        $matchId = 'match';
        $purchasableTypeId = 'purchasable-type';
        return Html::hiddenLabel($this->getLabel(), $matchId) .
            Html::tag('div',
                Cp::selectHtml([
                    'id' => $matchId,
                    'name' => 'match',
                    'options' => $this->_matchOptions(),
                    'value' => $this->match,
                    'inputAttributes' => [
                        'hx' => [
                            'post' => UrlHelper::actionUrl('conditions/render'),
                        ],
                    ],
                ]) .
                Cp::selectHtml([
                    'id' => $purchasableTypeId,
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

    /**
     * @return array
     */
    private function _matchOptions(): array
    {
        return [
            ['value' => 'any', 'label' => Craft::t('commerce', 'any')],
            ['value' => 'all', 'label' => Craft::t('commerce', 'all')],
            ['value' => 'only', 'label' => Craft::t('commerce', 'only')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function elementSelectConfig(): array
    {
        return array_merge(parent::elementSelectConfig(), [
            'showSiteMenu' => true,
        ]);
    }
}
