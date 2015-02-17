<?php
namespace Craft;

/**
 * Class Market_CustomerService
 *
 * @package Craft
 */
class Market_CustomerService extends BaseApplicationComponent
{
    const SESSION_CUSTOMER = 'session_customer_id_key';

    /**
     * @return Market_CustomerModel
     */
    public function getCustomer()
    {
        $userId = craft()->user->id;

        if($userId) {
            $record = Market_CustomerRecord::model()->with('addresses.address')->findByAttributes(['userId' => $userId]);
        } else {
            $id = craft()->session->get(self::SESSION_CUSTOMER);
            if($id) {
                $record = Market_CustomerRecord::model()->findById($id);
            }
        }

        if(!isset($record) || !$record->id) {
            $record = new Market_CustomerRecord;
        }

        return Market_CustomerModel::populateModel($record);
    }
}