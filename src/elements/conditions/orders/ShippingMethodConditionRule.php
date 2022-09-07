<?php

namespace craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use yii\db\QueryInterface;
use craft\commerce\Plugin;

/**
 * Element status condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class ShippingMethodConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
	/**
	 * @inheritdoc
	 */
	public function getLabel(): string
	{
		return Craft::t('app', 'Shipping Method');
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
	protected function options(): array
	{
		//return Plugin::getInstance()->getShippingMethods()->getAllShippingMethods();
		$options = [];
		foreach (Plugin::getInstance()->getShippingMethods()->getAllShippingMethods() as $method) {
			$options[$method->handle] = $method->name;
		}
		
		return $options;
			
	}

	/**
	 * @inheritdoc
	 */
	public function modifyQuery(QueryInterface $query): void
	{
		/** @var ElementQueryInterface $query */
		$query->shippingMethod($this->paramValue());
	}

	/**
	 * @inheritdoc
	 */
	public function matchElement(ElementInterface $element): bool
	{
		return $this->matchValue($element->shippingMethod->handle);
	}
}
