<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\CreditCardPaymentForm;

/**
 * This is a dummy gateway request response.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Dummy implements RequestResponseInterface
{
    /**
     * @var bool
     */
    private bool $_success = true;

    public function __construct(?CreditCardPaymentForm $form = null)
    {
        if ($form === null) {
            $this->_success = false;
            return;
        }

        // Token populated? This is a "payment source" so no need to fail anything
        if ($form->token) {
            return;
        }

        $number = (string)$form->number;
        $isValid = ((int)substr($number, -1) % 2 === 0);

        if (!$isValid) {
            $this->_success = false;
        }
    }

    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
        return $this->_success;
    }

    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        return date('Y-m-d-H-i-s');
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return $this->_success ? '' : 'payment.failed';
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        return $this->_success ? '' : Craft::t('commerce', 'Dummy gateway payment failed.');
    }

    /**
     * @inheritdoc
     */
    public function redirect(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function getData(): mixed
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return false;
    }
}
