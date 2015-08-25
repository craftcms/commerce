<?php

namespace Market\Interfaces;


interface Purchasable
{

    /**
     * Returns the id of the Purchasable that should be added to the lineitem
     * @return int
     */
    public function getPurchasableId();

    /*
     * This is an array of data that should be saved in a serialized way to the line item.
     *
     * Use it as a way to store data on the lineItem even after the purchasable may be deleted.
     * We will automatically add all attributes returned by ```->getAttributes()``` in addition to the ones you define here.
     * This snapshot gets passed to your Model's Class in a ```$modelClass::populateModel($snaphot)``` if
     * your Purchasable ```->getModelClass()``` is available, otherwise it returns just the snapshot data as an array.
     *
     * Below is an example of the snapshot data you could add in addition to the attributes of your model. Remember
     * that if you use the same array key as the model's attributes, the these items will will overridden.
     *
     * Example: $data = array('ticketType' => 'full',
     *                       'location' => 'N');
     *
     *
     * @return array
     */
    public function getSnapshot();


    /**
     * This is the className that used to populate the model from the snapshot if the purchasable is deleted
     *
     * Example: return 'Market_VariantModel';
     *
     * Would we used like this $modelClass::populateModel($snapshot);
     *
     * @return array
     */
    public function getModelClass();

    /**
     * This is the base price the item will be added to the line item with.
     *
     * @return float decimal(14,4)
     */
    public function getPrice();


    /**
     * This must be a unique code from the purchasables table
     *
     * @return string
     */
    public function getSku();

    // General description to be used on line items and orders.
    public function getDescription();


    /**
     * Validates this purchasable for the line item it is on.
     *
     * You can add model errors to the line item like this: `$lineItem->addError('qty', $errorText);`
     *
     * @param \Craft\Market_LineItemModel $lineItem
     *
     * @return mixed
     */
    public function validateLineItem(\Craft\Market_LineItemModel $lineItem);
}