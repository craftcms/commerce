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
use craft\commerce\stats\TotalOrdersByCountry as TotalOrdersByCountryStat;
use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\models\Site;

/**
 * Total Orders By Country widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property-read string $subtitle
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalOrdersByCountry extends Widget
{
    use StatWidgetTrait;

    /**
     * @var string Options 'billing', 'shipping'.
     */
    public string $type;

    /**
     * @var TotalOrdersByCountryStat
     */
    private TotalOrdersByCountryStat $_stat;

    /**
     * @var string
     */
    private string $_title;

    /**
     * @var array
     */
    private array $_typeOptions;

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
            'billing' => Craft::t('commerce', 'Billing'),
            'shipping' => Craft::t('commerce', 'Shipping'),
        ];

        if (isset($this->type) && $this->type == 'billing') {
            $this->_title = Craft::t('commerce', 'Total Orders by Billing Country');
        } else {
            $this->_title = Craft::t('commerce', 'Total Orders by Shipping Country');
            $this->type = 'shipping';
        }

        $this->dateRange = !isset($this->dateRange) || !$this->dateRange ? TotalOrdersByCountryStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TotalOrdersByCountryStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->storeId
        );

        if (!empty($this->orderStatuses)) {
            $this->_stat->setOrderStatuses($this->orderStatuses);
        }
    }

    /**
     * @inheritDoc
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
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Total Orders by Country');
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
    public function getBodyHtml(): ?string
    {
        $stats = $this->_stat->get();

        if (empty($stats)) {
            return Html::tag('p', Craft::t('commerce', 'No stats available.'), ['class' => 'zilch']);
        }

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        $id = 'total-revenue' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

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
    public function getSettingsHtml(): ?string
    {
        $id = 'total-orders' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/orders/country/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'orderStatuses' => $this->getOrderStatusOptions(),
            'widget' => $this,
            'typeOptions' => $this->_typeOptions,
        ]);
    }
}
