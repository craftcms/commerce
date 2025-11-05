<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\errors\StoreNotFoundException;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use craft\helpers\Html;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Zone Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZonesController extends BaseTaxSettingsController
{
    /**
     * @param string|null $storeHandle
     * @return Response
     * @throws StoreNotFoundException
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $taxZones = Plugin::getInstance()->getTaxZones()->getAllTaxZones($store->id);

        // Generate table data with chips
        $tableData = [];
        foreach ($taxZones as $taxZone) {
            $label = Craft::t('site', $taxZone->name);
            $tableData[] = [
                'id' => $taxZone->id,
                'title' => $label,
                'chip' => Cp::chipHtml($taxZone, [
                    'labelHtml' => Html::a($label, $taxZone->getCpEditUrl(), [
                        'class' => ['chip-label', 'cell-bold'],
                    ]),
                ]),
                'url' => $taxZone->getCpEditUrl(),
                'description' => Craft::t('site', $taxZone->description),
                'default' => $taxZone->default,
            ];
        }

        return $this->renderTemplate('commerce/store-management/tax/taxzones/index', [
            'taxZones' => $taxZones,
            'tableData' => $tableData,
            'store' => $store,
        ]);
    }

    /**
     * @param int|null $id
     * @param TaxAddressZone|null $taxZone
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, TaxAddressZone $taxZone = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $storeHandle = $store->handle;

        if (!$taxZone) {
            if ($id) {
                $taxZone = Plugin::getInstance()->getTaxZones()->getTaxZoneById($id, $store->id);

                if (!$taxZone) {
                    throw new HttpException(404);
                }
            } else {
                $taxZone = Craft::createObject([
                    'class' => TaxAddressZone::class,
                    'storeId' => $store->id,
                ]);
            }
        }

        $title = $taxZone->id ? $taxZone->name : Craft::t('commerce', 'Create a tax zone');

        $condition = $taxZone->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';
        $conditionField = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => Craft::t('app', 'Address Condition'),
        ]);

        DebugPanel::prependOrAppendModelTab(model: $taxZone, prepend: true);

        $metaSidebar = '';
        if ($taxZone->id) {
            $metaSidebar = '<div class="meta read-only">' .
                '<div class="data">' .
                '<h5 class="heading">' . Craft::t('app', 'Created at') . '</h5>' .
                '<div id="date-created-value" class="value">' . Craft::$app->getFormatter()->asDatetime($taxZone->dateCreated, 'short') . '</div>' .
                '</div>' .
                '<div class="data">' .
                '<h5 class="heading">' . Craft::t('app', 'Updated at') . '</h5>' .
                '<div id="date-updated-value" class="value">' . Craft::$app->getFormatter()->asDatetime($taxZone->dateUpdated, 'short') . '</div>' .
                '</div>' .
                '</div>';
        }

        return $this->asCpScreen()
            ->title($title)
            ->crumbs([
                ['label' => Craft::t('commerce', 'Commerce'), 'url' => 'commerce'],
                $this->getStoreSwitcher($storeHandle),
                ['label' => Craft::t('commerce', 'Tax Zones'), 'url' => "commerce/store-management/{$storeHandle}/taxzones"],
            ])
            ->selectedSubnavItem('tax')
            ->action('commerce/tax-zones/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxzones'))
            ->metaSidebarHtml($metaSidebar)
            ->contentTemplate('commerce/store-management/tax/taxzones/_edit', [
                'taxZone' => $taxZone,
                'store' => $store,
                'conditionField' => $conditionField,
            ]);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $taxZone = new TaxAddressZone();

        $taxZone->id = $this->request->getBodyParam('taxZoneId');
        $taxZone->storeId = $this->request->getBodyParam('storeId');
        $taxZone->name = $this->request->getBodyParam('name');
        $taxZone->description = $this->request->getBodyParam('description');
        $taxZone->default = (bool)$this->request->getBodyParam('default');
        $taxZone->setCondition($this->request->getBodyParam('condition'));

        if ($taxZone->validate() && Plugin::getInstance()->getTaxZones()->saveTaxZone($taxZone)) {
            return $this->asModelSuccess(
                $taxZone,
                Craft::t('commerce', 'Tax zone saved.'),
                'taxZone',
                data: [
                    'id' => $taxZone->id,
                    'name' => $taxZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $taxZone,
            Craft::t('commerce', 'Couldn’t save tax zone.'),
            'taxZone'
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

        Plugin::getInstance()->getTaxZones()->deleteTaxZoneById($id);
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
