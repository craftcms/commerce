<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\purchasables;

use Craft;
use craft\base\conditions\BaseConditionRule;
use craft\base\ElementInterface;
use craft\commerce\base\Purchasable;
use craft\elements\Category;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\Cp;
use craft\helpers\Html;

/**
 * Catalog Pricing Rule Purchasable Category Condition Rule
 *
 * @todo Remove this rule once the standard Related To condition rule supports source/target/either relationship type selection
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRulePurchasableCategoryConditionRule extends BaseConditionRule implements ElementConditionRuleInterface
{
    public const CATEGORY_RELATIONSHIP_TYPE_SOURCE = 'sourceElement';
    public const CATEGORY_RELATIONSHIP_TYPE_TARGET = 'targetElement';
    public const CATEGORY_RELATIONSHIP_TYPE_BOTH = 'element';

    /**
     * @var string
     */
    public string $categoryRelationshipType = self::CATEGORY_RELATIONSHIP_TYPE_BOTH;

    /**
     * @var array|null
     */
    public ?array $elementIds = null;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return $this->getElementType() === Category::class
            ? Craft::t('commerce', 'Purchasable Categories')
            : Craft::t('commerce', 'Purchasable Entries');
    }

    /**
     * Returns the element type the rule selects.
     *
     * Rules with categories selected keep selecting categories, so existing selections aren't lost.
     * Every other rule selects entries, which is the only option in Commerce 6.
     *
     * @return class-string<Category|Entry>
     * @since 5.7.8
     */
    public function getElementType(): string
    {
        if (!empty($this->elementIds) && Category::find()->id($this->elementIds)->status(null)->site('*')->exists()) {
            return Category::class;
        }

        return Entry::class;
    }


    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'elementIds' => $this->elementIds,
            'categoryRelationshipType' => $this->categoryRelationshipType,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['elementIds', 'categoryRelationshipType'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(): string
    {
        $id = 'cpr-purchasable-category';

        $elementType = $this->getElementType();
        $isCategory = $elementType === Category::class;
        $elements = !empty($this->elementIds) ? $elementType::find()->id($this->elementIds)->status(null)->all() : [];
        return Html::hiddenLabel($this->getLabel(), $id) .
            Html::tag('div',
                Html::tag('div',
                    Cp::elementSelectHtml([
                        'name' => 'elementIds',
                        'elements' => $elements,
                        'elementType' => $elementType,
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
                Html::a(Craft::t('app', 'Advanced'), null, [
                    'class' => array_filter(['fieldtoggle', $this->categoryRelationshipType !== self::CATEGORY_RELATIONSHIP_TYPE_BOTH ? 'expanded' : '']),
                    'data-target' => 'category-relationship-type-advanced',
                ]) .
                Html::tag('div',
                    Cp::selectHtml([
                        'id' => 'categoryRelationshipType',
                        'name' => 'categoryRelationshipType',
                        'label' => $isCategory ? Craft::t('commerce', 'Categories Relationship Type') : Craft::t('commerce', 'Entries Relationship Type'),
                        'instructions' => $isCategory
                            ? Craft::t('commerce', 'How the Purchasables and Categories are related, which determines the matching items. See [Relations Terminology]({link}).', [
                                'link' => 'https://craftcms.com/docs/4.x/relations.html#terminology',
                            ])
                            : Craft::t('commerce', 'How the Purchasables and Entries are related, which determines the matching items. See [Relations Terminology]({link}).', [
                                'link' => 'https://craftcms.com/docs/5.x/system/relations.html#terminology',
                            ]),
                        'options' => $isCategory ? [
                            self::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Craft::t('commerce', 'Source - The purchasable relationship field is on the category'),
                            self::CATEGORY_RELATIONSHIP_TYPE_TARGET => Craft::t('commerce', 'Target - The category relationship field is on the purchasable'),
                            self::CATEGORY_RELATIONSHIP_TYPE_BOTH => Craft::t('commerce', 'Either (Default) - The relationship field is on the purchasable or the category'),
                        ] : [
                            self::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Craft::t('commerce', 'Source - The purchasable relationship field is on the entry'),
                            self::CATEGORY_RELATIONSHIP_TYPE_TARGET => Craft::t('commerce', 'Target - The entry relationship field is on the purchasable'),
                            self::CATEGORY_RELATIONSHIP_TYPE_BOTH => Craft::t('commerce', 'Either (Default) - The relationship field is on the purchasable or the entry'),
                        ],
                        'value' => $this->categoryRelationshipType,
                    ]),
                    [
                        'class' => $this->categoryRelationshipType === self::CATEGORY_RELATIONSHIP_TYPE_BOTH ? 'hidden' : '',
                        'id' => 'category-relationship-type-advanced',
                    ]
                ),
                ['style' => ['width' => '100%']]
            )
            ;
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        if ($this->elementIds === null) {
            return;
        }

        $query->andRelatedTo([$this->categoryRelationshipType => $this->elementIds]);
    }

    /**
     * @inheritdoc
     */
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
