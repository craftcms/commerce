<?php

namespace Craft;

class Commerce_RevenueWidget extends BaseWidget
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc IComponentType::isSelectable()
     *
     * @return bool
     */
    public function isSelectable()
    {
        // This widget is only available to users that can manage orders
        return craft()->userSession->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Revenue');
    }

    /**
     * @inheritDoc IWidget::getIconPath()
     *
     * @return string
     */
    public function getIconPath()
    {
        return craft()->path->getPluginsPath().'commerce/resources/icon-mask.svg';
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

        $dateRanges = ChartHelper::getDateRanges();

        if(!empty($dateRanges[$settings->dateRange]))
        {
            $dateRange = $dateRanges[$settings->dateRange]['label'];
        }

        $options = [
            'dateRange' => $settings->dateRange
        ];

        craft()->templates->includeCssResource('commerce/CommerceRevenueWidget.css');
        craft()->templates->includeJsResource('commerce/js/CommerceRevenueWidget.js');

        $js = 'new Craft.Commerce.RevenueWidget('.$this->model->id.', '.JsonHelper::encode($options).');';

        craft()->templates->includeJs($js);

        return '<div class="chart hidden"></div>';
    }

    /**
     * @inheritDoc ISavableComponentType::getSettingsHtml()
     *
     * @return string
     */
    public function getSettingsHtml()
    {
        $dateRanges = ChartHelper::getDateRanges();

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
