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
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Purchasables;
use Override;

use function CraftCms\Cms\t;

/**
 * @method array|string|null paramValue(?callable $normalizeValue = null)
 */
class HasPurchasableConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
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

    public function modifyQuery(ElementQueryInterface $query): void
    {
        if ($this->getElementId() === null) {
            return;
        }

        /** @var OrderQuery $query */
        $query->hasPurchasables([$this->getElementId()]);
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

    #[Override]
    protected function inputHtml(): string
    {
        $id = 'purchasable-type';

        return Html::hiddenLabel($this->getLabel(), $id) .
            Html::tag('div',
                Cp::selectHtml([
                    'id' => $id,
                    'name' => 'purchasableType',
                    'options' => $this->purchasableTypeOptions(),
                    'value' => $this->purchasableType,
                    'inputAttributes' => [
                        'hx' => [
                            'post' => Url::actionUrl('conditions/render'),
                        ],
                    ],
                ]) .
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

    #[Override]
    protected function elementSelectConfig(): array
    {
        return array_merge(parent::elementSelectConfig(), [
            'showSiteMenu' => true,
        ]);
    }
}
