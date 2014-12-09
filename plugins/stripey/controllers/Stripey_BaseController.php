<?php

namespace Craft;


class Stripey_BaseController extends BaseController
{

    /** @var  StripeyPlugin */
    private $plugin;

    /**
     * @return null|void
     */
    public function init()
    {
        $this->plugin = craft()->plugins->getPlugin('stripey');
    }

    /**
     * @return StripeyPlugin
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

} 