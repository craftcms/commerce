<?php
namespace Craft;

/**
 * Class Stripey_PaymentMethodService
 * @package Craft
 */
class Stripey_PaymentMethodService extends BaseApplicationComponent
{
    const CP_ENABLED = 'cpEnabled';
    const FRONTEND_ENABLED = 'frontendEnabled';

//    public function getTransactions()
//    {
//        $records = Cellar_TransactionRecord::model()->findAll();
//
//        if ($records) {
//            return Cellar_TransactionModel::populateModels($records);
//        }
//
//        return null;
//    }

    /**
     * @param string $class
     * @param string $enabled CP_ENABLED | FRONTEND_ENABLED
     * @return Stripey_PaymentMethodModel
     */
    public function getByClass($class, $enabled = '')
    {
        $record = Stripey_PaymentMethodRecord::model()->findByAttributes(array('class' => $class));

        $this->filterEnabled($enabled);
        if ($enabled && (!$record || !$record->$enabled)) {
            return null;
        }

        if($record) {
            $model = Stripey_PaymentMethodModel::populateModel($record);
        } else {
            $gateway = craft()->stripey_gateway->getGateway($class);

            $model = new Stripey_PaymentMethodModel;
            $model->class = $gateway->getShortName();
            $model->name = $gateway->getName();
            $model->settings = $gateway->getDefaultParameters();
        }

        return $model;
    }

    /**
     * @param string $enabled CP_ENABLED | FRONTEND_ENABLED
     * @return Stripey_PaymentMethodModel[]
     */
    public function getAll($enabled = '')
    {
        $this->filterEnabled($enabled);
        if ($enabled) {
            $records = Stripey_PaymentMethodRecord::model()->findAllByAttributes(array($enabled => true));
            $paymentMethods = Stripey_PaymentMethodModel::populateModels($records);
        } else {
            $paymentMethods = array();
            $gateways = craft()->stripey_gateway->getGateways();

            foreach ($gateways as $gateway) {
                $paymentMethods[] = $this->getByClass($gateway->getShortName());
            }
        }

        return $paymentMethods;
    }

    /**
     * @param Stripey_PaymentMethodModel $model
     * @return bool
     * @throws Exception
     */
    public function save(Stripey_PaymentMethodModel $model) {
        $record = Stripey_PaymentMethodRecord::model()->findByAttributes(array('class' => $model->class));
        if (!$record) {
            $gateway = craft()->stripey_gateway->getGateway($model->class);

            if(!$gateway) {
                throw new Exception(Craft::t('No gateway exists with the class name “{class}”', array('class' => $model->class)));
            }
            $record = new Stripey_PaymentMethodRecord();
            $record->name = $gateway->getName();
        }

        $record->class = $model->class;
        $record->settings = $model->settings;
        $record->cpEnabled = $model->cpEnabled;
        $record->frontendEnabled = $model->frontendEnabled;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $enabled
     */
    private function filterEnabled(&$enabled) {
        if(!in_array($enabled, array(self::CP_ENABLED, self::FRONTEND_ENABLED), true)) {
            $enabled = '';
        }
    }
}