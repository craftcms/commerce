<?php
/**
 * eWAY Rapid Response
 */
 
namespace Omnipay\Eway\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * eWAY Rapid Response
 * 
 * This is the response class for Rapid Direct & Transparent Redirect (Rapid)
 *
 */
class RapidResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function isRedirect()
    {
        return isset($this->data['FormActionURL']);
    }

    public function getRedirectUrl()
    {
        return isset($this->data['FormActionURL']) ? $this->data['FormActionURL'] : null;
    }

    public function getRedirectMethod()
    {
        return 'POST';
    }

    public function getRedirectData()
    {
        if ($this->isRedirect()) {
            return array(
                'EWAY_ACCESSCODE' => $this->data['AccessCode'],
            );
        }
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
        
        return null;
    }
}
