<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\db\Table;
use craft\commerce\gateways\Dummy;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\errors\DeprecationException;
use craft\helpers\Html;
use craft\helpers\Json;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Gateways Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class GatewaysController extends BaseAdminController
{
    public function actionIndex(): Response
    {
        $gateways = Plugin::getInstance()->getGateways()->getAllGateways();
        $archivedGateways = Plugin::getInstance()->getGateways()->getAllArchivedGateways();

        if (!empty($archivedGateways)) {
            $gatewayIdsWithTransactions = (new Query())
                ->select(['gatewayId'])
                ->from(Table::TRANSACTIONS)
                ->groupBy(['gatewayId'])
                ->column();

            foreach ($archivedGateways as &$gateway) {
                $missing = $gateway instanceof MissingGateway;
                $gateway = [
                    'id' => $gateway->id,
                    'title' => Craft::t('site', $gateway->name),
                    'handle' => Html::encode($gateway->handle),
                    'type' => [
                        'missing' => $missing,
                        'name' => $missing ? $gateway->expectedType : $gateway->displayName(),
                    ],
                    'hasTransactions' => in_array($gateway->id, $gatewayIdsWithTransactions),
                ];
            }
        }

        return $this->renderTemplate('commerce/settings/gateways/index', [
            'gateways' => $gateways,
            'archivedGateways' => array_values($archivedGateways),
            'readOnly' => $this->isReadOnlyScreen(),
        ]);
    }

    /**
     * @param int|null $id
     * @param GatewayInterface|null $gateway
     * @return Response
     * @throws HttpException
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, ?GatewayInterface $gateway = null): Response
    {
        /** @var Gateway|null $gateway */
        $variables = compact('id', 'gateway');
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $gatewayService = Plugin::getInstance()->getGateways();

        if (!$variables['gateway']) {
            if ($variables['id']) {
                $variables['gateway'] = $gatewayService->getGatewayById($variables['id']);

                if (!$variables['gateway']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['gateway'] = $gatewayService->createGateway([
                    'type' => Dummy::class,
                ]);
            }
        }

        /** @var string[]|GatewayInterface[] $allGatewayTypes */
        $allGatewayTypes = $gatewayService->getAllGatewayTypes();

        // Make sure the selected gateway class is in there
        if ($gateway && !in_array(get_class($gateway), $allGatewayTypes, true)) {
            $allGatewayTypes[] = get_class($gateway);
        }

        $gatewayInstances = [];
        $gatewayOptions = [];

        foreach ($allGatewayTypes as $class) {
            if (($gateway && $class === get_class($gateway)) || $class::isSelectable()) {
                $gatewayInstances[$class] = $gatewayService->createGateway($class);

                $gatewayOptions[] = [
                    'value' => $class,
                    'label' => $class::displayName(),
                ];
            }
        }

        $variables['gatewayTypes'] = $allGatewayTypes;
        $variables['gatewayInstances'] = $gatewayInstances;
        $variables['gatewayOptions'] = $gatewayOptions;

        if ($variables['gateway']->id) {
            $variables['title'] = $variables['gateway']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new gateway');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['gateway'], prepend: true);

        $variables['readOnly'] = $this->isReadOnlyScreen();

        return $this->renderTemplate('commerce/settings/gateways/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $gatewayService = Plugin::getInstance()->getGateways();

        $type = $this->request->getRequiredParam('type');
        $gatewayId = $this->request->getBodyParam('id');

        $config = [
            'id' => $gatewayId,
            'type' => $type,
            'name' => $this->request->getBodyParam('name'),
            'handle' => $this->request->getBodyParam('handle'),
            'paymentType' => $this->request->getBodyParam('paymentTypes.' . $type . '.paymentType'),
            'isFrontendEnabled' => $this->request->getParam('isFrontendEnabled'),
            'settings' => $this->request->getBodyParam('types.' . $type),
        ];

        // For new gateway avoid NULL value.
        if (!$this->request->getBodyParam('id')) {
            $config['isArchived'] = false;
        }

        // If this is an existing gateway, populate with properties unchangeable by this action.
        if ($gatewayId) {
            /** @var Gateway $savedGateway */
            $savedGateway = $gatewayService->getGatewayById($gatewayId);
            $config['uid'] = $savedGateway->uid;
            $config['sortOrder'] = $savedGateway->sortOrder;
        }

        /** @var Gateway $gateway */
        $gateway = $gatewayService->createGateway($config);

        // Save it
        if (!Plugin::getInstance()->getGateways()->saveGateway($gateway)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save gateway.'));

            // Send the volume back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'gateway' => $gateway,
            ]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Gateway saved.'));
        return $this->redirectToPostedUrl($gateway);
    }

    /**
     * @throws HttpException
     */
    public function actionArchive(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        if (!$id || !Plugin::getInstance()->getGateways()->archiveGatewayById((int)$id)) {
            return $this->asFailure(Craft::t('commerce', 'Could not archive gateway.'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws HttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));

        if (!Plugin::getInstance()->getGateways()->reorderGateways($ids)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t reorder gateways.'));
        }

        return $this->asSuccess();
    }
}
