<?php
/**
 * eWAY Rapid Abstract Request
 */
 
namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Abstract Request
 *
 * This class forms the base class for eWAY Rapid requests
 *
 * @link https://eway.io/api-v3/#api-reference
 */
abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    protected $liveEndpoint = 'https://api.ewaypayments.com';
    protected $testEndpoint = 'https://api.sandbox.ewaypayments.com';

    public function getApiKey()
    {
        return $this->getParameter('apiKey');
    }

    public function setApiKey($value)
    {
        return $this->setParameter('apiKey', $value);
    }

    public function getPassword()
    {
        return $this->getParameter('password');
    }

    public function setPassword($value)
    {
        return $this->setParameter('password', $value);
    }

    public function getPartnerId()
    {
        return $this->getParameter('partnerId');
    }

    public function setPartnerId($value)
    {
        return $this->setParameter('partnerId', $value);
    }

    public function getTransactionType()
    {
        if ($this->getParameter('transactionType')) {
            return $this->getParameter('transactionType');
        }
        return 'Purchase';
    }

    /**
     * Sets the transaction type
     * One of "Purchase" (default), "MOTO" or "Recurring"
     */
    public function setTransactionType($value)
    {
        return $this->setParameter('transactionType', $value);
    }

    public function getShippingMethod()
    {
        return $this->getParameter('shippingMethod');
    }

    public function setShippingMethod($value)
    {
        return $this->setParameter('shippingMethod', $value);
    }

    public function getInvoiceReference()
    {
        return $this->getParameter('invoiceReference');
    }

    public function setInvoiceReference($value)
    {
        return $this->setParameter('invoiceReference', $value);
    }
    
    protected function getBaseData()
    {
        $data = array();
        $data['DeviceID'] = 'https://github.com/adrianmacneil/omnipay';
        $data['CustomerIP'] = $this->getClientIp();
        $data['PartnerID'] = $this->getPartnerId();
        $data['ShippingMethod'] = $this->getShippingMethod();

        $data['Customer'] = array();
        $card = $this->getCard();
        if ($card) {
            $data['Customer']['Title'] = $card->getTitle();
            $data['Customer']['FirstName'] = $card->getFirstName();
            $data['Customer']['LastName'] = $card->getLastName();
            $data['Customer']['CompanyName'] = $card->getCompany();
            $data['Customer']['Street1'] = $card->getAddress1();
            $data['Customer']['Street2'] = $card->getAddress2();
            $data['Customer']['City'] = $card->getCity();
            $data['Customer']['State'] = $card->getState();
            $data['Customer']['PostalCode'] = $card->getPostCode();
            $data['Customer']['Country'] = strtolower($card->getCountry());
            $data['Customer']['Email'] = $card->getEmail();
            $data['Customer']['Phone'] = $card->getPhone();

            $data['ShippingAddress']['FirstName'] = $card->getShippingFirstName();
            $data['ShippingAddress']['LastName'] = $card->getShippingLastName();
            $data['ShippingAddress']['Street1'] = $card->getShippingAddress1();
            $data['ShippingAddress']['Street2'] = $card->getShippingAddress2();
            $data['ShippingAddress']['City'] = $card->getShippingCity();
            $data['ShippingAddress']['State'] = $card->getShippingState();
            $data['ShippingAddress']['Country'] = strtolower($card->getShippingCountry());
            $data['ShippingAddress']['PostalCode'] = $card->getShippingPostcode();
            $data['ShippingAddress']['Phone'] = $card->getShippingPhone();
        }

        return $data;
    }

    protected function getItemData()
    {
        $itemArray = array();
        $items = $this->getItems();
        if ($items) {
            foreach ($items as $item) {
                $data = array();
                $data['SKU'] = strval($item->getName());
                $data['Description'] = strval($item->getDescription());
                $data['Quantity'] = strval($item->getQuantity());
                $cost = $this->formatCurrency($item->getPrice());
                $data['UnitCost'] = strval($this->getCostInteger($cost));
                $itemArray[] = $data;
            }
        }

        return $itemArray;
    }

    protected function getCostInteger($amount)
    {
        return (int) round($amount * pow(10, $this->getCurrencyDecimalPlaces()));
    }

    public function getEndpointBase()
    {
        return $this->getTestMode() ? $this->testEndpoint : $this->liveEndpoint;
    }
}
