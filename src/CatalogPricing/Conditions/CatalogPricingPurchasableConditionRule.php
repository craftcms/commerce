<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Conditions;

use craft\helpers\Cp;
use CraftCms\Cms\Condition\BaseConditionRule;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\CatalogPricing\Contracts\CatalogPricingConditionRuleInterface;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
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

    #[Override]
    protected function inputHtml(): string
    {
        $id = 'purchasable';

        $html = '';
        foreach (app(Purchasables::class)->getAllPurchasableElementTypes() as $purchasableType) {
            /** @var PurchasableInterface|string $purchasableType */
            $elements = null;
            if (!empty($this->_elementIds) && isset($this->_elementIds[$purchasableType]) && !empty($this->_elementIds[$purchasableType])) {
                $elements = $purchasableType::find()
                    ->id($this->_elementIds[$purchasableType])
                    ->status(null)
                    ->all();
            }

            $html .= Html::tag('div',
                Html::beginTag('div') .
                Html::tag('strong', $purchasableType::displayName()) .
                Html::endTag('div') .
                Cp::elementSelectHtml([
                    'name' => Html::namespaceInputName($purchasableType, 'elementIds'),
                    'elements' => $elements,
                    'elementType' => $purchasableType,
                    'sources' => null,
                    'criteria' => null,
                    'single' => false,
                ])
            );
        }

        return Html::hiddenLabel($this->getLabel(), $id) .
            Html::tag('div',
                $html,
                [
                    'class' => ['flex', 'flex-start'],
                ]
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
