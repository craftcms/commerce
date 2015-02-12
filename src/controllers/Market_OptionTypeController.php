<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_OptionTypeController extends Market_BaseController
{
	protected $allowAnonymous = false;

	public function actionIndex()
	{
		$optionTypes = craft()->market_optionType->getAll();
		$this->renderTemplate('market/settings/optiontypes/index', compact('optionTypes'));
	}

	public function actionEditOptionType(array $variables = array())
	{
		$variables['brandNewOptionType'] = false;

		if (empty($variables['optionType'])) {
			if (!empty($variables['optionTypeId'])) {
				$optionTypeId            = $variables['optionTypeId'];
				$variables['optionType'] = craft()->market_optionType->getById($optionTypeId);

				if (!$variables['optionType']) {
					throw new HttpException(404);
				}
			} else {
				$variables['optionType']         = new Market_OptionTypeModel();
				$variables['brandNewOptionType'] = true;
			};
		}

		if (!empty($variables['optionTypeId'])) {
			$variables['title'] = $variables['optionType']->name;
		} else {
			$variables['title'] = Craft::t('Create an Option Type');
		}

		/**
		 * Start of Option Value Table*/
		$cols = Market_OptionValueModel::editableColumns();
		$rows = $variables['optionType']->getOptionValues();

		$variables['optionValuesTable'] = craft()->templates->render('market/_includes/forms/editableTable', array(
			'id'     => 'optionValues',
			'name'   => 'optionValues',
			'cols'   => $cols,
			'rows'   => $rows,
			'static' => false
		));
		/**End of Option Value Table
		 */

		$this->renderTemplate('market/settings/optiontypes/_edit', $variables);
	}

	public function actionSaveOptionType()
	{
		$this->requirePostRequest();

		// Build OptionType from Post
		$optionType = $this->_prepareOptionTypeModel();

		//Do we have optionValues and build OptionValues from post
		$optionValues = $this->_prepareOptionValueModels();

		// Save it
		if (craft()->market_optionType->save($optionType)) {
			craft()->market_optionValue->saveOptionValuesForOptionType($optionType, $optionValues);
			craft()->userSession->setNotice(Craft::t('Option Type and Values saved.'));
			$this->redirectToPostedUrl($optionType);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save Option Type.'));
		}

		// Send the calendar back to the template
		craft()->urlManager->setRouteVariables(array(
			'optionType' => $optionType
		));
	}

	/**
	 * @return Market_OptionTypeModel
	 */
	private function _prepareOptionTypeModel()
	{
		$optionType         = new Market_OptionTypeModel();
		$optionType->id     = craft()->request->getPost('optionTypeId');
		$optionType->name   = craft()->request->getPost('name');
		$optionType->handle = craft()->request->getPost('handle');

		return $optionType;
	}

	/**
	 * @return array
	 */
	private function _prepareOptionValueModels()
	{
		$optionValues = array();
		$position     = 0;
		if (!craft()->request->getPost('optionValues')) {
			return $optionValues;
		}
		foreach (craft()->request->getPost('optionValues') as $optionValue) {
			$position++;
			$id             = isset($optionValue['current']) ? $optionValue['current'] : NULL;
			$name           = isset($optionValue['name']) ? $optionValue['name'] : NULL;
			$displayName    = isset($optionValue['displayName']) ? $optionValue['displayName'] : NULL;
			$data           = compact('id', 'name', 'displayName', 'position');
			$optionValues[] = Market_OptionValueModel::populateModel($data);
		}

		return $optionValues;
	}

	public function actionDeleteOptionType()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$optionTypeId = craft()->request->getRequiredPost('id');

		craft()->market_optionType->deleteById($optionTypeId);
		$this->returnJson(array('success' => true));
	}

}