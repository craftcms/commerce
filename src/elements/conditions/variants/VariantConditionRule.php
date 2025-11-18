<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\variants;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Variant;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

/**
 * Variants Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.5.0
 */
class VariantConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    protected function elementType(): string
    {
        return Variant::class;
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Product Variant');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['id'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var VariantQuery $query */
        $query->id($this->getElementIds());
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Variant $element */
        return $this->matchValue($element->getId());
    }

    /**
     * @inheritdoc
     */
    protected function allowMultiple(): bool
    {
        return true;
    }

    /**
     * @inerhitdoc
     */
    protected function elementSelectConfig(): array
    {
        return array_merge(parent::elementSelectConfig(), [
            'showSiteMenu' => true,
        ]);
    }
}
