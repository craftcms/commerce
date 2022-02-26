<?php

namespace craft\commerce\behaviors;

use Craft;
use craft\commerce\Plugin;
use craft\commerce\records\Store;
use craft\elements\Address;
use craft\events\DefineRulesEvent;
use craft\events\ModelEvent;
use DvK\Vat\Validator;
use yii\base\Behavior;

class StoreLocationBehavior extends Behavior
{
    /** @var Address */
    public $owner;

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        if (!$owner instanceof Address) {
            throw new \RuntimeException('StoreLocationBehavior can only be attached to an Address element');
        }

        parent::attach($owner);
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            Address::EVENT_AFTER_SAVE => 'saveStoreLocation',
        ];
    }

    /**
     * @param ModelEvent $event
     * @return void
     */
    public function saveStoreLocation(ModelEvent $event): void
    {
        $address = $event->sender;
        $store = Store::find()->one(); // we only have one store right now and we assume it is the first one
        $store->locationAddressId = $address->id;
        $store->save();
    }
}