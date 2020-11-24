<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\Email;
use craft\commerce\models\OrderHistory;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\helpers\ArrayHelper;
use craft\web\Controller;
use craft\web\View;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Email Preview Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class EmailPreviewController extends Controller
{
    /**
     * @return Response
     */
    public function actionRender(): Response
    {
        $this->requireAdmin();

        $emailId = Craft::$app->getRequest()->getParam('emailId');
        $email = Plugin::getInstance()->getEmails()->getEmailById($emailId);
        $orderNumber = Craft::$app->getRequest()->getParam('orderNumber');

        $view = Craft::$app->getView();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $order = null;
        if ($orderNumber) {
            $order = Order::find()->shortNumber(substr($orderNumber, 0, 7))->one();
        } else {
            $orderIds = Order::find()->isCompleted(true)->limit(5000)->ids();
            if ($orderIds) {
                $rand = array_rand($orderIds, 1);
                $order = Order::find()->isCompleted(true)->id($orderIds[$rand])->one();
            }
        }

        if (!$order) {
            $order = new Order();
        }

        if ($email && $order && $template = $email->templatePath) {
            if ($email->recipientType == EmailRecord::TYPE_CUSTOMER) {
                // use the order's language for template rendering the email.
                $orderLanguage = $order->orderLanguage ?: Craft::$app->language;
                Craft::$app->language = $orderLanguage;
            }

            $orderHistory = ArrayHelper::firstValue($order->getHistories()) ?: new OrderHistory();
            $orderData = $order->toArray();
            $option = 'email';
            return $this->renderTemplate($template, compact('order', 'orderHistory', 'option', 'orderData'));
        }

        $errors = [];
        if (!$email) {
            $errors[] = Craft::t('commerce', 'Could not find the email or template.');
        }

        $view = Craft::$app->getView();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);
        return $this->renderTemplate('commerce/settings/emails/_previewError', compact('errors'));
    }
}
