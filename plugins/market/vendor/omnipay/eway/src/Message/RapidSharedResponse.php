<?php
/**
 * eWAY Rapid Shared Purchase Response
 */

namespace Omnipay\Eway\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * eWAY Rapid Shared Purchase Response
 *
 * @link https://eway.io/api-v3/#responsive-shared-page
 */
class RapidSharedResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function isRedirect()
    {
        return isset($this->data['SharedPaymentUrl']);
    }

    public function getRedirectUrl()
    {
        return isset($this->data['SharedPaymentUrl']) ? $this->data['SharedPaymentUrl'] : null;
    }

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectData()
    {
        return null;
    }
    
    /**
     * Get a card reference (eWAY Token), for createCard requests.
     *
     * @return string|null
     */
    public function getCardReference()
    {
        if (isset($this->data['Customer']['TokenCustomerID'])) {
            return $this->data['Customer']['TokenCustomerID'];
        }
    }
}
