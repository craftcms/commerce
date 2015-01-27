<?php
namespace Craft;

class Cellar_PaymentService extends BaseApplicationComponent
{
    public function getTransactions()
    {
        $records = Cellar_TransactionRecord::model()->findAll();

        if ($records) {
            return Cellar_TransactionModel::populateModels($records);
        }

        return null;
    }

    public function getPaymentMethod($class, $enabledOnly = false)
    {
        $record = $this->_getPaymentMethodRecord($class);

        if ($enabledOnly) {
            if (!$record) {
                return null;
            }

        } else {
            if (!$record) {
                $gateway = craft()->cellar_gateways->getGateway($class);

                $model = new Cellar_PaymentMethodModel;
                $model->class = $gateway->getShortName();
                $model->name = $gateway->getName();
            }
        }

        if ($record) {
            $model = Cellar_PaymentMethodModel::populateModel($record);
        }

        return $model;
    }

    public function getPaymentMethods($enabledOnly = false)
    {
        if ($enabledOnly) {
            $records = $this->_getPaymentMethodRecords();

            $paymentMethods = Cellar_PaymentMethodModel::populateModels($records);

        } else {
            $paymentMethods = array();

            $gateways = craft()->cellar_gateways->getGateways();

            foreach ($gateways as $gateway) {

                $paymentMethod = $this->getPaymentMethod($gateway->getShortName(), $enabledOnly);

                if ($paymentMethod) {
                    array_push($paymentMethods, $paymentMethod);
                }
            }
        }

        return $paymentMethods;
    }

    public function _getPaymentMethodRecord($class)
    {
        $conditions = 'class=:class';

        $params = array(':class' => $class);

        $record = Cellar_PaymentMethodRecord::model()->find($conditions, $params);

        return $record;
    }

    public function _getPaymentMethodRecords()
    {
        $records = Cellar_PaymentMethodRecord::model()->findAll();

        return $records;
    }
}