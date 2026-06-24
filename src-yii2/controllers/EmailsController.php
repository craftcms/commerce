<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\helpers\Locale as LocaleHelper;
use craft\commerce\models\Email;
use craft\commerce\models\Pdf;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\models\Site;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Emails Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class EmailsController extends BaseAdminController
{
    /**
     * @throws InvalidConfigException
     */
    public function actionIndex(): Response
    {
        $emails = [];
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $stores->each(function(Store $store) use (&$emails) {
            $emails[$store->handle] = Plugin::getInstance()->getEmails()->getAllEmails($store->id);
        });
        $stores = $stores->all();

        return $this->renderTemplate('commerce/settings/emails/index', [
            'stores' => $stores,
            'emails' => $emails,
            'readOnly' => $this->isReadOnlyScreen(),
        ]);
    }

    /**
     * @param int|null $id
     * @param Email|null $email
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, Email $email = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        if (!$email) {
            if ($id) {
                $email = Plugin::getInstance()->getEmails()->getEmailById($id, $store->id);

                if (!$email) {
                    throw new HttpException(404);
                }
            } else {
                $email = Craft::createObject([
                    'class' => Email::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        $title = $email->id ? $email->name : Craft::t('commerce', 'Create a new email');

        DebugPanel::prependOrAppendModelTab(model: $email, prepend: true);

        $pdfs = Plugin::getInstance()->getPdfs()->getAllPdfs($email->storeId);
        $pdfList = [null => Craft::t('commerce', 'Do not attach a PDF to this email')];
        $pdfList = ArrayHelper::merge($pdfList, $pdfs->mapWithKeys(fn(Pdf $pdf) => [$pdf->id => $pdf->name])->all());
        $senderAddressPlaceholder = App::mailSettings()->fromEmail;
        $senderNamePlaceholder = App::mailSettings()->fromName;

        $emailLanguageOptions = [
            EmailRecord::LOCALE_ORDER_LANGUAGE => Craft::t('commerce', 'The language the order was made in.'),
        ];

        $emailLanguageOptions = array_merge($emailLanguageOptions, LocaleHelper::getSiteAndOtherLanguages());

        $emailRenderSiteOptions = [
            null => Craft::t('commerce', 'The site the order was made in.'),
            ['optgroup' => Craft::t('commerce', 'Sites')],
        ] + collect(Craft::$app->getSites()->getAllSites())->mapWithKeys(fn(Site $site) => [$site->id => $site->name])->all();

        return $this->asCpScreen()
            ->title($title)
            ->crumbs([
                ['label' => Craft::t('commerce', 'Commerce'), 'url' => 'commerce'],
                ['label' => Craft::t('app', 'Settings'), 'url' => 'commerce/settings', 'ariaLabel' => Craft::t('commerce', 'Commerce Settings')],
                ['label' => Craft::t('commerce', 'Emails'), 'url' => 'commerce/settings/emails'],
            ])
            ->selectedSubnavItem('settings')
            ->action('commerce/emails/save')
            ->redirectUrl('commerce/settings/emails')
            ->contentTemplate('commerce/settings/emails/_edit', [
                'email' => $email,
                'pdfList' => $pdfList,
                'senderAddressPlaceholder' => $senderAddressPlaceholder,
                'senderNamePlaceholder' => $senderNamePlaceholder,
                'emailLanguageOptions' => $emailLanguageOptions,
                'emailRenderSiteOptions' => $emailRenderSiteOptions,
                'readOnly' => $this->isReadOnlyScreen(),
            ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $emailsService = Plugin::getInstance()->getEmails();
        $emailId = $this->request->getBodyParam('emailId');
        $storeId = $this->request->getBodyParam('storeId');

        if (!$storeId) {
            throw new BadRequestHttpException("Invalid store ID: $storeId");
        }

        if ($emailId) {
            $email = $emailsService->getEmailById($emailId, $storeId);
            if (!$email) {
                throw new BadRequestHttpException("Invalid email ID: $emailId");
            }
        } else {
            $email = new Email();
        }

        $renderSiteId = $this->request->getBodyParam('renderSiteId');

        // Shared attributes
        $email->storeId = $storeId;
        $email->name = $this->request->getBodyParam('name');
        $email->subject = $this->request->getBodyParam('subject');
        $email->recipientType = $this->request->getBodyParam('recipientType');
        $email->to = $this->request->getBodyParam('to');
        $email->bcc = $this->request->getBodyParam('bcc');
        $email->cc = $this->request->getBodyParam('cc');
        $email->replyTo = $this->request->getBodyParam('replyTo');
        $email->enabled = (bool)$this->request->getBodyParam('enabled');
        $email->templatePath = $this->request->getBodyParam('templatePath');
        $email->plainTextTemplatePath = $this->request->getBodyParam('plainTextTemplatePath');
        $pdfId = $this->request->getBodyParam('pdfId');
        $email->pdfId = $pdfId ?: null;
        $email->language = $this->request->getBodyParam('language');
        $email->renderSiteId = $renderSiteId ? (int)$renderSiteId : null;
        $email->setSenderAddress($this->request->getBodyParam('senderAddress'));
        $email->setSenderName($this->request->getBodyParam('senderName'));

        // Save it
        if ($emailsService->saveEmail($email)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Email saved.'));
            return $this->redirectToPostedUrl($email);
        }

        $this->setFailFlash(Craft::t('commerce', 'Couldn’t save email.'));
        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['email' => $email]);

        return null;
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');
        if (!$id) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t delete email.'));
        }

        if (!Plugin::getInstance()->getEmails()->deleteEmailById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t delete email.'));
        }

        return $this->asSuccess();
    }
}
