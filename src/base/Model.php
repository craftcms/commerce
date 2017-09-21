<?php

namespace craft\commerce\base;

use craft\base\Model as BaseModel;

class Model extends BaseModel
{
    public static function populateModels($data, $indexBy = null)
    {
        $models = [];

        if (is_array($data)) {
            foreach ($data as $values) {
                $model = new static($values);

                if ($indexBy) {
                    $models[$model->$indexBy] = $model;
                } else {
                    $models[] = $model;
                }
            }
        }

        return $models;
    }


}