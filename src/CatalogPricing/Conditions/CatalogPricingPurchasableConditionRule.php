<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Conditions;

use CraftCms\Cms\Condition\BaseConditionRule;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\ElementSelect;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Commerce\CatalogPricing\Contracts\CatalogPricingConditionRuleInterface;
use CraftCms\Commerce\Purchasable\Purchasables;
use Illuminate\Database\Query\Builder;

use Override;
use function CraftCms\Cms\t;

class CatalogPricingPurchasableConditionRule extends BaseConditionRule implements CatalogPricingConditionRuleInterface
{
    /**
     * @var array|null
     * @see getElementIds()
     * @see setElementIds
     */
    private ?array $_elementIds = null;

    #[Override]
    public function getLabel(): string
    {
        return t('Purchasable', category: 'commerce');
    }

    public function setElementIds($value): void
    {
        $this->_elementIds = $value;
    }

    public function getElementIds(): ?array
    {
        if ($this->_elementIds === null) {
            return null;
        }

        $elementIds = [];
        foreach ($this->_elementIds as $ids) {
            $elementIds = array_merge($elementIds, $ids);
        }

        return $elementIds;
    }

    #[Override]
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'elementIds' => $this->_elementIds,
        ]);
    }

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'elementIds' => ['nullable', 'array'],
        ]);
    }

    /** @return list<Node> */
    #[Override]
    protected function inputNodes(): array
    {
        return array_map(
            fn(string $purchasableType) => Field::make($purchasableType::displayName(), ElementSelect::make(['elementIds', $purchasableType])
                ->elementType($purchasableType)
                ->value($this->_elementIds[$purchasableType] ?? [])),
            app(Purchasables::class)->getAllPurchasableElementTypes(),
        );
    }

    #[Override]
    public function getExclusiveQueryParams(): array
    {
        return ['id'];
    }

    #[Override]
    public function modifyQuery(Builder $query): void
    {
        $ids = $this->getElementIds();
        if ($ids === null) {
            return;
        }

        $query->whereIn('purchasableId', $ids);
    }
}
