<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\stats\TopProducts as TopProductsStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use craft\web\assets\admintable\AdminTableAsset;

/**
 * Top Products widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopProducts extends Widget
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
     * @var string Options 'revenue', 'qty'.
     */
    public $type;

    /**
     * @var array|null
     */
    public $revenueOptions = [
        TopProductsStat::REVENUE_OPTION_DISCOUNT,
        TopProductsStat::REVENUE_OPTION_TAX_INCLUDED,
        TopProductsStat::REVENUE_OPTION_TAX,
        TopProductsStat::REVENUE_OPTION_SHIPPING,
    ];

    /**
     * @var TopProductsStat
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
     * @var array
     */
    private $_revenueCheckboxOptions;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->_typeOptions = [
            TopProductsStat::TYPE_QTY => Craft::t('commerce', 'Qty'),
            TopProductsStat::TYPE_REVENUE => Craft::t('commerce', 'Revenue'),
        ];

        $this->_revenueCheckboxOptions = [
            [
                'value' => TopProductsStat::REVENUE_OPTION_DISCOUNT,
                'label' => Craft::t('commerce', 'Discount'),
                'checked' => in_array(TopProductsStat::REVENUE_OPTION_DISCOUNT, $this->revenueOptions, true),
                'instructions' => Craft::t('commerce', 'Include line item discounts in product revenue calculation.'),
            ],
            [
                'value' => TopProductsStat::REVENUE_OPTION_TAX_INCLUDED,
                'label' => Craft::t('commerce', 'Tax (inc)'),
                'checked' => in_array(TopProductsStat::REVENUE_OPTION_TAX_INCLUDED, $this->revenueOptions, true),
                'instructions' => Craft::t('commerce', 'Include inclusive line item tax in product revenue calculation.'),
            ],
            [
                'value' => TopProductsStat::REVENUE_OPTION_TAX,
                'label' => Craft::t('commerce', 'Tax'),
                'checked' => in_array(TopProductsStat::REVENUE_OPTION_TAX, $this->revenueOptions, true),
                'instructions' => Craft::t('commerce', 'Include line item tax in product revenue calculation.'),
            ],
            [
                'value' => TopProductsStat::REVENUE_OPTION_SHIPPING,
                'label' => Craft::t('commerce', 'Shipping'),
                'checked' => in_array(TopProductsStat::REVENUE_OPTION_SHIPPING, $this->revenueOptions, true),
                'instructions' => Craft::t('commerce', 'Include line item shipping costs in product revenue calculation.'),
            ],
        ];

        switch ($this->type) {
            case 'revenue':
            {
                $this->_title = Craft::t('commerce', 'Top Products by Revenue');
                break;
            }
            case 'qty':
            {
                $this->_title = Craft::t('commerce', 'Top Products by Qty Sold');
                break;
            }
            default:
            {
                $this->_title = Craft::t('commerce', 'Top Products');
                break;
            }
        }

        $this->dateRange = !$this->dateRange ? TopProductsStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TopProductsStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->revenueOptions
        );
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders') && Craft::$app->getUser()->checkPermission('commerce-manageProducts');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Top Products');
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

        return $view->renderTemplate('commerce/_components/widgets/products/top/body', [
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

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/products/top/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'typeOptions' => $this->_typeOptions,
            'revenueOptions' => $this->_revenueCheckboxOptions,
            'isRevenueOptionsEnabled' => $this->type === TopProductsStat::TYPE_REVENUE
        ]);
    }
}
