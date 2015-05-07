<?php
/**
 * eWAY Rapid Direct Abstract Request
 */
 
namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Direct Abstract Request
 *
 * This class forms the base class for eWAY Rapid Direct requests.
 *
 * @link https://eway.io/api-v3/#direct-connection
 */
abstract class RapidDirectAbstractRequest extends AbstractRequest
{
    public function getEncryptedCardNumber()
    {
        return $this->getParameter('encryptedCardNumber');
    }
    
    /**
     * Sets the encrypted card number, for use when submitting card data
     * encrypted using eWAY's client side encryption.
     *
     * @param string $value
     * @return RapidDirectAbstractRequest
     */
    public function setEncryptedCardNumber($value)
    {
        return $this->setParameter('encryptedCardNumber', $value);
    }
    
    public function getEncryptedCardCvv()
    {
        return $this->getParameter('encryptedCardCvv');
    }

    /**
     * Sets the encrypted card cvv, for use when submitting card data
     * encrypted using eWAY's client side encryption.
     *
     * @param string $value
     * @return RapidDirectAbstractRequest
     */
    public function setEncryptedCardCvv($value)
    {
        return $this->setParameter('encryptedCardCvv', $value);
    }
    
    protected function getBaseData()
    {

        $data = parent::getBaseData();
        $data['TransactionType'] = $this->getTransactionType();
        
        if ($this->getCardReference()) {
            $data['Customer']['TokenCustomerID'] = $this->getCardReference();
        } else {
            $this->validate('card');
        }
        
        if ($this->getCard()) {
            $data['Customer']['CardDetails'] = array();
            $data['Customer']['CardDetails']['Name'] = $this->getCard()->getName();
            $data['Customer']['CardDetails']['ExpiryMonth'] = $this->getCard()->getExpiryDate('m');
            $data['Customer']['CardDetails']['ExpiryYear'] = $this->getCard()->getExpiryDate('y');
            $data['Customer']['CardDetails']['CVN'] = $this->getCard()->getCvv();
            
            if ($this->getEncryptedCardNumber()) {
                $data['Customer']['CardDetails']['Number'] = $this->getEncryptedCardNumber();
            } else {
                $data['Customer']['CardDetails']['Number'] = $this->getCard()->getNumber();
            }
            
            if ($this->getEncryptedCardCvv()) {
                $data['Customer']['CardDetails']['CVN'] = $this->getEncryptedCardCvv();
            } else {
                $data['Customer']['CardDetails']['CVN'] = $this->getCard()->getCvv();
            }

            if ($this->getCard()->getStartMonth() and $this->getCard()->getStartYear()) {
                $data['Customer']['CardDetails']['StartMonth'] = $this->getCard()->getStartDate('m');
                $data['Customer']['CardDetails']['StartYear'] = $this->getCard()->getStartDate('y');
            }
            
            if ($this->getCard()->getIssueNumber()) {
                $data['Customer']['CardDetails']['IssueNumber'] = $this->getCard()->getIssueNumber();
            }
        }

        if ($this->getItems()) {
            $data['Items'] = $this->getItemData();
        }

        return $data;
    }

    public function sendData($data)
    {
        $httpResponse = $this->httpClient->post($this->getEndpoint(), null, json_encode($data))
            ->setAuth($this->getApiKey(), $this->getPassword())
            ->send();

        return $this->response = new RapidResponse($this, $httpResponse->json());
    }
}
