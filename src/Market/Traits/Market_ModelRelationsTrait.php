<?php

namespace Market\Traits;
use Craft\BaseModel;
use Craft\BaseRecord;

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
            if(!$this->getRecord()) {
                return $this->isArrayRelation($name) ? [] : null;
            }

            $relations = $this->getRelations();
            $class = $relations[$name][1];
            $modelClass = '\Craft\\' . str_replace('Record', 'Model', $class);
            $value = $this->getRecord()->$name;

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
     * @return string
     */
    private function getRecordClass()
    {
        $class = get_class();
        $recordClass = str_replace('Model', 'Record', $class);
        return $recordClass;
    }

    /**
     * @return mixed
     */
    private function getRelations()
    {
        $recordClass = $this->getRecordClass();
        $record = new $recordClass;
        return $record->defineRelations();
    }

    /**
     * @param $name
     * @return bool
     */
    private function isRelation($name)
    {
        $relations = $this->getRelations();
        return isset($relations[$name]);
    }

    /**
     * @param $name
     * @return bool
     */
    private function isArrayRelation($name)
    {
        $relations = $this->getRelations();
        $type = $relations[$name][0];

        return in_array($type, [BaseRecord::HAS_MANY, BaseRecord::MANY_MANY]);
    }

    /**
     * @return BaseRecord
     */
    private function getRecord()
    {
        if(empty($this->_record) && $this->id) {
            $recordClass = $this->getRecordClass();
            $this->_record = $recordClass::model()->findByPk($this->id);
        }

        return $this->_record;
    }
}