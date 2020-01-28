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
use craft\commerce\stats\TopCustomers as TopCustomersStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use craft\web\assets\admintable\AdminTableAsset;

/**
 * Top Customers widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopCustomers extends Widget
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
     * @var string Options 'total', 'average'.
     */
    public $type;

    /**
     * @var TopCustomersStat
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
        $this->_typeOptions = [
            'total' => Plugin::t('Total'),
            'average' => Plugin::t('Average'),
        ];

        switch ($this->type) {
            case 'average':
            {
                $this->_title = Plugin::t('Top Customers by Average Order');
                break;
            }
            case 'total':
            {
                $this->_title = Plugin::t('Top Customers by Total Revenue');
                break;
            }
            default:
            {
                $this->_title = Plugin::t('Top Customers');
                break;
            }
        }
        $this->dateRange = !$this->dateRange ? TopCustomersStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TopCustomersStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate),
            DateTimeHelper::toDateTime($this->endDate)
        );

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders') && Craft::$app->getUser()->checkPermission('commerce-manageCustomers');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Plugin::t( 'Top Customers');
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
    public function getBodyHtml()
    {
        $stats = $this->_stat->get();

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);
        $view->registerAssetBundle(AdminTableAsset::class);

        return $view->renderTemplate('commerce/_components/widgets/customers/top/body', [
            'stats' => $stats,
            'type' => $this->type,
            'typeLabel' => $this->_typeOptions[$this->type] ?? '',
            'id' => 'top-products' . StringHelper::randomString(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        $id = 'top-products' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/customers/top/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'typeOptions' => $this->_typeOptions,
        ]);
    }
}
