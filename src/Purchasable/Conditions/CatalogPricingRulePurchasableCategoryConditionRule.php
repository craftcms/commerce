<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Conditions;

use craft\elements\Category;
use CraftCms\Cms\Condition\BaseConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\ElementSelect;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

/**
 * @todo Remove this rule once the standard Related To condition rule supports source/target/either relationship type selection
 */
class CatalogPricingRulePurchasableCategoryConditionRule extends BaseConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
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

    /** @return list<Node> */
    protected function inputNodes(): array
    {
        return [
            Field::make($this->getLabel(), ElementSelect::make('elementIds')->elementType(Category::class)->value($this->elementIds ?? [])),
            Field::make(t('Categories Relationship Type', category: 'commerce'), Choice::make('categoryRelationshipType')
                ->options([
                    ['value' => self::CATEGORY_RELATIONSHIP_TYPE_SOURCE, 'label' => t('Source - The purchasable relationship field is on the category', category: 'commerce')],
                    ['value' => self::CATEGORY_RELATIONSHIP_TYPE_TARGET, 'label' => t('Target - The category relationship field is on the purchasable', category: 'commerce')],
                    ['value' => self::CATEGORY_RELATIONSHIP_TYPE_BOTH, 'label' => t('Either (Default) - The relationship field is on the purchasable or the category', category: 'commerce')],
                ])
                ->withoutPlaceholder()
                ->value($this->categoryRelationshipType))
                ->instructions(t('How the Purchasables and Categories are related, which determines the matching items. See [Relations Terminology]({link}).', ['link' => 'https://craftcms.com/docs/4.x/relations.html#terminology'], category: 'commerce')),
        ];
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        if ($this->elementIds === null) {
            return;
        }

        ElementQuery::applyRelatedTo($query, [$this->categoryRelationshipType => $this->elementIds], $elementQuery);
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
