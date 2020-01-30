<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\Plugin;
use craft\commerce\stats\NewCustomers as NewCustomersStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;

/**
 * New Customers widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class NewCustomers extends Widget
{
    /**
     * @var int|\DateTime|null
     */
    public $startDate;

    /**
     * @var int|\DateTime|null
     */
    public $endDate;

    /**
     * @var string|null
     */
    public $dateRange;

    /**
     * @var null|NewCustomersStat
     */
    private $_stat;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->dateRange = !$this->dateRange ? NewCustomersStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new NewCustomersStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate),
            DateTimeHelper::toDateTime($this->endDate)
        );
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageCustomers');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Plugin::t( 'New Customers');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml()
    {
        $number = $this->_stat->get();
        $timeFrame = $this->_stat->getDateRangeWording();

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        return $view->renderTemplate('commerce/_components/widgets/customers/new/body', compact('number', 'timeFrame'));
    }

    /**
     * @inheritDoc
     */
    public static function maxColspan()
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        $id = 'new-customers' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/customers/new/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
        ]);
    }
}
