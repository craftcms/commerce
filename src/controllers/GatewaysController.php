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
use craft\commerce\gateways\Dummy;
use craft\commerce\Plugin;
use craft\helpers\Json;
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
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $gateways = Plugin::getInstance()->getGateways()->getAllGateways();

        return $this->renderTemplate('commerce/settings/gateways/index', [
            'gateways' => $gateways
        ]);
    }

    /**
     * @param int|null $id
     * @param GatewayInterface|null $gateway
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, GatewayInterface $gateway = null): Response
    {
        $variables = compact('id', 'gateway');

        $gatewayService = Plugin::getInstance()->getGateways();

        if (!$variables['gateway']) {
            if ($variables['id']) {
                $variables['gateway'] = $gatewayService->getGatewayById($variables['id']);

                if (!$variables['gateway']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['gateway'] = $gatewayService->createGateway(Dummy::class);
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
                    'label' => $class::displayName()
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
        return $this->renderTemplate('commerce/settings/gateways/_edit', $variables);
    }

    /**
     * @return Response|null
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $gatewayService = Plugin::getInstance()->getGateways();

        $type = $request->getRequiredParam('type');
        $gatewayId = $request->getBodyParam('id');

        $config = [
            'id' => $gatewayId,
            'type' => $type,
            'name' => $request->getBodyParam('name'),
            'handle' => $request->getBodyParam('handle'),
            'paymentType' => $request->getBodyParam('paymentTypes.' . $type . '.paymentType'),
            'isFrontendEnabled' => (bool)$request->getParam('isFrontendEnabled'),
            'settings' => $request->getBodyParam('types.' . $type),
        ];

        // For new gateway avoid NULL value.
        if (!$request->getBodyParam('id')) {
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

        $session = Craft::$app->getSession();

        // Save it
        if (!Plugin::getInstance()->getGateways()->saveGateway($gateway)) {
            $session->setError(Craft::t('commerce', 'Couldn’t save gateway.'));

            // Send the volume back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'gateway' => $gateway
            ]);

            return null;
        }

        $session->setNotice(Craft::t('commerce', 'Gateway saved.'));
        return $this->redirectToPostedUrl($gateway);
    }

    /**
     * @throws HttpException
     */
    public function actionArchive(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->getGateways()->archiveGatewayById($id)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could not archive gateway.'));
    }

    /**
     * @throws HttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Plugin::getInstance()->getGateways()->reorderGateways($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder gateways.')]);
    }
}
