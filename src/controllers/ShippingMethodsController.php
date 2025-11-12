<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\View;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Methods Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingMethodsController extends BaseShippingSettingsController
{
    /**
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAllShippingMethods($store->id);

        // Generate table data with chips
        $tableData = [];
        foreach ($shippingMethods as $shippingMethod) {
            $label = Craft::t('site', $shippingMethod->name);
            $tableData[] = [
                'id' => $shippingMethod->id,
                'title' => $label,
                'chip' => Cp::chipHtml($shippingMethod, [
                    'showStatus' => true,
                    'showThumb' => true,
                    'labelHtml' => Html::a($label, $shippingMethod->getCpEditUrl(), [
                        'class' => ['chip-label', 'cell-bold'],
                    ]),
                ]),
                'url' => $shippingMethod->getCpEditUrl(),
                'handle' => $shippingMethod->handle,
                'type' => $shippingMethod->getType(),
                'status' => $shippingMethod->enabled,
            ];
        }

        $this->getView()->registerTranslations('commerce', [
            'Disabled',
            'Enabled',
            'Handle',
            'Name',
            'Set status',
            'Type',
        ]);

        $tableData = Json::encode($tableData);

        $js = <<<JS
    var columns = [
        { name: 'chip', title: Craft.t('commerce', 'Name') },
        { name: '__slot:handle', title: Craft.t('commerce', 'Handle') },
        { name: 'type', title: Craft.t('commerce', 'Type') },
    ];

    var actions = [
        {
            label: Craft.t('commerce', 'Set status'),
            actions: [
                {
                    label: Craft.t('commerce', 'Enabled'),
                    action: 'commerce/shipping-methods/update-status',
                    param: 'status',
                    value: 'enabled',
                    status: 'enabled'
                },
                {
                    label: Craft.t('commerce', 'Disabled'),
                    action: 'commerce/shipping-methods/update-status',
                    param: 'status',
                    value: 'disabled',
                    status: 'disabled'
                }
            ]
        }
    ];

    new Craft.VueAdminTable({
        actions: actions,
        checkboxes: true,
        columns: columns,
        container: '#shipping-vue-admin-table',
        deleteAction: 'commerce/shipping-methods/delete',
        padded: true,
        tableData: {$tableData}
    });
JS;

        $this->getView()->registerJs($js, View::POS_END);

        return $this->asStoreManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(Html::a(Craft::t('commerce', 'New shipping method'), $store->getStoreSettingsUrl('shippingmethods/new'), ['class' => 'btn submit add icon']))
            ->contentHtml(Html::tag('div', '', ['id' => 'shipping-vue-admin-table']));
    }

    /**
     * @param int|null $id
     * @param ShippingMethod|null $shippingMethod
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, ShippingMethod $shippingMethod = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        if (!$shippingMethod) {
            if ($id) {
                $shippingMethod = Plugin::getInstance()->getShippingMethods()->getShippingMethodById($id, $store->id);

                if (!$shippingMethod) {
                    throw new HttpException(404);
                }
            } else {
                $shippingMethod = Craft::createObject([
                    'class' => ShippingMethod::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        if ($shippingMethod->id) {
            $title = $shippingMethod->name;
        } else {
            $title = Craft::t('commerce', 'Create a new shipping method');
        }

        $storeHandle = $store->handle;

        DebugPanel::prependOrAppendModelTab(model: $shippingMethod, prepend: true);

        $shippingRules = $shippingMethod->id !== null
            ? Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($shippingMethod->id)
            : [];

        $this->getView()->registerTranslations('commerce', [
            'Couldn’t reorder rules.',
            'Description',
            'No shipping rules exist yet.',
            'Rules reordered.',
            'Shipping Rule',
        ]);

        $metaDataHtml = Html::beginTag('div', ['class' => 'meta']) .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('commerce', 'Enable this shipping method on the front end'),
                'id' => 'enabled',
                'name' => 'enabled',
                'on' => $shippingMethod->enabled,
                'errors' => $shippingMethod->getErrors('enabled'),
            ]) .
            Html::endTag('div');

        if ($shippingMethod->id) {
            $metaDataHtml .= Cp::metadataHtml([
                Craft::t('app', 'Created at') => Craft::$app->getFormatter()->asDatetime($shippingMethod->dateCreated, 'short'),
                Craft::t('app', 'Updated at') => Craft::$app->getFormatter()->asDatetime($shippingMethod->dateUpdated, 'short'),
            ]);
        }

        return $this->asStoreManagementCpScreen($storeHandle, false)
            ->title($title)
            ->action('commerce/shipping-methods/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingmethods/{id}#rules'))
            ->addCrumb(Craft::t('commerce', 'Shipping Methods'), $store->getStoreSettingsUrl('shippingmethods'))
            ->metaSidebarHtml($metaDataHtml)
            ->submitButtonLabel($shippingMethod->id ? Craft::t('commerce', 'Save and set rules') : Craft::t('app', 'Save'))
            ->contentTemplate('commerce/store-management/shipping/shippingmethods/_edit', [
                'shippingMethod' => $shippingMethod,
                'shippingRules' => $shippingRules,
                'store' => $store,
                'storeHandle' => $storeHandle,
            ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws \yii\base\Exception
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $shippingMethod = new ShippingMethod();

        // Shared attributes
        $shippingMethod->id = $this->request->getBodyParam('shippingMethodId');
        $shippingMethod->name = $this->request->getBodyParam('name');
        $shippingMethod->handle = $this->request->getBodyParam('handle');
        $shippingMethod->icon = $this->request->getBodyParam('icon');
        $shippingMethod->color = $this->request->getBodyParam('color');
        $shippingMethod->storeId = $this->request->getBodyParam('storeId');
        $shippingMethod->setOrderCondition($this->request->getBodyParam('orderCondition'));
        $shippingMethod->setCustomerCondition($this->request->getBodyParam('customerCondition'));
        $shippingMethod->enabled = (bool)$this->request->getBodyParam('enabled');

        // Save it
        if (!Plugin::getInstance()->getShippingMethods()->saveShippingMethod($shippingMethod)) {
            return $this->asModelFailure($shippingMethod, Craft::t('commerce', 'Couldn’t save shipping method.'), 'shippingMethod');
        }

        return $this->asModelSuccess($shippingMethod, Craft::t('commerce', 'Shipping method saved.'), 'shippingMethod');
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('id');
        $ids = $this->request->getBodyParam('ids');

        if ((!$id && empty($ids)) || ($id && !empty($ids))) {
            throw new BadRequestHttpException('id or ids must be specified.');
        }

        if ($id) {
            // If it is just the one id we know it has come from an ajax request on the table
            $this->requireAcceptsJson();
            $ids = [$id];
        }

        $failedIds = [];
        foreach ($ids as $id) {
            if (!Plugin::getInstance()->getShippingMethods()->deleteShippingMethodById($id)) {
                $failedIds[] = $id;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete {count, number} shipping {count, plural, one{method} other{methods}} and rules.', [
                'count' => count($failedIds),
            ]));
        }

        return $this->asSuccess(Craft::t('commerce', 'Shipping methods and rules deleted.'));
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @since 3.2.9
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $ids = $this->request->getRequiredBodyParam('ids');
        $status = $this->request->getRequiredBodyParam('status');

        if (empty($ids)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t update status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $shippingMethods = ShippingMethodRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var ShippingMethodRecord $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->enabled = ($status == 'enabled');
            $shippingMethod->save();
        }
        $transaction->commit();

        $this->setSuccessFlash(Craft::t('commerce', 'Shipping methods updated.'));
    }
}
