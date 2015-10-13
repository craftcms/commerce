<?php

namespace Commerce\Traits;

use Craft\BaseRecord;

/**
 * Trait Commerce_ModelRelationsTrait provides a way for models to have relations
 * based on associated record's relations. Getters always take precedence over this.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   Commerce\Traits
 * @since     1.0
 */
trait Commerce_ModelRelationsTrait
{
    /** @var \craft\BaseRecord */
    private $_record;
    private $_relationsCache = [];

    /**
     * @param array|\craft\BaseRecord $values
     *
     * @return \craft\BaseModel
     */
    public static function populateModel($values)
    {
        $model = parent::populateModel($values);

        if (is_object($values)) {
            $model->_record = $values;
        }

        return $model;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        //getters have maximum priority anyway
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        if (isset($this->_relationsCache[$name])) {
            return $this->_relationsCache[$name];
        }

        if ($this->isRelation($name)) {
            if (!$this->getRecord()) {
                return $this->isArrayRelation($name) ? [] : null;
            }

            $relations = $this->getRelations();
            $class = $relations[$name][1];
            $modelClass = '\Craft\\' . str_replace('Record', 'Model', $class);
            $value = $this->getRecord()->$name;

            if (is_array($value)) {
                $this->_relationsCache[$name] = $modelClass::populateModels($value);
            } elseif ($value) {
                $this->_relationsCache[$name] = $modelClass::populateModel($value);
            } else {
                $this->_relationsCache[$name] = null;
            }

            return $this->_relationsCache[$name];
        }

        return parent::__get($name);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    private function isRelation($name)
    {
        $relations = $this->getRelations();

        return isset($relations[$name]);
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
     * @return string
     */
    private function getRecordClass()
    {
        $class = get_class();
        $recordClass = str_replace('Model', 'Record', $class);

        return $recordClass;
    }

    /**
     * @return BaseRecord
     */
    private function getRecord()
    {
        if (empty($this->_record) && $this->id) {
            $recordClass = $this->getRecordClass();
            $this->_record = $recordClass::model()->findByPk($this->id);
        }

        return $this->_record;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    private function isArrayRelation($name)
    {
        $relations = $this->getRelations();
        $type = $relations[$name][0];

        return in_array($type, [BaseRecord::HAS_MANY, BaseRecord::MANY_MANY]);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        if (isset($this->_relationsCache[$name]) || $this->isRelation($name)) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }
}