<?php
namespace Craft;

class Stripey_OptionTypeController extends Stripey_BaseController
{
    protected $allowAnonymous = false;

    public function actionIndex()
    {
        $optionTypes = craft()->stripey_optionType->getAllOptionTypes();
        $this->renderTemplate('stripey/settings/optiontypes/index', compact('optionTypes'));
    }

    public function actionEditOptionType(array $variables = array())
    {
        $variables['brandNewOptionType'] = false;

        if (!empty($variables['optionTypeId'])) {

            $optionTypeId = $variables['optionTypeId'];

            $variables['optionType'] = craft()->stripey_optionType->getOptionTypeById($optionTypeId);

            if (!$variables['optionType']) {
                throw new HttpException(404);
            }
            $variables['title'] = $variables['optionType']->name;
        } else {
            if (empty($variables['optionType'])) {
                $variables['optionType']         = new Stripey_OptionTypeModel();
                $variables['brandNewOptionType'] = true;
            }
            $variables['title'] = Craft::t('Create a Option Type');
        };

        /**
         * Start of Option Value Table*/
        $cols = Stripey_OptionValueModel::editableColumns();
        $rows = $variables['optionType']->getOptionValues();
        $rows = array_map(function ($value) {
            return $value->toEditableRow();
        }, $rows);

        $variables['optionValuesTable'] = craft()->templates->render('stripey/_includes/forms/editableTable', array(
            'id'     => 'optionValues',
            'name'   => 'optionValues',
            'cols'   => $cols,
            'rows'   => $rows,
            'static' => false
        ));
        /**End of Option Value Table
         */

        $this->renderTemplate('stripey/settings/optiontypes/_edit', $variables);
    }

    public function actionSaveOptionType()
    {
        $this->requirePostRequest();

        // Build OptionType from Post
        $optionType = $this->_prepareOptionTypeModel();

        //Do we have optionValues and build OptionValues from post
        $optionValues = $this->_prepareOptionValueModels();

        // Save it
        if (craft()->stripey_optionType->saveOptionType($optionType)) {
            craft()->stripey_optionValue->saveOptionValuesForOptionType($optionType, $optionValues);
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
     * @return Stripey_OptionTypeModel
     */
    private function _prepareOptionTypeModel()
    {
        $optionType         = new Stripey_OptionTypeModel();
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
        $optionValues    = craft()->request->getPost('optionValues');
        $hasOptionValues = (bool)$optionValues;
        $optionValues    = array();
        if ($hasOptionValues) {
            $position = 0;

            foreach (craft()->request->getPost('optionValues') as $optionValue) {
                $position++;
                $id             = isset($optionValue['id']) ? $optionValue['id'] : null;
                $name           = $optionValue[0];
                $displayName    = $optionValue[1];
                $data           = compact('id', 'name', 'displayName', 'position');
                $optionValues[] = Stripey_OptionValueModel::populateModel($data);
            }
        }

        return $optionValues;
    }

    public function actionDeleteOptionType()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $optionTypeId = craft()->request->getRequiredPost('id');

        craft()->stripey_optionType->deleteOptionTypeById($optionTypeId);
        $this->returnJson(array('success' => true));
    }

}