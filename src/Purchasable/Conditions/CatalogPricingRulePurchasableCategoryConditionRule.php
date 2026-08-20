<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Conditions;

use craft\elements\Category;
use craft\helpers\Cp;
use CraftCms\Cms\Condition\BaseConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;

use function CraftCms\Cms\t;

/**
 * @todo Remove this rule once the standard Related To condition rule supports source/target/either relationship type selection
 */
class CatalogPricingRulePurchasableCategoryConditionRule extends BaseConditionRule implements ElementConditionRuleInterface
{
    public const string CATEGORY_RELATIONSHIP_TYPE_SOURCE = 'sourceElement';

    public const string CATEGORY_RELATIONSHIP_TYPE_TARGET = 'targetElement';

    public const string CATEGORY_RELATIONSHIP_TYPE_BOTH = 'element';

    public string $categoryRelationshipType = self::CATEGORY_RELATIONSHIP_TYPE_BOTH;

    /** @var array<int>|null */
    public ?array $elementIds = null;

    public function getLabel(): string
    {
        return t('Purchasable Categories', category: 'commerce');
    }

    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'elementIds' => $this->elementIds,
            'categoryRelationshipType' => $this->categoryRelationshipType,
        ]);
    }

    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'elementIds' => ['nullable'],
            'categoryRelationshipType' => ['nullable', 'string'],
        ]);
    }

    protected function inputHtml(): string
    {
        $id = 'cpr-purchasable-category';

        $elements = !empty($this->elementIds) ? Category::find()->id($this->elementIds)->all() : [];

        return Html::hiddenLabel($this->getLabel(), $id) .
            Html::tag('div',
                Html::tag('div',
                    Cp::elementSelectHtml([
                        'name' => 'elementIds',
                        'elements' => $elements,
                        'elementType' => Category::class,
                        'sources' => null,
                        'criteria' => null,
                        'single' => false,
                    ])
                ),
                [
                    'class' => ['flex', 'flex-start'],
                ]
            ) .
            Html::tag('div',
                Html::a(t('Advanced'), null, [
                    'class' => array_filter(['fieldtoggle', $this->categoryRelationshipType !== self::CATEGORY_RELATIONSHIP_TYPE_BOTH ? 'expanded' : '']),
                    'data-target' => 'category-relationship-type-advanced',
                ]) .
                Html::tag('div',
                    Cp::selectHtml([
                        'id' => 'categoryRelationshipType',
                        'name' => 'categoryRelationshipType',
                        'label' => t('Categories Relationship Type', category: 'commerce'),
                        'instructions' => t('How the Purchasables and Categories are related, which determines the matching items. See [Relations Terminology]({link}).', ['link' => 'https://craftcms.com/docs/4.x/relations.html#terminology'], category: 'commerce'),
                        'options' => [
                            self::CATEGORY_RELATIONSHIP_TYPE_SOURCE => t('Source - The purchasable relationship field is on the category', category: 'commerce'),
                            self::CATEGORY_RELATIONSHIP_TYPE_TARGET => t('Target - The category relationship field is on the purchasable', category: 'commerce'),
                            self::CATEGORY_RELATIONSHIP_TYPE_BOTH => t('Either (Default) - The relationship field is on the purchasable or the category', category: 'commerce'),
                        ],
                        'value' => $this->categoryRelationshipType,
                    ]),
                    [
                        'class' => $this->categoryRelationshipType === self::CATEGORY_RELATIONSHIP_TYPE_BOTH ? 'hidden' : '',
                        'id' => 'category-relationship-type-advanced',
                    ]
                ),
                ['style' => ['width' => '100%']]
            );
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        if ($this->elementIds === null) {
            return;
        }

        $query->andRelatedTo([$this->categoryRelationshipType => $this->elementIds]);
    }

    public function matchElement(ElementInterface $element): bool
    {
        if ($this->elementIds === null) {
            return true;
        }

        return Purchasable::find()
            ->id($element->id ?: false)
            ->site('*')
            ->drafts($element->getIsDraft())
            ->provisionalDrafts($element->isProvisionalDraft)
            ->revisions($element->getIsRevision())
            ->status(null)
            ->relatedTo([$this->categoryRelationshipType => $this->elementIds])
            ->exists();
    }
}
