<?php

namespace Omnipay\CardSave\Message;

/**
 * CardSave Purchase Request
 */
class ReferencedPurchaseRequest extends RefundRequest
{
    public $transactionType = 'SALE';
}
