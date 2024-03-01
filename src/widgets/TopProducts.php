<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\base\StatWidgetTrait;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\stats\TopProducts as TopProductsStat;
use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\models\Site;
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
    use StatWidgetTrait;

    /**
     * @var string|null Options 'revenue', 'qty'.
     */
    public ?string $type = null;

    /**
     * @var array|null
     */
    public ?array $revenueOptions = [
        TopProductsStat::REVENUE_OPTION_DISCOUNT,
        TopProductsStat::REVENUE_OPTION_TAX_INCLUDED,
        TopProductsStat::REVENUE_OPTION_TAX,
        TopProductsStat::REVENUE_OPTION_SHIPPING,
    ];

    /**
     * @var TopProductsStat
     */
    private TopProductsStat $_stat;

    /**
     * @var string
     */
    private string $_title;

    /**
     * @var array
     */
    private array $_typeOptions;

    /**
     * @var array
     */
    private array $_revenueCheckboxOptions;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        if (!(isset($this->storeId)) || !$this->storeId) {
            /** @var Site|StoreBehavior $site */
            $site = Cp::requestedSite();
            $this->storeId = $site->getStore()->id;
        }

        $this->_typeOptions = [
            TopProductsStat::TYPE_QTY => Craft::t('commerce', 'Qty'),
            TopProductsStat::TYPE_REVENUE => Craft::t('commerce', 'Revenue'),
        ];

        $this->_revenueCheckboxOptions = [
            [
                'value' => TopProductsStat::REVENUE_OPTION_DISCOUNT,
                'label' => Craft::t('commerce', 'Discount'),
                'checked' => in_array(TopProductsStat::REVENUE_OPTION_DISCOUNT, $this->revenueOptions, true),
                'instructions' => Craft::t('commerce', 'Include line item discounts.'),
            ],
            [
                'value' => TopProductsStat::REVENUE_OPTION_TAX_INCLUDED,
                'label' => Craft::t('commerce', 'Tax (inc)'),
                'checked' => in_array(TopProductsStat::REVENUE_OPTION_TAX_INCLUDED, $this->revenueOptions, true),
                'instructions' => Craft::t('commerce', 'Include built-in line item tax.'),
            ],
            [
                'value' => TopProductsStat::REVENUE_OPTION_TAX,
                'label' => Craft::t('commerce', 'Tax'),
                'checked' => in_array(TopProductsStat::REVENUE_OPTION_TAX, $this->revenueOptions, true),
                'instructions' => Craft::t('commerce', 'Include separate line item tax.'),
            ],
            [
                'value' => TopProductsStat::REVENUE_OPTION_SHIPPING,
                'label' => Craft::t('commerce', 'Shipping'),
                'checked' => in_array(TopProductsStat::REVENUE_OPTION_SHIPPING, $this->revenueOptions, true),
                'instructions' => Craft::t('commerce', 'Include line item shipping costs.'),
            ],
        ];

        $this->_title = match ($this->type) {
            'revenue' => Craft::t('commerce', 'Top Products by Revenue'),
            'qty' => Craft::t('commerce', 'Top Products by Qty Sold'),
            default => Craft::t('commerce', 'Top Products'),
        };

        $this->dateRange = !isset($this->dateRange) || !$this->dateRange ? TopProductsStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TopProductsStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->revenueOptions,
            $this->storeId
        );

        if (!empty($this->orderStatuses)) {
            $this->_stat->setOrderStatuses($this->orderStatuses);
        }
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
        return Craft::t('commerce', 'Top Products');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        return $this->_title;
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        return $this->_stat->getDateRangeWording();
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $stats = $this->_stat->get();

        if (empty($stats)) {
            return Html::tag('p', Craft::t('commerce', 'No stats available.'), ['class' => 'zilch']);
        }

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);
        $view->registerAssetBundle(AdminTableAsset::class);

        $revenueOptions = [
            TopProductsStat::REVENUE_OPTION_DISCOUNT,
            TopProductsStat::REVENUE_OPTION_TAX_INCLUDED,
            TopProductsStat::REVENUE_OPTION_TAX,
            TopProductsStat::REVENUE_OPTION_SHIPPING,
        ];
        $revenueColumnHandle = 'revenue';
        if ($this->type === TopProductsStat::TYPE_REVENUE && count(array_intersect($revenueOptions, $this->revenueOptions)) !== count($revenueOptions)) {
            $revenueColumnHandle = 'revenue_custom';
        }

        return $view->renderTemplate('commerce/_components/widgets/products/top/body', [
            'stats' => $stats,
            'revenueColumnHandle' => $revenueColumnHandle,
            'type' => $this->type,
            'typeLabel' => $this->_typeOptions[$this->type] ?? '',
            'id' => 'top-products' . StringHelper::randomString(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $id = 'top-products' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/products/top/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'typeOptions' => $this->_typeOptions,
            'revenueOptions' => $this->_revenueCheckboxOptions,
            'orderStatuses' => $this->getOrderStatusOptions(),
            'isRevenueOptionsEnabled' => $this->type === TopProductsStat::TYPE_REVENUE,
        ]);
    }
}
