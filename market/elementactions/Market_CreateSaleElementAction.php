<?php
namespace Craft;

class Market_CreateSaleElementAction extends BaseElementAction
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
		handle: 'Market_CreateSale',
		batch: true,
		activate: function(\$selectedItems)
		{
			Craft.redirectTo(Craft.getUrl('market/promotions/sales/new', 'productIds='+Craft.elementIndex.getSelectedElementIds().join('|')));
		}
	});
})();
EOT;

		craft()->templates->includeJs($js);
	}

	protected function defineParams()
	{
		return [];
	}
}