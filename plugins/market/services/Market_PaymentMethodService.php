<?php
namespace Craft;

/**
 * Class Market_PaymentMethodService
 *
 * @package Craft
 */
class Market_PaymentMethodService extends BaseApplicationComponent
{
	const CP_ENABLED = 'cpEnabled';
	const FRONTEND_ENABLED = 'frontendEnabled';

	/**
	 * @param int $id
	 *
	 * @return Market_PaymentMethodModel
	 */
	public function getById($id)
	{
		$record = Market_PaymentMethodRecord::model()->findById($id);

		return Market_PaymentMethodModel::populateModel($record);
	}

	/**
	 * @param string $enabled CP_ENABLED | FRONTEND_ENABLED
	 *
	 * @return Market_PaymentMethodModel[]
	 */
	public function getAll($enabled = '')
	{
		$this->filterEnabled($enabled);
		if ($enabled) {
			$records        = Market_PaymentMethodRecord::model()->findAllByAttributes(array($enabled => true));
			$paymentMethods = Market_PaymentMethodModel::populateModels($records);
		} else {
			$paymentMethods = array();
			$gateways       = craft()->market_gateway->getGateways();

			foreach ($gateways as $gateway) {
				$paymentMethods[] = $this->getByClass($gateway->getShortName());
			}
		}

		return $paymentMethods;
	}

	/**
	 * @param string $enabled
	 */
	private function filterEnabled(&$enabled)
	{
		if (!in_array($enabled, array(self::CP_ENABLED, self::FRONTEND_ENABLED), true)) {
			$enabled = '';
		}
	}

	/**
	 * @param string $class
	 * @param string $enabled CP_ENABLED | FRONTEND_ENABLED
	 *
	 * @return Market_PaymentMethodModel
	 */
	public function getByClass($class, $enabled = '')
	{
		$record = Market_PaymentMethodRecord::model()->findByAttributes(array('class' => $class));

		$this->filterEnabled($enabled);
		if ($enabled && (!$record || !$record->$enabled)) {
			return NULL;
		}

		if ($record) {
			$model = Market_PaymentMethodModel::populateModel($record);
		} else {
			$gateway = craft()->market_gateway->getGateway($class);

			$model           = new Market_PaymentMethodModel;
			$model->class    = $gateway->getShortName();
			$model->name     = $gateway->getName();
			$model->settings = $gateway->getDefaultParameters();
		}

		return $model;
	}

	/**
	 * @param Market_PaymentMethodModel $model
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function save(Market_PaymentMethodModel $model)
	{
		$record = Market_PaymentMethodRecord::model()->findByAttributes(array('class' => $model->class));
		if (!$record) {
			$gateway = craft()->market_gateway->getGateway($model->class);

			if (!$gateway) {
				throw new Exception(Craft::t('No gateway exists with the class name “{class}”', array('class' => $model->class)));
			}
			$record       = new Market_PaymentMethodRecord();
			$record->name = $gateway->getName();
		}

		$record->class           = $model->class;
		$record->settings        = $model->settings;
		$record->cpEnabled       = $model->cpEnabled;
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
}