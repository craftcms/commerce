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
use craft\commerce\stats\TotalOrdersByCountry as TotalOrdersByCountryStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;

/**
 * Total Orders By Country widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalOrdersByCountry extends Widget
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
     * @var string Options 'billing', 'shippinh'.
     */
    public $type;

    /**
     * @var TotalOrdersByCountryStat
     */
    private $_stat;

    /**
     * @var string
     */
    private $_title;

    /**
     * @var array
     */
    private $_typeOptions;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->_typeOptions = [
            'billing' => Plugin::t('Billing'),
            'shipping' => Plugin::t('Shipping'),
        ];

        if ($this->type == 'billing') {
            $this->_title = Plugin::t('Total Orders by Billing Country');
        } else {
            $this->_title = Plugin::t('Total Order by Shipping Country');
            $this->type = 'shipping';
        }

        $this->dateRange = !$this->dateRange ? TotalOrdersByCountryStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TotalOrdersByCountryStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate),
            DateTimeHelper::toDateTime($this->endDate)
        );
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->_title;
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle()
    {
        return $this->_stat->getDateRangeWording();
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Plugin::t( 'Total Orders by Country');
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
    public function getBodyHtml()
    {
        $stats = $this->_stat->get();

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        $id = 'total-revenue' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        if (empty($stats)) {
            // TODO no stats available message
            return '';
        }

        $labels = ArrayHelper::getColumn($stats, 'name', false);
        $totalOrders = ArrayHelper::getColumn($stats, 'total', false);

        return $view->renderTemplate('commerce/_components/widgets/orders/country/body',
            compact(
                'stats',
                'namespaceId',
                'labels',
                'totalOrders'
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        $id = 'total-orders' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/orders/country/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'typeOptions' => $this->_typeOptions
        ]);
    }
}
