<?php

namespace Craft;

class Commerce_RevenueWidget extends BaseWidget
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
        return Craft::t('Commerce Revenue');
    }

    /**
     * @inheritDoc IWidget::getBodyHtml()
     *
     * @return string|false
     */
    public function getBodyHtml()
    {
        $settings = $this->getSettings();

        craft()->templates->includeJsResource('commerce/js/CommerceRevenueWidget.js');

        $js = 'new Craft.CommerceRevenueWidget('.$this->model->id.', '.JsonHelper::encode($settings).');';
        craft()->templates->includeJs($js);

        return craft()->templates->render('commerce/_components/widgets/Revenue/body');
    }

    /**
     * @inheritDoc ISavableComponentType::getSettingsHtml()
     *
     * @return string
     */
    public function getSettingsHtml()
    {
        return craft()->templates->render('commerce/_components/widgets/Revenue/settings', array(
            'settings' => $this->getSettings()
        ));
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc BaseSavableComponentType::defineSettings()
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'dateRange'   => AttributeType::String,
        );
    }
}
