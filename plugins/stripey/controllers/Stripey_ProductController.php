<?php
namespace Craft;

/**
 * Class Stripey_ProductController
 *
 * @package Craft
 */

class Stripey_ProductController extends Stripey_BaseController
{
    protected $allowAnonymous = false;


    public function actionProductIndex()
    {
        $variables['productTypes'] = craft()->stripey_productType->getAllProductTypes();
        $this->renderTemplate('stripey/products/_index', $variables);
    }

    /**
     * The public Charge creation action.
     */
    public function actionSaveProduct()
    {
        $this->requirePostRequest();

        $productId = craft()->request->getPost('productId');

        if ($productId)
        {
            $product = craft()->stripey_product->getProductById($productId);

            if (!$product)
            {
                throw new Exception(Craft::t('No event product with the ID “{id}”', array('id' => $productId)));
            }
        }
        else
        {
            $product = new Stripey_ProductModel();
        }

        $product->availableOn = (($startDate = craft()->request->getPost('availableOn')) ? DateTime::createFromString($startDate, craft()->timezone) : null);
        $product->expiresOn   = (($startDate = craft()->request->getPost('expiresOn')) ? DateTime::createFromString($startDate, craft()->timezone) : null);
        $product->typeId      = craft()->request->getPost('typeId');

        $product->getContent()->title = craft()->request->getPost('title', $product->title);
        $product->setContentFromPost('fields');

        $chargeCreator = new \Stripey\Product\Creator;

        if ($chargeCreator->save($product)) {
            craft()->userSession->setNotice(Craft::t('Product saved.'));
            $this->redirectToPostedUrl($product);
        } else {

            craft()->userSession->setNotice(Craft::t("Couldn't save product."));
            craft()->urlManager->setRouteVariables(array(
                'product' => $product
            ));
        }
    }

    public function actionEditProduct(array $variables = array())
    {
        $variables['brandNewProduct'] = false;


        if (!empty($variables['productTypeHandle']))
        {
            $variables['productType'] = craft()->stripey_productType->getProductTypeByHandle($variables['productTypeHandle']);
        }
        else if (!empty($variables['productTypeId']))
        {
            $variables['productType'] = craft()->stripey_productType->getProductTypeById($variables['productTypeId']);
        }


        if (!empty($variables['productId'])) {

            $productId = $variables['productId'];

            $variables['product'] = craft()->stripey_product->getProductById($productId);

            if (!$variables['product'])
            {
                throw new HttpException(404);
            }

            $variables['title'] = $variables['product']->title;

        }else{
            if (empty($variables['product'])){
                $variables['product'] = new Stripey_ProductModel();
                $variables['product']->typeId = $variables['productType']->id;
                $variables['brandNewProduct'] = true;
            }

            $variables['title'] = Craft::t('Create a new Product');

        };

        $this->prepVariables($variables);

        $this->renderTemplate('stripey/products/_edit', $variables);
    }

    public function actionDeleteProduct()
    {
        $this->requirePostRequest();

        $productId = craft()->request->getRequiredPost('productId');
        $product = craft()->stripey_product->getProductById($productId);

        if (!$product)
        {
            throw new Exception(Craft::t('No product exists with the ID “{id}”.', array('id' => $productId)));
        }

        if (craft()->stripey_product->deleteProduct($product))
        {
            if (craft()->request->isAjaxRequest())
            {
                $this->returnJson(array('success' => true));
            }
            else
            {
                craft()->userSession->setNotice(Craft::t('Product deleted.'));
                $this->redirectToPostedUrl($product);
            }
        }
        else
        {
            if (craft()->request->isAjaxRequest())
            {
                $this->returnJson(array('success' => false));
            }
            else
            {
                craft()->userSession->setError(Craft::t('Couldn’t delete product.'));

                craft()->urlManager->setRouteVariables(array(
                    'product' => $product
                ));
            }
        }
    }


    private function prepVariables(&$variables)
    {
        $variables['tabs'] = array();

        foreach ($variables['productType']->getFieldLayout()->getTabs() as $index => $tab)
        {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($variables['product']->hasErrors())
            {
                foreach ($tab->getFields() as $field)
                {
                    if ($variables['product']->getErrors($field->getField()->handle))
                    {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = array(
                'label' => Craft::t($tab->name),
                'url'   => '#tab'.($index+1),
                'class' => ($hasErrors ? 'error' : null)
            );
        }
    }

} 