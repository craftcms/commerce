<?php
namespace Craft;

class Commerce_CreateSaleElementAction extends BaseElementAction
{

	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc IComponentType::getName()
	 *
	 * @return string
	 */
	public function getName ()
	{
		return Craft::t('Create sale from selected products…');
	}

	/**
	 * @inheritDoc IElementAction::isDestructive()
	 *
	 * @return bool
	 */
	public function isDestructive ()
	{
		return false;
	}

	/**
	 * @inheritDoc IElementAction::getTriggerHtml()
	 *
	 * @return string|null
	 */
	public function getTriggerHtml ()
	{
		$js = <<<EOT
(function()
{
	var trigger = new Craft.ElementActionTrigger({
		handle: 'Commerce_CreateSale',
		batch: true,
		activate: function(\$selectedItems)
		{
			Craft.redirectTo(Craft.getUrl('commerce/promotions/sales/new', 'productIds='+Craft.elementIndex.getSelectedElementIds().join('|')));
		}
	});
})();
EOT;

		craft()->templates->includeJs($js);
	}

	/**
	 * @inheritDoc BaseElementAction::defineParams()
	 *
	 * @return array
	 */
	protected function defineParams ()
	{
		return [];
	}
}