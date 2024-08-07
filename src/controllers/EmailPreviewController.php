<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderHistory;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\View;
use yii\base\Exception;
use yii\web\ForbiddenHttpException;
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
     * @throws Exception
     * @throws ForbiddenHttpException
     */
    public function actionRender(): Response
    {
        $this->requireAdmin();

        $email = $this->request->getParam('email');
        $emailId = (int)StringHelper::split($email, ':')[0];
        $storeId = (int)StringHelper::split($email, ':')[1];
        $email = Plugin::getInstance()->getEmails()->getEmailById($emailId, $storeId);

        $orderNumber = $this->request->getParam('number');

        if ($orderNumber) {
            $order = Order::find()->shortNumber(substr($orderNumber, 0, 7))->one();
        } else {
            $orderQuery = Order::find()->isCompleted(true);

            if (Craft::$app->getDb()->getIsPgsql()) {
                $orderQuery->orderBy('RANDOM()');
            } else {
                $orderQuery->orderBy('RAND()');
            }

            $order = $orderQuery->one();
        }

        if (!$order) {
            $order = new Order();
        }

        if ($email && $template = $email->templatePath) {
            if ($email->recipientType == EmailRecord::TYPE_CUSTOMER) {
                // use the order's language for template rendering the email.
                $orderLanguage = $order->orderLanguage ?: Craft::$app->language;
                Craft::$app->language = $orderLanguage;
            }

            $orderHistory = ArrayHelper::firstValue($order->getHistories()) ?: new OrderHistory();
            $orderData = $order->toArray();
            $option = 'email';
            return $this->renderTemplate($template, compact('order', 'orderHistory', 'option', 'orderData'), View::TEMPLATE_MODE_SITE);
        }

        $errors = [];
        if (!$email) {
            $errors[] = Craft::t('commerce', 'Could not find the email or template.');
        }

        return $this->renderTemplate('commerce/settings/emails/_previewError', compact('errors'));
    }
}
