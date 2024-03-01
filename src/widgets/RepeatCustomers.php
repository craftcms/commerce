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
use craft\commerce\stats\RepeatCustomers as RepeatingCustomersStat;
use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use craft\models\Site;

/**
 * Repeat Customers widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class RepeatCustomers extends Widget
{
    use StatWidgetTrait;

    /**
     * @var null|RepeatingCustomersStat
     */
    private ?RepeatingCustomersStat $_stat = null;

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

        $this->dateRange = !isset($this->dateRange) || !$this->dateRange ? RepeatingCustomersStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new RepeatingCustomersStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
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
        return Craft::$app->getUser()->checkPermission('commerce-manageCustomers');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Repeat Customers');
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
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $numbers = $this->_stat->get();
        $timeFrame = $this->_stat->getDateRangeWording();

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        return $view->renderTemplate('commerce/_components/widgets/customers/repeat/body', compact('numbers', 'timeFrame'));
    }

    /**
     * @inheritDoc
     */
    public static function maxColspan(): ?int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $id = 'repeat' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/customers/repeat/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'orderStatuses' => $this->getOrderStatusOptions(),
            'widget' => $this,
        ]);
    }
}
