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

            if (!$variables['optionType'])
            {
                throw new HttpException(404);
            }

            $variables['title'] = $variables['optionType']->name;

        }else{
            if (empty($variables['optionType'])){
                $variables['optionType'] = new Stripey_OptionTypeModel();
                $variables['brandNewOptionType'] = true;
            }

            $variables['title'] = Craft::t('Create a Option Type');

        };

        /**
         * Start of Option Value Table
         */

        $cols = array(
            array('heading'=>'Name',
                  'type'=>'singleline',
                  'width'=>'50%'
            ),
            array('heading'=>'Display Name',
                  'type'=>'singleline',
                  'width'=>'50%'
            ),
        );
        $variables['optionValuesTable'] = craft()->templates->render('_includes/forms/editableTable', array(
            'id'     => 'optionValues',
            'name'   => 'optionValues',
            'cols'   => $cols,
            'rows'   => array(array("","")),
            'static' => array()
        ));

        /**
         * End of Option Value Table
         */


        $this->renderTemplate('stripey/settings/optiontypes/_edit', $variables);
    }

    public function actionSaveOptionType()
    {
        $this->requirePostRequest();

        $optionType = new Stripey_OptionTypeModel();

        // Shared attributes
        $optionType->id         = craft()->request->getPost('optionTypeId');
        $optionType->name       = craft()->request->getPost('name');
        $optionType->handle     = craft()->request->getPost('handle');

        // Save it
        if (craft()->stripey_optionType->saveOptionType($optionType))
        {
            craft()->userSession->setNotice(Craft::t('Option Type saved.'));
            $this->redirectToPostedUrl($optionType);
        }
        else
        {
            craft()->userSession->setError(Craft::t('Couldn’t save Option Type.'));
        }

        // Send the calendar back to the template
        craft()->urlManager->setRouteVariables(array(
            'optionType' => $optionType
        ));
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