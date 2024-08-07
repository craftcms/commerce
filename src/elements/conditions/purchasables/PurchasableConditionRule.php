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
use craft\commerce\base\PurchasableInterface;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\Html;

/**
 * Purchasable Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableConditionRule extends BaseConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var array|null
     * @see getElementIds()
     * @see setElementIds
     */
    private ?array $_elementIds = null;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Purchasable');
    }

    /**
     * @param $value
     * @return void
     */
    public function setElementIds($value): void
    {
        $this->_elementIds = $value;
    }

    /**
     * @return array|null
     */
    public function getElementIds(): ?array
    {
        if ($this->_elementIds === null) {
            return null;
        }

        $elementIds = [];
        foreach ($this->_elementIds as $ids) {
            if (!is_array($ids) || empty($ids)) {
                continue;
            }

            $elementIds = array_merge($elementIds, $ids);
        }

        return $elementIds;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'elementIds' => $this->_elementIds,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['elementIds'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(): string
    {
        $id = 'purchasable';

        $html = '';
        foreach (Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes() as $purchasableType) {
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
        $ids = $this->getElementIds();
        if ($ids === null) {
            return;
        }

        $query->id($ids);
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $ids = $this->getElementIds();
        if ($ids === null) {
            return true;
        }

        if (!is_array($ids)) {
            return false;
        }

        return in_array($element->id, $ids);
    }
}
