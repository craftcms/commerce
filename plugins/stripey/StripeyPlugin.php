<?php

namespace Craft;

use Stripey\Stripey;
use Stripey\Api\Stripe;


require 'vendor/autoload.php';


class StripeyPlugin extends BasePlugin
{
    function __construct()
    {
        Stripey::app()["stripe"] = function ($c) {
            $key = $this->getSettings()->secretKey;

            return new Stripe($key);
        };
    }

    public function getName()
    {
        return "Stripey";
    }

    public function getVersion()
    {
        return "0.0.1";
    }

    public function getDeveloper()
    {
        return "Make with Morph (Luke Holder)";
    }

    public function getDeveloperUrl()
    {
        return "http://makewithmorph.com";
    }

    public function hasCpSection()
    {
        return true;
    }


    public function registerCpRoutes()
    {
        return array(
            'stripey'                                                             => array('action' => 'stripey/dashboard/index'),

            'stripey/settings'                                                    => 'stripey/settings/index',

            'stripey/settings/producttypes'                                       => array('action' => 'stripey/productType/index'),
            'stripey/settings/producttypes/(?P<productTypeId>\d+)'                => array('action' => 'stripey/productType/editProductType'),
            'stripey/settings/producttypes/new'                                   => array('action' => 'stripey/productType/editProductType'),

            'stripey/products'                                                    => array('action' => 'stripey/product/productIndex'),
            'stripey/products/(?P<productTypeHandle>{handle})/new'                => array('action' => 'stripey/product/editProduct'),
            'stripey/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)' => array('action' => 'stripey/product/editProduct'),

            'stripey/plans'                                                       => array('action' => 'stripey/plans/index'),
            'stripey/charges'                                                     => 'stripey/charges/index',
            'stripey/charges/(?P<chargeId>\d+)'                                   => array('action' => 'stripey/charge/editCharge'),

            'stripey/settings'                                                    => array('action' => 'stripey/settings/edit')
        );
    }

    /**
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'secretKey'       => AttributeType::String,
            'publishableKey'  => AttributeType::String,
            //TODO: Fill currency enum values dynamically based on https://support.stripe.com/questions/which-currencies-does-stripe-support
            'defaultCurrency' => AttributeType::String
        );
    }

    public function onAfterInstall()
    {
//        $fieldLayout = array('type' => 'Stripey_Charge');
//        $fieldLayout = FieldLayoutModel::populateModel($fieldLayout);
//        craft()->fields->saveLayout($fieldLayout);
    }

    public function onBeforeUninstall()
    {
//        $fieldLayout = array('type' => 'Stripey_Charge');
//        $fieldLayout = FieldLayoutModel::populateModel($fieldLayout);
//        craft()->fields->saveLayout($fieldLayout);
    }


}

