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

    /** @var Market_CustomerModel */
    private $customer = null;

    /**
     * @return Market_CustomerModel
     */
    public function getCustomer()
    {
        if($this->customer === null) {
            $userId = craft()->user->id;

            if($userId) {
                $record = Market_CustomerRecord::model()->findByAttributes(['userId' => $userId]);
            } else {
                $id = craft()->session->get(self::SESSION_CUSTOMER);
                if($id) {
                    $record = Market_CustomerRecord::model()->findById($id);
                }
            }

            if(empty($record)) {
                $record = new Market_CustomerRecord;
            }

            $this->customer = Market_CustomerModel::populateModel($record);
        }

        return $this->customer;
    }

    /**
     * @return bool
     */
    public function isSaved()
    {
        return !!$this->getCustomer()->id;
    }

    /**
     * @return Market_CustomerModel
     */
/*    private function getSavedCustomer()
    {
        $customer = $this->getCustomer();
        if(!$customer->id) {
            $this->save($customer);
        }

        return $customer;
    }*/


//    public function saveAddress(Market_AddressModel $address)
//    {
//    }

/*    private function save(Market_CustomerModel $customer)
    {
        if (!$customer->id) {
            $customerRecord = new Market_CustomerRecord();
        } else {
            $customerRecord = Market_CustomerRecord::model()->findById($customer->id);

            if (!$customerRecord) {
                throw new Exception(Craft::t('No customer exists with the ID “{id}”', array('id' => $customer->id)));
            }
        }

        $customerRecord->email = $customer->email;
        $customerRecord->userId = $customer->userId;


        $customerRecord->validate();
        $customer->addErrors($customerRecord->getErrors());

        if (!$customer->hasErrors()) {
            if (craft()->elements->saveElement($customer)) {
                $customerRecord->id     = $customer->id;
                $customerRecord->save(false);

                return true;
            }
        }
        return false;
    }*/
}