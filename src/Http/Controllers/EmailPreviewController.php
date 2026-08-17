<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\elements\Order;
use craft\commerce\helpers\Locale;
use craft\commerce\models\OrderHistory;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use CraftCms\Cms\View\TemplateMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

readonly class EmailPreviewController
{
    public function render(Request $request): string
    {
        $email = $request->input('email');
        $emailId = (int)StringHelper::split($email, ':')[0];
        $storeId = (int)StringHelper::split($email, ':')[1];
        $email = Plugin::getInstance()->getEmails()->getEmailById($emailId, $storeId);

        $orderNumber = $request->input('number');

        if ($orderNumber) {
            $order = Order::find()->shortNumber(substr((string)$orderNumber, 0, 7))->one();
        } else {
            $orderQuery = Order::find()->isCompleted(true);

            if (DB::connection()->getDriverName() === 'pgsql') {
                $orderQuery->orderByRaw('RANDOM()');
            } else {
                $orderQuery->orderByRaw('RAND()');
            }

            $order = $orderQuery->one();
        }

        $order ??= new Order();

        if ($email && $templatePath = $email->templatePath) {
            $emailLanguage = $email->getRenderLanguage($order);

            Locale::switchAppLanguage($emailLanguage);

            $orderHistory = ArrayHelper::firstValue($order->getHistories()) ?: new OrderHistory();
            $orderData = $order->toArray();
            $option = 'email';

            return template($templatePath, compact('order', 'orderHistory', 'option', 'orderData'), TemplateMode::Site);
        }

        $errors = [];
        if (!$email) {
            $errors[] = t('Could not find the email or template.', category: 'commerce');
        }

        return template('commerce/settings/emails/_previewError', compact('errors'), TemplateMode::Cp);
    }
}
