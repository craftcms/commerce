<?php
namespace Craft;

class Market_SetVariantValuesElementAction extends BaseElementAction
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Set Variant Values…');
    }

    /**
     * @inheritDoc IElementAction::getTriggerHtml()
     *
     * @return string|null
     */
    public function getTriggerHtml()
    {
        $js = <<<EOT
(function()
{
	var trigger = new Craft.ElementActionTrigger({
		handle: 'Market_SetVariantValues',
		batch: true,
		activate: function(\$selectedItems)
		{
			var modal = new Craft.SetVariantValuesModal(Craft.elementIndex.getSelectedElementIds(), {
				onSubmit: function()
				{
					//Craft.elementIndex.submitAction('Market_SetVariantValues', Garnish.getPostData(modal.\$container));
					modal.hide();

					return false;
				}
			});
		}
	});
})();
EOT;

        craft()->templates->includeJs($js);
    }


    /**
     * @inheritDoc IElementAction::performAction()
     *
     * @param ElementCriteriaModel $criteria
     *
     * @return bool
     */
    public function performAction(ElementCriteriaModel $criteria)
    {
        $variants = $criteria->find();
        foreach($variants as $variant){
            $variant->price = 11;
            craft()->market_variant->save($variant);
        }

        $this->setMessage(Craft::t('Price set to 11.00'));

        return true;

    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc BaseElementAction::defineParams()
     *
     * @return array
     */
    protected function defineParams()
    {
        return array(
            'label' => array(AttributeType::String, 'default' => Craft::t('Set Values')),
        );
    }
}