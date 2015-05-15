<?php
/**
 * eWAY Rapid Complete Purchase Request
 */
 
namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Complete Purchase Request
 *
 * @link https://eway.io/api-v3/#step-3-request-the-results
 */
class RapidCompletePurchaseRequest extends RapidPurchaseRequest
{
    public function getData()
    {
        return array('AccessCode' => $this->httpRequest->query->get('AccessCode'));
    }

    protected function getEndpoint()
    {
        return $this->getEndpointBase().'/GetAccessCodeResult.json';
    }
}
