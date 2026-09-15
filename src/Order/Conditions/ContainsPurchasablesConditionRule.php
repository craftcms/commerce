<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseElementSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\ElementSelect;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Commerce\Inventory\Enums\ContainsPurchasablesMatch;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Purchasables;
use Illuminate\Database\Query\Builder;
use Override;

use function CraftCms\Cms\t;

/**
 * @method array|string|null paramValue(?callable $normalizeValue = null)
 */
class ContainsPurchasablesConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
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

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        $ids = $this->getElementIds();
        if (empty($ids)) {
            return;
        }

        /** @var OrderQuery $elementQuery */
        $elementQuery->containsPurchasables(['purchasables' => $ids, 'match' => $this->getMatch()]);
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

    /** @return list<Node> */
    #[Override]
    protected function inputNodes(): array
    {
        return [
            Field::make(t('Match', category: 'commerce'), Choice::make('match')
                ->options($this->matchOptions())
                ->withoutPlaceholder()
                ->value($this->getMatch()->value)
                ->reactive()),
            Field::make(t('Purchasable Type', category: 'commerce'), Choice::make('purchasableType')
                ->options($this->purchasableTypeOptions())
                ->withoutPlaceholder()
                ->value($this->purchasableType)
                ->reactive()),
            ...parent::inputNodes(),
        ];
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
    protected function elementSelect(): ElementSelect
    {
        return parent::elementSelect()->showSiteMenu();
    }
}
