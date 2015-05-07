<?php
/**
 * eWAY Rapid Responsive Shared Page Gateway
 */
 
namespace Omnipay\Eway;

use Omnipay\Common\AbstractGateway;

/**
 * eWAY Rapid Responsive Shared Page Gateway
 *
 * This class forms the gateway class for eWAY Rapid Responsive Sharesd Page requests.
 *
 * The eWAY Rapid gateways use an API Key and Password for authentication. 
 *
 * There is also a test sandbox environment, which uses a separate endpoint and 
 * API key and password. To access the eWAY Sandbox requires an eWAY Partner account.
 * https://myeway.force.com/success/partner-registration
 *
 *
 * @link https://eway.io/api-v3/#responsive-shared-page
 * @link https://eway.io/api-v3/#authentication
 * @link https://go.eway.io/s/article/How-do-I-setup-my-Live-eWAY-API-Key-and-Password
 */
class RapidSharedGateway extends AbstractGateway
{
    public function getName()
    {
        return 'eWAY Rapid Shared Page';
    }

    public function getDefaultParameters()
    {
        return array(
            'apiKey' => '',
            'password' => '',
            'testMode' => false,
        );
    }

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

    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RapidSharedPurchaseRequest', $parameters);
    }

    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RapidCompletePurchaseRequest', $parameters);
    }

    public function refund(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RefundRequest', $parameters);
    }
}
