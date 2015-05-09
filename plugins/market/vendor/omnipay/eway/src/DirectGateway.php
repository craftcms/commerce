<?php
/**
 * eWAY Legacy Direct XML Payments Gateway
 */
 
namespace Omnipay\Eway;

use Omnipay\Common\AbstractGateway;

/**
 * eWAY Legacy Direct XML Payments Gateway
 *
 * This class forms the gateway class for eWAY Legacy Direct XML requests.
 *
 * NOTE: The APIs called by this gateway are older legacy APIs, new integrations should instead
 * use eWAY Rapid.
 *
 */
class DirectGateway extends AbstractGateway
{
    public function getName()
    {
        return 'eWAY Direct';
    }

    public function getDefaultParameters()
    {
        return array(
            'customerId' => '',
            'testMode' => false,
        );
    }

    public function getCustomerId()
    {
        return $this->getParameter('customerId');
    }

    public function setCustomerId($value)
    {
        return $this->setParameter('customerId', $value);
    }

    public function authorize(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\DirectAuthorizeRequest', $parameters);
    }

    public function capture(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\DirectCaptureRequest', $parameters);
    }

    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\DirectPurchaseRequest', $parameters);
    }

    public function refund(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\DirectRefundRequest', $parameters);
    }

    public function void(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\DirectVoidRequest', $parameters);
    }
}
