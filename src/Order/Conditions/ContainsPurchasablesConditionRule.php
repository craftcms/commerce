<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use craft\helpers\Cp;
use CraftCms\Cms\Condition\BaseElementSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Inventory\Enums\ContainsPurchasablesMatch;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Purchasable\Purchasables;
use Override;

use function CraftCms\Cms\t;

/**
 * @method array|string|null paramValue(?callable $normalizeValue = null)
 */
class ContainsPurchasablesConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    public string $purchasableType = Variant::class;

    /**
     * @see getMatch()
     * @see setMatch()
     */
    private ContainsPurchasablesMatch $_match = ContainsPurchasablesMatch::Any;

    public function getMatch(): ContainsPurchasablesMatch
    {
        return $this->_match;
    }

    public function setMatch(ContainsPurchasablesMatch|string $value): void
    {
        $this->_match = $value instanceof ContainsPurchasablesMatch ? $value : ContainsPurchasablesMatch::from($value);
    }

    public function getLabel(): string
    {
        return t('Contains Purchasables', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['hasPurchasable'];
    }

    protected function elementType(): string
    {
        return $this->purchasableType;
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $ids = $this->getElementIds();
        if (empty($ids)) {
            return;
        }

        /** @var OrderQuery $query */
        $query->containsPurchasables(['purchasables' => $ids, 'match' => $this->getMatch()]);
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $element->hasPurchasables($this->getElementIds(), $this->getMatch());
    }

    #[Override]
    protected function allowMultiple(): bool
    {
        return true;
    }

    #[Override]
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'purchasableType' => $this->purchasableType,
            'match' => $this->getMatch()->value,
        ]);
    }

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'purchasableType' => ['nullable', 'string'],
            'match' => ['nullable'],
        ]);
    }

    #[Override]
    protected function inputHtml(): string
    {
        $matchId = 'match';
        $purchasableTypeOptions = $this->purchasableTypeOptions();

        $purchasableTypeHtml = count($purchasableTypeOptions) === 1
            ? Html::hiddenInput('purchasableType', $purchasableTypeOptions[0]['value'])
            : Cp::selectHtml([
                'id' => 'purchasable-type',
                'name' => 'purchasableType',
                'options' => $purchasableTypeOptions,
                'value' => $this->purchasableType,
                'inputAttributes' => [
                    'hx' => [
                        'post' => Url::actionUrl('conditions/render'),
                    ],
                ],
            ]);

        return Html::hiddenLabel($this->getLabel(), $matchId) .
            Html::tag('div',
                Cp::selectHtml([
                    'id' => $matchId,
                    'name' => 'match',
                    'options' => $this->matchOptions(),
                    'value' => $this->getMatch()->value,
                    'inputAttributes' => [
                        'hx' => [
                            'post' => Url::actionUrl('conditions/render'),
                        ],
                    ],
                ]) .
                $purchasableTypeHtml .
                parent::inputHtml(),
                [
                    'class' => ['flex', 'flex-start'],
                ]
            );
    }

    #[Override]
    protected function selectionCondition(): ?ElementConditionInterface
    {
        /** @var OrderCondition $condition */
        $condition = Conditions::createCondition(['class' => OrderCondition::class]);

        return $condition;
    }

    /** @return list<array{value: class-string<ElementInterface>, label: string}> */
    private function purchasableTypeOptions(): array
    {
        $options = [];

        foreach (app(Purchasables::class)->getAllPurchasableElementTypes() as $elementType) {
            $options[] = [
                'value' => $elementType,
                'label' => $elementType::displayName(),
            ];
        }

        return $options;
    }

    /** @return list<array{value: string, label: string}> */
    private function matchOptions(): array
    {
        return array_map(
            fn(ContainsPurchasablesMatch $m) => ['value' => $m->value, 'label' => $m->label()],
            ContainsPurchasablesMatch::cases()
        );
    }

    #[Override]
    protected function elementSelectConfig(): array
    {
        return array_merge(parent::elementSelectConfig(), [
            'showSiteMenu' => true,
        ]);
    }
}
