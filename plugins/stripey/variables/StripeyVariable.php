<?php
namespace Craft;

class StripeyVariable
{
    /**
     * Get Stripe Plans
     *
     * @return mixed
     */
    public function plans()
    {
        return craft()->stripey_plans->getPlans();
    }

    /**
     * Get Stripey settings
     *
     * @return mixed
     */
    public function config()
    {
        return stripey()->settings->getSettings();
    }

    /**
     * @param array|null $criteria
     *
     * @return ElementCriteriaModel|null
     */
    public function charges($criteria = null)
    {
            return craft()->elements->getCriteria('Stripey_Charge', $criteria);
    }
}