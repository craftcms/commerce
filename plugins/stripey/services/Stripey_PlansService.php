<?php
namespace Craft;

class Stripey_PlansService extends BaseApplicationComponent{

    public function getPlans()
    {
        $apiKey = $this->getPlugin()->getSettings()->secretKey;
        $stripe = new Stripe($apiKey);
        $plans = $stripe->plans()->all();
        return $plans['data'];
    }
} 