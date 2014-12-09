<?php
namespace Craft;

class StripeyVariable
{
    public function plans()
    {
        return craft()->stripey_plans->getPlans();
    }
}