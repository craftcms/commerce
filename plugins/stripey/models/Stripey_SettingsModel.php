<?php

namespace Craft;


class Stripey_SettingsModel extends BaseModel{

    protected function defineAttributes()
    {
        return array(
            'secretKey'      => AttributeType::String,
            'publishableKey' => AttributeType::String
        );
    }

    public function rules()
    {
        return array(
          array('secretKey','required'),
          array('publishableKey','required')
        );
    }
} 