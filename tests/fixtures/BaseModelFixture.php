<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use yii\base\ArrayAccessTrait;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\test\DbFixture;
use yii\test\FileFixtureTrait;

/**
 * Base Model Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
 */
abstract class BaseModelFixture extends DbFixture implements \IteratorAggregate, \ArrayAccess, \Countable
{
    use ArrayAccessTrait;
    use FileFixtureTrait;

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
     * @var array the data rows. Each array element represents one row of data (column name => column value).
     */
    public $data = [];

    /**
     * @var array
     */
    protected $ids = [];

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
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

    protected function getData(): array
    {
        return $this->loadData($this->dataFile, false);
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
    protected function prepData($data)
    {
        return $data;
    }

    /**
     * Called after model has been instantiated and before the model is saved.
     *
     * @param $model
     * @param $data
     * @return mixed
     */
    protected function prepModel($model, $data)
    {
        return $model;
    }
}
