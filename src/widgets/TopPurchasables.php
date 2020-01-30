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
use craft\commerce\stats\TopPurchasables as TopPurchasablesStat;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
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
     * @var string options 'description', 'sku'.
     */
    public $nameField;

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
    private $_nameFieldOptions;

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->nameField = $this->nameField ?: 'description';

        $this->_nameFieldOptions = [
            'description' => Plugin::t('Description'),
            'sku' => Plugin::t('SKU'),
        ];

        $this->_typeOptions = [
            'qty' => Plugin::t('Qty'),
            'revenue' => Plugin::t('Revenue'),
        ];

        switch ($this->type) {
            case 'revenue':
            {
                $this->_title = Plugin::t('Top Purchasables by Revenue');
                break;
            }
            case 'qty':
            {
                $this->_title = Plugin::t('Top Purchasables by Qty Sold');
                break;
            }
            default:
            {
                $this->_title = Plugin::t('Top Purchasables');
                break;
            }
        }
        $this->dateRange = !$this->dateRange ? TopPurchasablesStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TopPurchasablesStat(
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
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders') && Craft::$app->getUser()->checkPermission('commerce-manageProducts');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Plugin::t( 'Top Purchasables');
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
    public function getSettingsHtml(): string
    {
        $id = 'top-purchasables' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/purchasables/top/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
            'typeOptions' => $this->_typeOptions,
            'nameFieldOptions' => $this->_nameFieldOptions,
        ]);
    }
}
