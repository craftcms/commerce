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
use craft\commerce\Plugin;

/**
 * This is a dummy gateway request response.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Dummy implements RequestResponseInterface
{
    private $_success = true;


    public function __construct(CreditCardPaymentForm $form = null)
    {
        if ($form !== null && (substr($form->number, -1) % 2 === 1)) {
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
        return $this->_success ? '' : Plugin::t('Dummy gateway payment failed.');
    }

    /**
     * @inheritdoc
     */
    public function redirect()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getData()
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
