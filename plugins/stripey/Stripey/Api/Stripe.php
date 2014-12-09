<?php
namespace Stripey\Api;

use Cartalyst\Stripe\Api\Stripe as CartalystStripe;


class Stripe extends CartalystStripe{

    public function __construct()
    {
        $apiKey = stripey()->settings->getSettings()->secretKey;
        $this->stripe = new Stripe($apiKey);

        parent::__construct($apiKey,);
    }

} 