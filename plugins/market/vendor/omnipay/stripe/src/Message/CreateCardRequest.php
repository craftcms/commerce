<?php
/**
 * Stripe Create Credit Card Request
 */

namespace Omnipay\Stripe\Message;

/**
 * Stripe Create Credit Card Request
 *
 * This doesn't actually create a card, it creates a customer.
 *
 * See issue #8
 *
 * @link https://github.com/thephpleague/omnipay-stripe/issues/8
 */
class CreateCardRequest extends AbstractRequest
{
    public function getData()
    {
        $data = array();
        $data['description'] = $this->getDescription();

        if ($this->getToken()) {
            $data['card'] = $this->getToken();
        } elseif ($this->getCard()) {
            $data['card'] = $this->getCardData();
            $data['email'] = $this->getCard()->getEmail();
        } else {
            // one of token or card is required
            $this->validate('card');
        }

        return $data;
    }

    public function getEndpoint()
    {
        return $this->endpoint.'/customers';
    }
}
