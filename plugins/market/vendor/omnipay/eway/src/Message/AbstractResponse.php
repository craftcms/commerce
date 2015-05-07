<?php
/**
 * eWAY Rapid Abstract Response
 */
 
namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Abstract Response
 *
 * This is the base response class for all eWAY requests.
 *
 */
abstract class AbstractResponse extends \Omnipay\Common\Message\AbstractResponse
{
    /**
     * Translation of eWAY response codes to English
     *
     * @link https://eway.io/api-v3/#response-amp-error-codes
     */
    public static $MESSAGES = array(
        'A2000' => 'Transaction Approved',
        'A2008' => 'Honour With Identification',
        'A2010' => 'Approved For Partial Amount',
        'A2011' => 'Approved, VIP',
        'A2016' => 'Approved, Update Track 3',
        'D4401' => 'Refer to Issuer',
        'D4402' => 'Refer to Issuer, special',
        'D4403' => 'No Merchant',
        'D4404' => 'Pick Up Card',
        'D4405' => 'Do Not Honour',
        'D4406' => 'Error',
        'D4407' => 'Pick Up Card, Special',
        'D4409' => 'Request In Progress',
        'D4412' => 'Invalid Transaction',
        'D4413' => 'Invalid Amount',
        'D4414' => 'Invalid Card Number',
        'D4415' => 'No Issuer',
        'D4419' => 'Re-enter Last Transaction',
        'D4421' => 'No Action Taken',
        'D4422' => 'Suspected Malfunction',
        'D4423' => 'Unacceptable Transaction Fee',
        'D4425' => 'Unable to Locate Record On File',
        'D4430' => 'Format Error',
        'D4431' => 'Bank Not Supported By Switch',
        'D4433' => 'Expired Card, Capture',
        'D4434' => 'Suspected Fraud, Retain Card',
        'D4435' => 'Card Acceptor, Contact Acquirer, Retain Card',
        'D4436' => 'Restricted Card, Retain Card',
        'D4437' => 'Contact Acquirer Security Department, Retain Card',
        'D4438' => 'PIN Tries Exceeded, Capture',
        'D4439' => 'No Credit Account',
        'D4440' => 'Function Not Supported',
        'D4441' => 'Lost Card',
        'D4442' => 'No Universal Account',
        'D4443' => 'Stolen Card',
        'D4444' => 'No Investment Account',
        'D4451' => 'Insufficient Funds',
        'D4452' => 'No Cheque Account',
        'D4453' => 'No Savings Account',
        'D4454' => 'Expired Card',
        'D4455' => 'Incorrect PIN',
        'D4456' => 'No Card Record',
        'D4457' => 'Function Not Permitted to Cardholder',
        'D4458' => 'Function Not Permitted to Terminal',
        'D4459' => 'Suspected Fraud',
        'D4460' => 'Acceptor Contact Acquirer',
        'D4461' => 'Exceeds Withdrawal Limit',
        'D4462' => 'Restricted Card',
        'D4463' => 'Security Violation',
        'D4464' => 'Original Amount Incorrect',
        'D4466' => 'Acceptor Contact Acquirer, Security',
        'D4467' => 'Capture Card',
        'D4475' => 'PIN Tries Exceeded',
        'D4482' => 'CVV Validation Error',
        'D4490' => 'Cut off In Progress',
        'D4491' => 'Card Issuer Unavailable',
        'D4492' => 'Unable To Route Transaction',
        'D4493' => 'Cannot Complete, Violation Of The Law',
        'D4494' => 'Duplicate Transaction',
        'D4496' => 'System Error',
        'D4497' => 'MasterPass Error',
        'D4498' => 'PayPal Create Transaction Error',
        'D4499' => 'Invalid Transaction for Auth/Void',
        'S5000' => 'System Error',
        'S5011' => 'PayPal Connection Error',
        'S5012' => 'PayPal Settings Error',
        'S5085' => 'Started 3dSecure',
        'S5086' => 'Routed 3dSecure',
        'S5087' => 'Completed 3dSecure',
        'S5099' => 'Incomplete (Access Code in progress/incomplete)',
        'V6000' => 'Validation error',
        'V6001' => 'Invalid CustomerIP',
        'V6002' => 'Invalid DeviceID',
        'V6003' => 'Invalid PartnerID',
        'V6004' => 'Invalid Method',
        'V6010' => 'Invalid TransactionType, account not certified for eCome only MOTO or Recurring available',
        'V6011' => 'Invalid TotalAmount',
        'V6012' => 'Invalid InvoiceDescription',
        'V6013' => 'Invalid InvoiceNumber',
        'V6014' => 'Invalid InvoiceReference',
        'V6015' => 'Invalid CurrencyCode',
        'V6016' => 'Payment Required',
        'V6017' => 'Payment CurrencyCode Required',
        'V6018' => 'Unknown Payment CurrencyCode',
        'V6021' => 'EWAY_CARDNAME Required',
        'V6022' => 'EWAY_CARDNUMBER Required',
        'V6023' => 'EWAY_CARDCVN Required',
        'V6033' => 'Invalid Expiry Date',
        'V6034' => 'Invalid Issue Number',
        'V6035' => 'Invalid Valid From Date',
        'V6040' => 'Invalid TokenCustomerID',
        'V6041' => 'Customer Required',
        'V6042' => 'Customer First Name Required',
        'V6043' => 'Customer Last Name Required',
        'V6044' => 'Customer CountryCode Required',
        'V6045' => 'Customer Title Required',
        'V6046' => 'TokenCustomerID Required',
        'V6047' => 'RedirectURL Required',
        'V6051' => 'Invalid Customer FirstName',
        'V6052' => 'Invalid Customer LastName',
        'V6053' => 'Invalid Customer CountryCode',
        'V6058' => 'Invalid Customer Title',
        'V6059' => 'Invalid RedirectURL',
        'V6060' => 'Invalid TokenCustomerID',
        'V6061' => 'Invalid Customer Reference',
        'V6062' => 'Invalid Customer CompanyName',
        'V6063' => 'Invalid Customer JobDescription',
        'V6064' => 'Invalid Customer Street1',
        'V6065' => 'Invalid Customer Street2',
        'V6066' => 'Invalid Customer City',
        'V6067' => 'Invalid Customer State',
        'V6068' => 'Invalid Customer PostalCode',
        'V6069' => 'Invalid Customer Email',
        'V6070' => 'Invalid Customer Phone',
        'V6071' => 'Invalid Customer Mobile',
        'V6072' => 'Invalid Customer Comments',
        'V6073' => 'Invalid Customer Fax',
        'V6074' => 'Invalid Customer URL',
        'V6075' => 'Invalid ShippingAddress FirstName',
        'V6076' => 'Invalid ShippingAddress LastName',
        'V6077' => 'Invalid ShippingAddress Street1',
        'V6078' => 'Invalid ShippingAddress Street2',
        'V6079' => 'Invalid ShippingAddress City',
        'V6080' => 'Invalid ShippingAddress State',
        'V6081' => 'Invalid ShippingAddress PostalCode',
        'V6082' => 'Invalid ShippingAddress Email',
        'V6083' => 'Invalid ShippingAddress Phone',
        'V6084' => 'Invalid ShippingAddress Country',
        'V6085' => 'Invalid ShippingAddress ShippingMethod',
        'V6086' => 'Invalid ShippingAddress Fax',
        'V6091' => 'Unknown Customer CountryCode',
        'V6092' => 'Unknown ShippingAddress CountryCode',
        'V6100' => 'Invalid EWAY_CARDNAME',
        'V6101' => 'Invalid EWAY_CARDEXPIRYMONTH',
        'V6102' => 'Invalid EWAY_CARDEXPIRYYEAR',
        'V6103' => 'Invalid EWAY_CARDSTARTMONTH',
        'V6104' => 'Invalid EWAY_CARDSTARTYEAR',
        'V6105' => 'Invalid EWAY_CARDISSUENUMBER',
        'V6106' => 'Invalid EWAY_CARDCVN',
        'V6107' => 'Invalid EWAY_ACCESSCODE',
        'V6108' => 'Invalid CustomerHostAddress',
        'V6109' => 'Invalid UserAgent',
        'V6110' => 'Invalid EWAY_CARDNUMBER',
        'V6111' => 'Unauthorised API Access, Account Not PCI Certified',
        'V6112' => 'Redundant card details other than expiry year and month',
        'V6113' => 'Invalid transaction for refund',
        'V6114' => 'Gateway validation error',
        'V6115' => 'Invalid Refund Transaction ID',
        'V6116' => 'Invalid card data on original TransactionID',
        'V6117' => 'Invalid CreateAccessCodeSharedRequest, FooterText',
        'V6118' => 'Invalid CreateAccessCodeSharedRequest, HeaderText',
        'V6119' => 'Invalid CreateAccessCodeSharedRequest, Language',
        'V6120' => 'Invalid CreateAccessCodeSharedRequest, LogoUrl',
        'V6121' => 'Invalid TransactionSearch, Filter Match Type',
        'V6122' => 'Invalid TransactionSearch, Non numeric Transaction ID',
        'V6123' => 'Invalid TransactionSearch,no TransactionID or AccessCode specified',
        'V6124' => 'Invalid Line Items. The line items have been provided however the totals do not match the
 TotalAmount field',
        'V6125' => 'Selected Payment Type not enabled',
        'V6126' => 'Invalid encrypted card number, decryption failed',
        'V6127' => 'Invalid encrypted cvn, decryption failed',
        'V6128' => 'Invalid Method for Payment Type',
        'V6129' => 'Transaction has not been authorised for Capture/Cancellation',
        'V6130' => 'Generic customer information error',
        'V6131' => 'Generic shipping information error',
        'V6132' => 'Transaction has already been completed or voided, operation not permitted',
        'V6133' => 'Checkout not available for Payment Type',
        'V6134' => 'Invalid Auth Transaction ID for Capture/Void',
        'V6135' => 'PayPal Error Processing Refund',
        'V6140' => 'Merchant account is suspended',
        'V6141' => 'Invalid PayPal account details or API signature',
        'V6142' => 'Authorise not available for Bank/Branch',
        'V6150' => 'Invalid Refund Amount',
        'V6151' => 'Refund amount greater than original transaction',
        'V6152' => 'Original transaction already refunded for total amount',
        'V6153' => 'Card type not support by merchant',
    );

    public function isSuccessful()
    {
        return isset($this->data['TransactionStatus']) && $this->data['TransactionStatus'];
    }

    public function getTransactionReference()
    {
        return isset($this->data['TransactionID']) ? (string) $this->data['TransactionID'] : null;
    }

    public function getMessage()
    {
        $codes = explode(',', $this->getCode());
        $messages = array();

        foreach ($codes as $code) {
            $code = trim($code);
            if (isset(static::$MESSAGES[$code])) {
                $messages[] = static::$MESSAGES[$code];
            } else {
                $messages[] = $code;
            }
        }

        return implode(', ', $messages) ?: null;
    }

    public function getCode()
    {
        if (!empty($this->data['ResponseMessage'])) {
            return $this->data['ResponseMessage'];
        } elseif (!empty($this->data['Errors'])) {
            return $this->data['Errors'];
        }
    }
}
