<?php
namespace Craft;

use Cartalyst\Stripe\Api\Stripe;

/**
 * Class Stripey_ApiService
 *
 * @package Craft
 */
class Stripey_ApiService extends BaseApplicationComponent
{
    /** @var Stripe $stripe */
    public $stripe;

    /**
     * setup connection to stripe api
     */
    public function init()
    {
        // TODO: Handle failure by testing if key works.
        $apiKey = stripey()->settings->getSettings()->secretKey;
        $this->stripe = new Stripe($apiKey);
    }

} 