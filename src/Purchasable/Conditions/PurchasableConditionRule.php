<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Conditions;

use craft\helpers\Cp;
use CraftCms\Cms\Condition\BaseConditionRule;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Purchasables;

use function CraftCms\Cms\t;

class PurchasableConditionRule extends BaseConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var array<class-string, array<int>>|null
     *
     * @see getElementIds()
     * @see setElementIds()
     */
    private ?array $_elementIds = null;

    public function getLabel(): string
    {
        return t('Purchasable', category: 'commerce');
    }

    /** @param array<class-string, array<int>>|null $value */
    public function setElementIds(?array $value): void
    {
        $this->_elementIds = $value;
    }

    /** @return array<int>|null */
    public function getElementIds(): ?array
    {
        if ($this->_elementIds === null) {
            return null;
        }

        $elementIds = [];
        foreach ($this->_elementIds as $ids) {
            if (empty($ids)) {
                continue;
            }

            $elementIds = array_merge($elementIds, $ids);
        }

        return $elementIds;
    }

    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'elementIds' => $this->_elementIds,
        ]);
    }

    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'elementIds' => ['nullable'],
        ]);
    }

    protected function inputHtml(): string
    {
        $id = 'purchasable';

        $html = '';
        foreach (app(Purchasables::class)->getAllPurchasableElementTypes() as $purchasableType) {
            /** @var class-string<PurchasableInterface> $purchasableType */
            $elements = null;
            if (!empty($this->_elementIds[$purchasableType])) {
                $elements = $purchasableType::find()
                    ->id($this->_elementIds[$purchasableType])
                    ->site('*')
                    ->preferSites(array_filter([Cp::requestedSite()?->id]))
                    ->status(null)
                    ->unique()
                    ->all();
            }

            $html .= Html::tag('div',
                Html::beginTag('div') .
                Html::tag('strong', $purchasableType::displayName()) .
                Html::endTag('div') .
                FormFields::elementSelectHtml([
                    'name' => Html::namespaceInputName($purchasableType, 'elementIds'),
                    'elements' => $elements,
                    'elementType' => $purchasableType,
                    'sources' => null,
                    'criteria' => null,
                    'single' => false,
                    'showSiteMenu' => true,
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

    public function getExclusiveQueryParams(): array
    {
        return ['id'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $ids = $this->getElementIds();
        if ($ids === null) {
            return;
        }

        $query->id($ids);
    }

    public function matchElement(ElementInterface $element): bool
    {
        $ids = $this->getElementIds();
        if ($ids === null) {
            return true;
        }

        return in_array($element->id, $ids);
    }
}
