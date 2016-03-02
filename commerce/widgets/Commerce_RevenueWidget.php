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
        $dateRange = false;

        $settings = $this->getSettings();

        $dateRanges = craft()->charts->getDateRanges();

        if(!empty($dateRanges[$settings->dateRange]))
        {
            $dateRange = $dateRanges[$settings->dateRange]['label'];
        }

        $options = [
            'dateRange' => $settings->dateRange
        ];

        craft()->templates->includeCssResource('commerce/CommerceRevenueWidget.css');
        craft()->templates->includeJsResource('commerce/js/CommerceRevenueWidget.js');

        $js = 'new Craft.CommerceRevenueWidget('.$this->model->id.', '.JsonHelper::encode($options).');';

        craft()->templates->includeJs($js);

        return craft()->templates->render('commerce/_components/widgets/Revenue/body', array(
            'dateRange' => $dateRange
        ));
    }

    /**
     * @inheritDoc ISavableComponentType::getSettingsHtml()
     *
     * @return string
     */
    public function getSettingsHtml()
    {
        $dateRanges = craft()->charts->getDateRanges();

        $dateRangeOptions = [];

        foreach($dateRanges as $key => $dateRange)
        {
            $dateRangeOptions[] = [
                'value' => $key,
                'label' => $dateRange['label']
            ];
        }

        return craft()->templates->render('commerce/_components/widgets/Revenue/settings', array(
            'settings' => $this->getSettings(),
            'dateRangeOptions' => $dateRangeOptions
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
