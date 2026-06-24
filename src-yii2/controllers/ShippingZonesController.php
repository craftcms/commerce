<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\View;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Zone Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZonesController extends BaseShippingSettingsController
{
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones($store->id);

        // Generate table data
        $tableData = [];
        foreach ($shippingZones as $shippingZone) {
            $label = Html::encode(Craft::t('site', $shippingZone->name));
            $tableData[] = [
                'id' => $shippingZone->id,
                'title' => Html::a($label, $shippingZone->getCpEditUrl()),
                'url' => $shippingZone->getCpEditUrl(),
                'description' => Html::encode(Craft::t('site', $shippingZone->description)),
            ];
        }

        $tableData = Json::encode($tableData);

        $js = <<<JS
    var columns = [
        { name: 'title', title: Craft.t('commerce', 'Name') },
        { name: 'description', title: Craft.t('commerce', 'Description') },
    ];

    new Craft.VueAdminTable({
        columns: columns,
        container: '#shipping-vue-admin-table',
        deleteAction: 'commerce/shipping-zones/delete',
        tableData: {$tableData},
    });
JS;

        $this->getView()->registerJs($js, View::POS_END);

        $this->getView()->registerTranslations('commerce', [
            'Name',
            'Description',
        ]);

        return $this->asStoreManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(Html::a(Craft::t('commerce', 'New shipping zone'), $store->getStoreSettingsUrl('shippingzones/new'), ['class' => 'btn submit add icon']))
            ->contentHtml(Html::tag('div', '', ['id' => 'shipping-vue-admin-table']));
    }

    /**
     * @param int|null $id
     * @param ShippingAddressZone|null $shippingZone
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, ShippingAddressZone $shippingZone = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        if (!$shippingZone) {
            if ($id) {
                $shippingZone = Plugin::getInstance()->getShippingZones()->getShippingZoneById($id, $store->id);

                if (!$shippingZone) {
                    throw new HttpException(404);
                }
            } else {
                $shippingZone = Craft::createObject([
                    'class' => ShippingAddressZone::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        if ($shippingZone->id) {
            $title = $shippingZone->name;
        } else {
            $title = Craft::t('commerce', 'Create a shipping zone');
        }

        $storeHandle = $store->handle;

        $condition = $shippingZone->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';

        DebugPanel::prependOrAppendModelTab(model: $shippingZone, prepend: true);

        $metadata = [];
        if ($shippingZone->id) {
            $metadata = [
                Craft::t('app', 'Created at') => Craft::$app->getFormatter()->asDatetime($shippingZone->dateCreated, 'short'),
                Craft::t('app', 'Updated at') => Craft::$app->getFormatter()->asDatetime($shippingZone->dateUpdated, 'short'),
            ];
        }

        return $this->asStoreManagementCpScreen($storeHandle, false)
            ->title($title)
            ->addCrumb(Craft::t('commerce', 'Shipping Zones'), $store->getStoreSettingsUrl('shippingzones'))
            ->action('commerce/shipping-zones/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingzones'))
            ->metaSidebarHtml(Cp::metadataHtml($metadata))
            ->contentTemplate('commerce/store-management/shipping/shippingzones/_edit', [
                'shippingZone' => $shippingZone,
                'condition' => $condition,
                'store' => $store,
            ]);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $shippingZone = new ShippingAddressZone();

        // Shared attributes
        $shippingZone->id = $this->request->getBodyParam('shippingZoneId');
        $shippingZone->storeId = $this->request->getBodyParam('storeId');
        $shippingZone->name = $this->request->getBodyParam('name');
        $shippingZone->description = $this->request->getBodyParam('description');
        $shippingZone->setCondition($this->request->getBodyParam('condition'));

        if ($shippingZone->validate() && Plugin::getInstance()->getShippingZones()->saveShippingZone($shippingZone)) {
            return $this->asModelSuccess(
                $shippingZone,
                Craft::t('commerce', 'Shipping zone saved.'),
                'shippingZone',
                data: [
                    'id' => $shippingZone->id,
                    'name' => $shippingZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $shippingZone,
            Craft::t('commerce', 'Couldn’t save shipping zone.'),
            'shippingZone'
        );
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        if (!Plugin::getInstance()->getShippingZones()->deleteShippingZoneById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete shipping zone'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     * @throws LoaderError
     * @throws SyntaxError
     * @since 2.2
     */
    public function actionTestZip(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $zipCodeFormula = (string)$this->request->getRequiredBodyParam('zipCodeConditionFormula');
        $testZipCode = (string)$this->request->getRequiredBodyParam('testZipCode');

        $params = ['zipCode' => $testZipCode];

        if (!Plugin::getInstance()->getFormulas()->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asFailure('failed');
        }

        return $this->asSuccess();
    }
}
