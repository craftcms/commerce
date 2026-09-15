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
class HasPurchasableConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public string $purchasableType = Variant::class;

    public function getLabel(): string
    {
        return t('Has Purchasable', category: 'commerce');
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
        if ($this->getElementId() === null) {
            return;
        }

        /** @var OrderQuery $elementQuery */
        $elementQuery->hasPurchasables([$this->getElementId()]);
    }

    public function matchElement(ElementInterface $element): bool
    {
        return Order::find()
            ->id($element->id)
            ->hasPurchasables([$this->getElementId()])
            ->exists();
    }

    #[Override]
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'purchasableType' => $this->purchasableType,
        ]);
    }

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'purchasableType' => ['nullable', 'string'],
        ]);
    }

    /** @return list<Node> */
    #[Override]
    protected function inputNodes(): array
    {
        return [
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

    #[Override]
    protected function elementSelect(): ElementSelect
    {
        return parent::elementSelect()->showSiteMenu();
    }
}
