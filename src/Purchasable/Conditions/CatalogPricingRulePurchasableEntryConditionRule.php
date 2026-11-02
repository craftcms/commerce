<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Conditions;

use CraftCms\Cms\Condition\BaseConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\ElementSelect;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

/**
 * @todo Remove this rule once the standard Related To condition rule supports source/target/either relationship type selection
 */
class CatalogPricingRulePurchasableEntryConditionRule extends BaseConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public const string ENTRY_RELATIONSHIP_TYPE_SOURCE = 'sourceElement';

    public const string ENTRY_RELATIONSHIP_TYPE_TARGET = 'targetElement';

    public const string ENTRY_RELATIONSHIP_TYPE_BOTH = 'element';

    public string $entryRelationshipType = self::ENTRY_RELATIONSHIP_TYPE_BOTH;

    /** @var array<int>|null */
    public ?array $elementIds = null;

    public function getLabel(): string
    {
        return t('Purchasable Entries', category: 'commerce');
    }

    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'elementIds' => $this->elementIds,
            'entryRelationshipType' => $this->entryRelationshipType,
        ]);
    }

    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'elementIds' => ['nullable'],
            'entryRelationshipType' => ['nullable', 'string'],
        ]);
    }

    /** @return list<Node> */
    protected function inputNodes(): array
    {
        return [
            Field::make($this->getLabel(), ElementSelect::make('elementIds')->elementType(Entry::class)->value($this->elementIds ?? [])),
            Field::make(t('Entries Relationship Type', category: 'commerce'), Choice::make('entryRelationshipType')
                ->options([
                    ['value' => self::ENTRY_RELATIONSHIP_TYPE_SOURCE, 'label' => t('Source - The purchasable relationship field is on the entry', category: 'commerce')],
                    ['value' => self::ENTRY_RELATIONSHIP_TYPE_TARGET, 'label' => t('Target - The entry relationship field is on the purchasable', category: 'commerce')],
                    ['value' => self::ENTRY_RELATIONSHIP_TYPE_BOTH, 'label' => t('Either (Default) - The relationship field is on the purchasable or the entry', category: 'commerce')],
                ])
                ->withoutPlaceholder()
                ->value($this->entryRelationshipType))
                ->instructions(t('How the Purchasables and Entries are related, which determines the matching items. See [Relations Terminology]({link}).', ['link' => 'https://craftcms.com/docs/5.x/system/relations.html#terminology'], category: 'commerce')),
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

        ElementQuery::applyRelatedTo($query, [$this->entryRelationshipType => $this->elementIds], $elementQuery);
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
            ->relatedTo([$this->entryRelationshipType => $this->elementIds])
            ->exists();
    }
}
