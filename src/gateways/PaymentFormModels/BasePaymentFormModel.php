<?php

namespace craft\commerce\gateway\models;

use craft\base\Model;

abstract class BasePaymentFormModel extends Model
{
    /**
     * @param $post
     */
    public function populateModelFromPost($post)
    {
        foreach ($this->getAttributes() as $attr => $value) {
            $this->$attr = $post;
        }
    }
}