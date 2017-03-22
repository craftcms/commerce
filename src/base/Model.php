<?php
namespace craft\commerce\base;

use craft\base\Model as BaseModel;

class Model extends BaseModel
{
    public static function populateModels($rows)
    {
        $output = [];
        foreach ($rows as $row) {
            $output[] = new static($row);
        }

        return $output;
    }
}