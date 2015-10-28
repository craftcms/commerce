<?php
namespace Craft;

/**
 * Class Commerce_VariantsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_VariantsController extends Commerce_BaseAdminController
{
    /**
     * Create/Edit State
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {

        craft()->version;
        //getting related product
        if (empty($variables['productId'])) {
            throw new HttpException(400);
        }

        $variables['product'] = craft()->commerce_products->getById($variables['productId']);
        if (!$variables['product']) {
            throw new HttpException(404, craft::t('Product not found'));
        }

        //getting variant model
        if (empty($variables['variant'])) {
            if (!empty($variables['id'])) {
                $variables['variant'] = craft()->commerce_variants->getById($variables['id']);

                if (!$variables['variant']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['variant'] = new Commerce_VariantModel();
            };
        }

        $variables['productType'] = craft()->commerce_productTypes->getByHandle($variables['productTypeHandle']);
        $this->prepVariables($variables);

        if (!empty($variables['variant']->id)) {
            $variables['title'] = Craft::t('Variant for {product}',
                ['product' => $variables['product']]);
        } else {
            $variables['title'] = Craft::t('Create a Variant for {product}',
                ['product' => $variables['product']]);
        }

        $this->renderTemplate('commerce/products/variants/_edit', $variables);
    }

    /**
     * Modifies the variables of the request.
     *
     * @param $variables
     */
    private function prepVariables(&$variables)
    {
        $variables['tabs'] = [];

        foreach ($variables['productType']->asa('variantFieldLayout')->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;
            if ($variables['variant']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($variables['variant']->getErrors($field->getField()->handle)) {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t($tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => ($hasErrors ? 'error' : null)
            ];
        }
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $variant = new Commerce_VariantModel();

        // Shared attributes
        $params = [
            'id',
            'productId',
            'sku',
            'price',
            'width',
            'height',
            'length',
            'weight',
            'stock',
            'unlimitedStock',
            'minQty',
            'maxQty',
        ];
        foreach ($params as $param) {
            $variant->$param = craft()->request->getPost($param);
        }

        $variant->setContentFromPost('fields');

        // Save it
        if (craft()->commerce_variants->save($variant)) {

            craft()->userSession->setNotice(Craft::t('Variant saved.'));
            $this->redirectToPostedUrl($variant);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save variant.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables([
            'variant' => $variant
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_variants->deleteById($id);
        $this->redirectToPostedUrl();
    }
}
