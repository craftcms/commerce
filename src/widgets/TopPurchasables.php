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
use craft\commerce\stats\TopPurchasables as TopPurchasablesStat;
use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\models\Site;
use craft\web\assets\admintable\AdminTableAsset;

/**
 * Top Purchasables widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopPurchasables extends Widget
{
    use StatWidgetTrait;

    /**
     * @var string|null Options 'revenue', 'qty'.
     */
    public ?string $type = null;

    /**
     * @var string options 'description', 'sku'.
     */
    public string $nameField;

    /**
     * @var TopPurchasablesStat
     */
    private TopPurchasablesStat $_stat;

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
    private array $_nameFieldOptions;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        if (!(isset($this->storeId)) || !$this->storeId) {
            /** @var Site|StoreBehavior $site */
            $site = Cp::requestedSite();
            $this->storeId = $site->getStore()->id;
        }

        $this->nameField = isset($this->nameField) ?: 'description';

        $this->_nameFieldOptions = [
            'description' => Craft::t('commerce', 'Description'),
            'sku' => Craft::t('commerce', 'SKU'),
        ];

        $this->_typeOptions = [
            'qty' => Craft::t('commerce', 'Qty'),
            'revenue' => Craft::t('commerce', 'Revenue'),
        ];

        $this->_title = match ($this->type) {
            'revenue' => Craft::t('commerce', 'Top Purchasables by Revenue'),
            'qty' => Craft::t('commerce', 'Top Purchasables by Qty Sold'),
            default => Craft::t('commerce', 'Top Purchasables'),
        };

        $this->dateRange = !isset($this->dateRange) || !$this->dateRange ? TopPurchasablesStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TopPurchasablesStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->storeId
        );

        if (!empty($this->orderStatuses)) {
            $this->_stat->setOrderStatuses($this->orderStatuses);
        }

        parent::init();
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
        return Craft::t('commerce', 'Top Purchasables');
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

        return $view->renderTemplate('commerce/_components/widgets/purchasables/top/body', [
            'stats' => $stats,
            'type' => $this->type,
            'nameField' => $this->nameField,
            'nameFieldLabel' => $this->_nameFieldOptions[$this->nameField] ?? '',
            'typeLabel' => $this->_typeOptions[$this->type] ?? '',
            'id' => 'top-purchasables' . StringHelper::randomString(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $id = 'top-purchasables' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/purchasables/top/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'orderStatuses' => $this->getOrderStatusOptions(),
            'typeOptions' => $this->_typeOptions,
            'nameFieldOptions' => $this->_nameFieldOptions,
        ]);
    }
}
