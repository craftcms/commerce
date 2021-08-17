<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

return [
    'order-confirmation' => [
        'name' => 'Order Confirmation',
        'subject' => 'Woo! We’ve got your order!',
        'recipientType' => 'customer',
        'to' => null,
        'bcc' => '',
        'cc' => '',
        'replyTo' => '',
        'enabled' => true,
        'templatePath' => 'emails/order-confirmation',
        'plainTextTemplatePath' => '',
        'pdfId' => null,
        'language' => 'orderLanguage',
    ],
];
