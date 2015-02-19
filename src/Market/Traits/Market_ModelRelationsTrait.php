<?php

namespace Market\Traits;

/**
 * Enables access to the all record's relations through a model
 *
 * Class Market_ModelRelationsTrait
 * @package craft
 */
trait Market_ModelRelationsTrait {
    /** @var \craft\BaseRecord */
    private $_record;
    private $_relationsCache = [];

    /**
     * @param array|\craft\Market_OrderRecord $values
     * @return \craft\Market_OrderModel
     */
    public static function populateModel($values)
    {
        /** @var self $model */
        $model = parent::populateModel($values);

        if(is_object($values)) {
            $model->_record = $values;
        }

        return $model;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if(isset($this->_relationsCache[$name])) {
            return $this->_relationsCache[$name];
        }

        if($this->isRelation($name)) {
            $relations = $this->_record->defineRelations();
            $class = $relations[$name][1];
            $modelClass = '\Craft\\' . str_replace('Record', 'Model', $class);
            $value = $this->_record->$name;

            if(is_array($value)) {
                $this->_relationsCache[$name] = $modelClass::populateModels($value);
            } else {
                $this->_relationsCache[$name] = $modelClass::populateModel($value);
            }

            return $this->_relationsCache[$name];
        }

        return parent::__get($name);
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        if(isset($this->_relationsCache[$name]) || $this->isRelation($name)) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }

    /**
     * @param $name
     * @return bool
     */
    private function isRelation($name)
    {
        if(empty($this->_record)) {
            return false;
        }

        $relations = $this->_record->defineRelations();
        return isset($relations[$name]);
    }
}