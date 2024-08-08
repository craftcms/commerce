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
use craft\helpers\Cp;
use craft\helpers\Html;

/**
 * Catalog Pricing Rule Purchasable Category Condition Rule
 * @TODO Remove this when standard Related To rules get more options.
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
        return Craft::t('commerce', 'Purchasable Categories');
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
                Html::a(Craft::t('app', 'Advanced'), null, [
                    'class' => array_filter(['fieldtoggle', $this->categoryRelationshipType !== self::CATEGORY_RELATIONSHIP_TYPE_BOTH ? 'expanded' : '']),
                    'data-target' => 'category-relationship-type-advanced',
                ]) .
                Html::tag('div',
                    Cp::selectHtml([
                        'id' => 'categoryRelationshipType',
                        'name' => 'categoryRelationshipType',
                        'label' => Craft::t('commerce', 'Categories Relationship Type'),
                        'instructions' => Craft::t('commerce', 'How the Purchasables and Categories are related, which determines the matching items. See [Relations Terminology]({link}).', [
                            'link' => 'https://craftcms.com/docs/4.x/relations.html#terminology',
                        ]),
                        'options' => [
                            self::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Craft::t('commerce', 'Source - The purchasable relationship field is on the category'),
                            self::CATEGORY_RELATIONSHIP_TYPE_TARGET => Craft::t('commerce', 'Target - The category relationship field is on the purchasable'),
                            self::CATEGORY_RELATIONSHIP_TYPE_BOTH => Craft::t('commerce', 'Either (Default) - The relationship field is on the purchasable or the category'),
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
