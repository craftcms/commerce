<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\test\ActiveFixture;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Base Model Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
 */
abstract class BaseModelFixture extends ActiveFixture
{
    /**
     * Name of the delete method in the service.
     *
     * @var string
     */
    public $deleteMethod;

    /**
     * Name of the save method in the service.
     *
     * @var string
     */
    public $saveMethod;

    /**
     * Instance of the service used for saving and deleting model data.
     */
    public $service;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        /**
         * Taken from Yii's ActiveFixture class.
         * Preventing using the parent init method as that is expecting $modelClass to be an active record class.
         */
        if ($this->tableName === null) {
            if ($this->modelClass === null) {
                throw new InvalidConfigException('Either "modelClass" or "tableName" must be set.');
            }
        }

        if ($this->service === null || $this->saveMethod === null || $this->deleteMethod === null) {
            throw new InvalidConfigException('"service", "saveMethod" and "deleteMethod" must be set.');
        }

        if (is_string($this->service)) {
            $this->service = Craft::$app->get($this->service);
        }
    }

    /**
     * @inheritDoc
     */
    public function load()
    {
        $this->data = [];
        $saveMethod = $this->saveMethod;

        foreach ($this->getData() as $key => $data) {
            // Call prep data for a chance to manipulate it before instantiation
            $data = $this->prepData($data);

            $model = new $this->modelClass($data);

            // Call prep model for a chance to manipulate it before save
            $model = $this->prepModel($model, $data);

            if (!$this->service->$saveMethod($model)) {
                throw new InvalidArgumentException('Unable to save model.');
            }

            $this->data[$key] = array_merge($data, ['id' => $model->id]);
            $this->ids[] = $model->id;
        }
    }

    /**
     * @inheritDoc
     */
    public function unload()
    {
        $deleteMethod = $this->deleteMethod;

        foreach ($this->data as $key => $data) {
            if (isset($data['id'])) {
                $this->service->$deleteMethod($data['id']);

                unset($this->data[$key]);
            }
        }
    }

    /**
     * Called before the data is instantiated on the model class.
     *
     * @param $data
     * @return mixed
     */
    protected function prepData($data) {
        return $data;
    }

    /**
     * Called after model has been instantiated and before the model is saved.
     *
     * @param $model
     * @param $data
     * @return mixed
     */
    protected function prepModel($model, $data) {
        return $model;
    }
}