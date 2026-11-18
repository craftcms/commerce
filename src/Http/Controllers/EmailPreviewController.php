<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Helpers\Sql;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Http\Request;

use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

readonly class EmailPreviewController
{
    public function render(Request $request): string
    {
        $email = $request->input('email');
        $emailId = (int)preg_split('/\s*:\s*/', $email, -1, PREG_SPLIT_NO_EMPTY)[0];
        $storeId = (int)preg_split('/\s*:\s*/', $email, -1, PREG_SPLIT_NO_EMPTY)[1];
        $email = app(Emails::class)->getEmailById($emailId, $storeId);

        $orderNumber = $request->input('number');

        if ($orderNumber) {
            $order = Order::find()->shortNumber(substr((string)$orderNumber, 0, 7))->one();
        } else {
            $orderQuery = Order::find()->isCompleted(true);
            $orderQuery->orderByRaw(Sql::randomSql());

            $order = $orderQuery->one();
        }

        $order ??= new Order();

        if ($email && $templatePath = $email->templatePath) {
            return I18N::withLocale($email->getRenderLanguage($order), null, function() use ($order, $templatePath) {
                $orderHistory = Arr::first($order->getHistories()) ?: new OrderHistory();
                $orderData = $order->toArray();
                $option = 'email';

                return template($templatePath, compact('order', 'orderHistory', 'option', 'orderData'), TemplateMode::Site);
            });
        }

        $errors = [];
        if (!$email) {
            $errors[] = t('Could not find the email or template.', category: 'commerce');
        }

        return Html::encode(t('Can’t preview this email.', category: 'commerce')) . Html::ul($errors);
    }
}
