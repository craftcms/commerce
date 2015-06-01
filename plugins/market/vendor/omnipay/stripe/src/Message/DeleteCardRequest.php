<?php
/**
 * Stripe Delete Credit Card Request
 */

namespace Omnipay\Stripe\Message;

/**
 * Stripe Delete Credit Card Request
 *
 * This needs further work and/or explanation because it requires
 * a customer ID.
 *
 * @link https://stripe.com/docs/api#delete_card
 */
class DeleteCardRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('cardReference');

        return null;
    }

    public function getHttpMethod()
    {
        return 'DELETE';
    }

    public function getEndpoint()
    {
        return $this->endpoint.'/customers/'.$this->getCardReference();
    }
}
