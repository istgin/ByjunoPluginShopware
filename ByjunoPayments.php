<?php


namespace ByjunoPayments;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use ByjunoPayments\Models\ByjunoTransactions;
use Shopware\Models\Payment\Payment;
use Doctrine\ORM\Tools\SchemaTool;

require (__DIR__) . '/api/byjuno.php';
require (__DIR__) . '/api/helper.php';

class ByjunoPayments extends Plugin
{

    private function getPaymentId(\sOrder $sOrder)
    {
        if (!empty($sOrder->sUserData['additional']['payment']['id'])) {
            return $sOrder->sUserData['additional']['payment']['id'];
        }
        return $sOrder->sUserData['additional']['user']['paymentID'];
    }

    private function getShopLocaleMapping()
    {
        $connection = Shopware()->Container()->get('dbal_connection');
        $query = $connection->createQueryBuilder();
        $query->select(['locale_id, IFNULL(main_id, id)']);
        $query->from('s_core_shops');
        $query->where('s_core_shops.default = 1');
        $query->setMaxResults(1);
        return $query->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    private function snippetInstalationToDB()
    {

        $shops = $this->getShopLocaleMapping();
        $sql = '
            INSERT IGNORE INTO s_core_snippets (namespace, shopID, localeID, name, created, `value`) VALUES (?, ?, ?, ?, ?, ?)
        ';

        $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Locale');

        $file = $this->getPath() . '/Snippets/frontend/byjuno/index.ini';
        $parsed = parse_ini_file($file, true);
        $date = new \DateTime();

        foreach ($shops as $localeId => $shopId)
        {
            foreach ($parsed as $sectionKey => $sectionValue) {
                foreach ($sectionValue as $key => $val) {

                    $locale = array_shift($repository->findBy(array('locale' => $sectionKey)));
                    $arr = Array(
                        'frontend/byjuno/index',
                        $shopId,
                        $locale->getId(),
                        trim($key),
                        $date->format('Y-m-d H:i:s'),
                        trim($val)
                    );
                    Shopware()->Db()->query($sql, $arr);
                }
            }
        }
    }

    public function registerMySnippets()
    {
        $this->container->get('Snippets')->addConfigDir(
            $this->getPath() . '/Snippets/'
        );
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_PaymentInvoice' => 'registerControllerInvoice',
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_PaymentInstallment' => 'registerControllerInstallment',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_ByjunoTransactions' => 'registerControllerTransactions',
            'Enlight_Controller_Action_PostDispatch_Backend' => 'documentGenerated',
            'Shopware_Modules_Order_SendMail_Send' => 'sendOrderConfirmationEmail',
            'Enlight_Controller_Action_PostDispatch' => 'onPostDispatchByjunoMessage'
        ];
    }
    function documentGenerated(\Enlight_Event_EventArgs $args) {

        if ($args->getRequest()->getActionName() == "createDocument"
            && $args->getRequest()->getControllerName() == "Order") {

            $s4s5 = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S4_S5");
            if (!isset($s4s5) || $s4s5 != 'Enabled') {
                return;
            }
            $orderId = $args->getSubject()->Request()->getParam('orderId', null);
            $documentType = $args->getSubject()->Request()->getParam('documentType', null);
            $preview = $args->getSubject()->Request()->getParam('preview', null);
            if (!empty($orderId) && !empty($documentType) && !isset($preview))
            {
                $row = Shopware()->Db()->fetchRow("
                SELECT *
                FROM s_order_documents
                WHERE orderID = ? AND type = ?
                ORDER BY ID DESC
                ",
                    array($orderId, $documentType)
                );

                $rowOrder = Shopware()->Db()->fetchRow("
                SELECT *
                FROM s_order
                WHERE ID = ?
                ",
                    array($orderId)
                );
                $statusLog = "";
                if (!empty($row) && !empty($rowOrder) && $documentType == 1) {
                    $request = CreateShopRequestS4($row["docID"], $row["amount"], $rowOrder["invoice_amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], $row["date"]);
                    $statusLog = "S4 Request";
                } else if (!empty($row) && !empty($rowOrder) && $documentType == 3) {
                    $request = CreateShopRequestS5($row["docID"], $row["amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], $row["date"]);
                    $statusLog = "S5 Request";
                } else {
                    return;
                }
                $xml = $request->createRequest();
                $byjunoCommunicator = new \ByjunoCommunicator();
                $mode = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_mode");
                if (isset($mode) && $mode == 'Live') {
                    $byjunoCommunicator->setServer('live');
                } else {
                    $byjunoCommunicator->setServer('test');
                }
                $response = $byjunoCommunicator->sendS4Request($xml);
                if (isset($response)) {
                    $byjunoResponse = new \ByjunoS4Response(); 
                    $byjunoResponse->setRawResponse($response);
                    $byjunoResponse->processResponse();
                    $statusCDP = $byjunoResponse->getProcessingInfoClassification();
                    if ($statusLog == "S4 Request") {
                        saveS4Log($request, $xml, $response, $statusCDP, $statusLog, "-", "-");
                    } else if ($statusLog == "S5 Request") {
                        saveS5Log($request, $xml, $response, $statusCDP, $statusLog, "-", "-");
                    }
                }
            }

        }
    }
    function onPostDispatchByjunoMessage(\Enlight_Event_EventArgs $args) {
        if (!empty($_SESSION["byjuno"]["message"])) {
            if ($args->getSubject()->View()->hasTemplate()){
                $args->getSubject()->View()->assign("sBasketInfo", $_SESSION["byjuno"]["message"]);
            }
            $_SESSION["byjuno"]["message"] = null;
        }

        /* @var $request \Enlight_Controller_Request_RequestHttp */
        $request = $args->getSubject()->Request();
        $response = $args->getSubject()->Response();
        /* @var $view \Enlight_View_Default */
        $view = $args->getSubject()->View();


        if (!$request->isDispatched()
            || $response->isException()
            || $request->getModuleName() != 'frontend'
            || $request->isXmlHttpRequest()
            || !$view->hasTemplate()
        ) {
            return;
        }

        if (!strstr($args->getRequest()->getActionName(), "ajax")
            && !strstr($args->getRequest()->getControllerName(), "PaymentInvoice")
            && !strstr($args->getRequest()->getControllerName(), "PaymentInstallment")) {
            $view->messageByjuno = "";
            if (!empty($_SESSION["byjuno"]["paymentMessage"])) {
                $view->messageByjuno = $_SESSION["byjuno"]["paymentMessage"];
                unset($_SESSION["byjuno"]["paymentMessage"]);
            }
            $this->container->get('Template')->addTemplateDir(
                $this->getPath() . '/Views/'
            );
            $view->extendsTemplate('frontend/byjuno_message.tpl');
        }

        $tmx_enable = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_threatmetrixenable");
        $tmxorgid = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_threatmetrix");

        if (isset($tmx_enable) && $tmx_enable == 'Enabled' && isset($tmxorgid) && $tmxorgid != '' && !isset($_SESSION["byjuno_tmx"])) {
            $_SESSION["byjuno_tmx"] = session_id();
            $view->tmx_enable = $tmx_enable;
            $view->tmx_orgid = $tmxorgid;
            $view->tmx_session = $_SESSION["byjuno_tmx"];
            $this->container->get('Template')->addTemplateDir(
                $this->getPath() . '/Views/'
            );
            $view->extendsTemplate('frontend/byjuno_tmx.tpl');
        }
    }


    public function sendOrderConfirmationEmail(\Enlight_Event_EventArgs $args)
    {
        /* @var $orderProxy \Shopware_Proxies_sOrderProxy */
        /* @var $order \Shopware\Models\Order\Order */
        $orderProxy = $args->get("subject");

        try {
            $paymentData = Shopware()->Modules()->Admin()->sGetPaymentMeanById($this->getPaymentId($orderProxy), Shopware()->Modules()->Admin()->sGetUserData());
            if (!empty($paymentData["name"]) && ($paymentData["name"] == 'byjuno_payment_invoice' || $paymentData["name"] == 'byjuno_payment_installment')) {
                /* @var $mail \Enlight_Components_Mail */
                $mail = $args->get("mail");
                $mail->send();
                $mail->clearRecipients();
                $mail->addTo(Shopware()->Config()->get("ByjunoPayments", "byjuno_email"));
                $mail->send();
                return false;
            }
        } catch (\Exception $e) {

        }
        return true;
    }

    public function registerControllerTransactions(\Enlight_Event_EventArgs $args)
    {
        $this->container->get('Template')->addTemplateDir(
            $this->getPath() . '/Resources/views/'
        );

        return $this->getPath() . '/Controllers/Backend/ByjunoTransactions.php';
    }

    public function registerControllerInstallment(\Enlight_Event_EventArgs $args)
    {
        $this->container->get('Template')->addTemplateDir(
            $this->getPath() . '/Views/'
        );

        return $this->getPath() . '/Controllers/Frontend/PaymentInstallment.php';
    }

    public function registerControllerInvoice(\Enlight_Event_EventArgs $args)
    {
        $this->container->get('Template')->addTemplateDir(
            $this->getPath() . '/Views/'
        );

        return $this->getPath() . '/Controllers/Frontend/PaymentInvoice.php';
    }

    private function removeSchema()
    {
        $tool = new SchemaTool($this->container->get('models'));
        $classes = [
            $this->container->get('models')->getClassMetadata(ByjunoTransactions::class)
        ];
        $tool->dropSchema($classes);
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $tool = new SchemaTool($this->container->get('models'));
        $classes = [
            $this->container->get('models')->getClassMetadata(ByjunoTransactions::class)
        ];

        try {
            $tool->createSchema($classes);
        } catch (\Exception $e) {

        }
        $sql = "ALTER TABLE `s_plugin_byjuno_transactions`
CHANGE COLUMN `xml_request` `xml_request` TEXT CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL ,
CHANGE COLUMN `xml_responce` `xml_responce` TEXT CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL";
        Shopware()->Db()->exec($sql);


        $options = [
            'name' => 'byjuno_payment_invoice',
            'description' => 'Byjuno invoice',
            'action' => 'PaymentInvoice',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="https://byjuno.ch/Content/logo/de/6639/BJ_Rechnung_BLK.gif" />'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        $options = [
            'name' => 'byjuno_payment_installment',
            'description' => 'Byjuno installment',
            'action' => 'PaymentInstallment',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="https://byjuno.ch/Content/logo/de/6639/BJ_Ratenzahlung_BLK.gif "/>'
        ];

        $installer->createOrUpdate($context->getPlugin(), $options);
        $this->snippetInstalationToDB();

        $attributeService = Shopware()->Container()->get('shopware_attribute.crud_service');
        $attributeService->update('s_order_attributes', "payment_plan", "string", []);
        $attributeService->update('s_order_attributes', "payment_send", "string", []);
        $attributeService->update('s_order_attributes', "payment_send_to", "string", []);


        parent::install($context);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        $attributeService = Shopware()->Container()->get('shopware_attribute.crud_service');
        try {
            $this->removeSchema();
            $attributeService->delete('s_order_attributes', "payment_plan");
            $attributeService->delete('s_order_attributes', "payment_send");
            $attributeService->delete('s_order_attributes', "payment_send_to");
        } catch (\Exception $e) {

        }

    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), true);
    }

    /**
     * @param Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        $em = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $em->flush();
    }
}
