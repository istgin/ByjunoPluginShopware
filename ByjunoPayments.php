<?php


namespace ByjunoPayments;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use ByjunoPayments\Models\ByjunoTransactions;
use ByjunoPayments\Models\ByjunoDocuments;
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
            'Shopware_Components_Document_Render_FilterHtml' => 'documentGenerated_backend',
            'Shopware\Models\Order\Order::preUpdate' => 'documentPreGenerated_order',
            'Shopware\Models\Order\Order::postUpdate' => 'documentGenerated_order',
            'Enlight_Controller_Action_PostDispatch_Backend' => 'documentGenerated',
            'Enlight_Controller_Action_PostDispatch' => 'onPostDispatchByjunoMessage',
            'Enlight_Controller_Action_PreDispatch' => 'onPreDispatchByjunoMessage',
            'Shopware_Modules_Admin_GetPaymentMeans_DataFilter' => 'Byjuno_CdpStatusCall',
            'Shopware_CronJob_ByjunoPaymentCron' => 'ByjunoPaymentCron'
        ];
    }
    function documentGenerated_backend(\Enlight_Event_EventArgs $args) {
        $S4_confirmation_trigger = Shopware()->Config()->getByNamespace("ByjunoPayments", "S4_confirmation_trigger");
        $S4_trigger_order = "Invoice-Document";
        if (isset($S4_confirmation_trigger) && $S4_confirmation_trigger == "Orderstatus") {
            $S4_trigger_order = $S4_confirmation_trigger;
        }
        $s4s5 = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S4_S5");
        $s5Rev = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S5_reversal");
        $s4_trigger = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S4_activation");
        if ($s4_trigger == 'Invoice' && ((isset($s4s5) && $s4s5 == 'Enabled') || (isset($s5Rev) && $s5Rev == 'Enabled'))) {
            /* @var $doc \Shopware_Components_Document */
            $doc = $args->get("subject");
            $reflection = new \ReflectionClass($doc);
            $property_order = $reflection->getProperty("_order");
            $property_typID = $reflection->getProperty("_typID");
            $property_config = $reflection->getProperty("_config");
            $property_order->setAccessible(true);
            $property_typID->setAccessible(true);
            $property_config->setAccessible(true);
            $_order = $property_order->getValue($doc);
            $_typID = $property_typID->getValue($doc);
            $_config = $property_config->getValue($doc);
            $orderId = $_order->order->id;
            $documentType = $_typID;
            if (!empty($orderId) && !empty($documentType)) {
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


                $rowPayment = Shopware()->Db()->fetchRow("
                        SELECT *
                        FROM s_core_paymentmeans
                        WHERE ID = ?
					",
                    array($rowOrder["paymentID"])
                );
                if (empty($rowPayment["name"]) ||
                    ($rowPayment["name"] != 'byjuno_payment_installment' && $rowPayment["name"] != 'byjuno_payment_invoice')
                ) {
                    return;
                }

                if (!empty($row) && !empty($rowOrder) && $documentType == 1 && $s4s5 == 'Enabled') {
                    if ($S4_trigger_order == "Orderstatus") {
                        return;
                    }
                     Byjuno_CreateShopRequestS4_DB($row["docID"], $row["amount"], $rowOrder["invoice_amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], $row["date"]);
                } else if (!empty($row) && !empty($rowOrder) && $documentType == 3 && $s4s5 == 'Enabled') {
                    Byjuno_CreateShopRequestS5Refund_DB($_config["bid"], $row["amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], $row["date"]);
                } else if (!empty($row) && !empty($rowOrder) && $documentType == 4 && $s5Rev == 'Enabled' ) {
                    if ($row["amount"] < 0) {
                        $row["amount"] = $row["amount"] * (-1);
                    }
                    Byjuno_CreateShopRequestS5Refund_DB($_config["bid"], $row["amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], $row["date"]);
                } else {
                    return;
                }
            }
        } else {
            return;
        }
    }
    private static $previousStatus = 0;
    function documentPreGenerated_order(\Enlight_Event_EventArgs $args) {
        $order = $args->getEntity();
        if (!($order instanceof \Shopware\Models\Order\Order)) {
            return;
        }
        $s4s5 = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S4_S5");
        if (isset($s4s5) && $s4s5 == 'Enabled') {
            $orderId = $order->getId();
            $rowOrder = Shopware()->Db()->fetchRow("
            SELECT *
            FROM s_order
            WHERE ID = ?
            ",
                array($orderId)
            );
            self::$previousStatus = $rowOrder["status"];
        }
    }

    function documentGenerated_order(\Enlight_Event_EventArgs $args) {

        /* @var $order \Shopware\Models\Order\Order */
        $order = $args->getEntity();
        if (!($order instanceof \Shopware\Models\Order\Order)) {
            return;
        }
        $S4_confirmation_trigger = Shopware()->Config()->getByNamespace("ByjunoPayments", "S4_confirmation_trigger");
        $S4_trigger_order = "Invoice-Document";
        if (isset($S4_confirmation_trigger) && $S4_confirmation_trigger == "Orderstatus") {
            $S4_trigger_order = $S4_confirmation_trigger;
        }
        $s4s5 = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S4_S5");
        if (isset($s4s5) && $s4s5 == 'Enabled') {
            $orderId = $order->getId();
            $rowOrder = Shopware()->Db()->fetchRow("
            SELECT *
            FROM s_order
            WHERE ID = ?
            ",
                array($orderId)
            );
            $S5_default_cancel_id = Shopware()->Config()->getByNamespace("ByjunoPayments", "S5_default_cancel_id");
            $cancelId = intval($S5_default_cancel_id);
            if ($cancelId == 0) {
                $cancelId = 4;
            }

            $rowPayment = Shopware()->Db()->fetchRow("
            SELECT *
            FROM s_core_paymentmeans
            WHERE ID = ?
            ",
                array($rowOrder["paymentID"])
            );
            if (empty($rowPayment["name"]) ||
                ($rowPayment["name"] != 'byjuno_payment_installment' && $rowPayment["name"] != 'byjuno_payment_invoice')) {
                return;

            }
            $S4_confirmation_order_id = Shopware()->Config()->getByNamespace("ByjunoPayments", "S4_confirmation_order_id");
            if (!empty($rowOrder) && $rowOrder["status"] == $cancelId && $rowOrder["status"] != self::$previousStatus)
            {
                Byjuno_CreateShopRequestS5Cancel_DB($rowOrder["invoice_amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], date("Y-m-d"));
            }
            else if (!empty($rowOrder) && $S4_trigger_order == "Orderstatus" && isset($S4_confirmation_order_id) && $rowOrder["status"] == $S4_confirmation_order_id && $rowOrder["status"] != self::$previousStatus)
            {
                Byjuno_CreateShopRequestS4_DB($rowOrder["ordernumber"], $rowOrder["invoice_amount"], $rowOrder["invoice_amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], date("Y-m-d"));
            }
            return;
        }

    }

    function documentGenerated(\Enlight_Event_EventArgs $args) {
        $S4_confirmation_trigger = Shopware()->Config()->getByNamespace("ByjunoPayments", "S4_confirmation_trigger");
        $S4_trigger_order = "Invoice-Document";
        if (isset($S4_confirmation_trigger) && $S4_confirmation_trigger == "Orderstatus") {
            $S4_trigger_order = $S4_confirmation_trigger;
        }

        $s4_trigger = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S4_activation");
        if ($s4_trigger == 'Button' &&
            $args->getRequest()->getActionName() == "createDocument"
            && $args->getRequest()->getControllerName() == "Order") {
            $s4s5 = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S4_S5");
            $s5Rev = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_S5_reversal");
            if ((isset($s4s5) && $s4s5 == 'Enabled') || (isset($s5Rev) && $s5Rev == 'Enabled')) {
                $orderId = $args->getSubject()->Request()->getParam('orderId', null);
                $documentType = $args->getSubject()->Request()->getParam('documentType', null);
                $preview = $args->getSubject()->Request()->getParam('preview', null);
                if (!empty($orderId) && !empty($documentType) && !isset($preview)) {
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
                    $rowPayment = Shopware()->Db()->fetchRow("
                        SELECT *
                        FROM s_core_paymentmeans
                        WHERE ID = ?
					",
                        array($rowOrder["paymentID"])
                    );
                    if (empty($rowPayment["name"]) ||
                        ($rowPayment["name"] != 'byjuno_payment_installment' && $rowPayment["name"] != 'byjuno_payment_invoice')
                    ) {
                        return;
                    }

                    if ($S4_trigger_order == "Orderstatus") {
                        return;
                    }
                    if (!empty($row) && !empty($rowOrder) && $documentType == 1 && $s4s5 == 'Enabled') {
                        Byjuno_CreateShopRequestS4_DB($row["docID"], $row["amount"], $rowOrder["invoice_amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], $row["date"]);
                    } else if (!empty($row) && !empty($rowOrder) && $documentType == 3 && $s4s5 == 'Enabled') {
                        $invoiceNumber = $args->getSubject()->Request()->getParam('invoiceNumber', null);
                        Byjuno_CreateShopRequestS5Refund_DB($invoiceNumber, $row["amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], $row["date"]);
                    } else if (!empty($row) && !empty($rowOrder) && $documentType == 4 && $s5Rev == 'Enabled' ) {
                        if ($row["amount"] < 0) {
                            $row["amount"] = $row["amount"] * (-1);
                        }
                        $invoiceNumber = $args->getSubject()->Request()->getParam('invoiceNumber', null);
                        Byjuno_CreateShopRequestS5Refund_DB($invoiceNumber, $row["amount"], $rowOrder["currency"], $rowOrder["ordernumber"], $rowOrder["userID"], $row["date"]);
                    } else {
                        return;
                    }
                }
            } else {
                return;
            }
        }
    }

    public static $controller = "";
    public static $action = "";
    public static $method = "";
    function onPreDispatchByjunoMessage(\Enlight_Event_EventArgs $args) {
        /* @var $request \Enlight_Controller_Request_RequestHttp */;
        $request = $args->getRequest();
        self::$controller = $request->getControllerName();
        self::$action = $request->getActionName();
        self::$method = $request->getMethod();
    }

    function onPostDispatchByjunoMessage(\Enlight_Event_EventArgs $args) {

        self::$controller = $args->getRequest()->getControllerName();
        self::$action = $args->getRequest()->getActionName();
        self::$method = $args->getRequest()->getMethod();
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
            $this->container->get('models')->getClassMetadata(ByjunoTransactions::class),
            $this->container->get('models')->getClassMetadata(ByjunoDocuments::class)
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
            $this->container->get('models')->getClassMetadata(ByjunoTransactions::class),
            $this->container->get('models')->getClassMetadata(ByjunoDocuments::class)
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


        $this->addCron();

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
        $this->removeCron();
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

    protected function isStatusOkCDP($status) {
        try {
            $accepted_CDP = Shopware()->Config()->getByNamespace("ByjunoPayments", "allowed_cdp");
            $ijStatus = Array();
            if (!empty(trim($accepted_CDP))) {
                $ijStatus = explode(",", trim($accepted_CDP));
                foreach($ijStatus as $key => $val) {
                    $ijStatus[$key] = intval($val);
                }
            }
            if (!empty($accepted_CDP) && count($ijStatus) > 0 && in_array($status, $ijStatus)) {
                return true;
            }
            return false;

        } catch (\Exception $e) {
            return false;
        }
    }

    protected function CDPRequest()
    {
        $statusCDP = 0;
        $mode = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_mode");
        $b2b = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_b2b");		
        $timeout = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_timeout");
        $user = $this->getUser();
        $billing = $user['billingaddress'];
        $shipping = $user['shippingaddress'];
        $basket = Shopware()->Modules()->Basket()->sGetAmount();
        $request = Byjuno_CreateShopWareShopRequestUserBillingCDP($user, $billing, $shipping, $basket['totalAmount'], "", "", "", "", "",  "NO");
        $statusLog = "CDP request";
        if ($request->getCompanyName1() != '' && $b2b == 'Enabled') {
            $statusLog = "CDP request for company";
            $xml = $request->createRequestCompany();
        } else {
            $xml = $request->createRequest();
        }
        $byjunoCommunicator = new \ByjunoCommunicator();
        if (isset($mode) && $mode == 'Live') {
            $byjunoCommunicator->setServer('live');
        } else {
            $byjunoCommunicator->setServer('test');
        }
        $response = $byjunoCommunicator->sendRequest($xml, $timeout);
        if (isset($response)) {
            $byjunoResponse = new \ByjunoResponse();
            $byjunoResponse->setRawResponse($response);
            $byjunoResponse->processResponse();
            $statusCDP = (int)$byjunoResponse->getCustomerRequestStatus();
            $this->saveLog($request, $xml, $response, $statusCDP, $statusLog);
            if (intval($statusCDP) > 15) {
                $statusCDP = 0;
            }
        }
        return $this->isStatusOkCDP($statusCDP);
    }

    public function SaveLog(\ByjunoRequest $request, $xml_request, $xml_response, $status, $type) {
        $sql     = '
            INSERT INTO s_plugin_byjuno_transactions (requestid, requesttype, firstname, lastname, ip, status, datecolumn, xml_request, xml_responce)
                    VALUES (?,?,?,?,?,?,?,?,?)
        ';
        Shopware()->Db()->query($sql, Array(
            $request->getRequestId(),
            $type,
            $request->getFirstName(),
            $request->getLastName(),
            $_SERVER['REMOTE_ADDR'],
            (($status != 0) ? $status : 'Error'),
            date('Y-m-d\TH:i:sP'),
            $xml_request,
            $xml_response
        ));
    }

    public static $sesStatusString = '';
    public function Byjuno_CdpStatusCall(\Enlight_Event_EventArgs $args)
    {
        $cdp_enabled = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_cdpenable");
        $user = $this->getUser();
        $methods = $args->getReturn();
        if (self::$controller != "checkout" ||
            (self::$action != "shippingPayment" && self::$action != "saveShippingPayment")) {
            return $methods;
        }

        $needToCheck = false;
        foreach($methods as $m) {
            if ($m["name"] == 'byjuno_payment_invoice' || $m["name"] == 'byjuno_payment_installment') {
                $needToCheck = true;
                break;
            }
        }
        if (!$needToCheck) {
            return $methods;
        }

        if (empty($user) || empty($user['billingaddress']) || empty($user['shippingaddress'])) {
            return $methods;
        }
        $min = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_minimum");
        $max = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_maximum");
        $basket = Shopware()->Modules()->Basket()->sGetAmount();
        if ($basket == null || $min > $basket['totalAmount'] || $max < $basket['totalAmount']) {
            return $methods;
        }
        if ($cdp_enabled == 'Enabled') {
            if (!empty(self::$sesStatusString)) {
                if (self::$sesStatusString == 'true') {
                    $sesStatus = true;
                } else {
                    $sesStatus = false;
                }
            }
            if (!isset($sesStatus)) {
                $allowed = $this->CDPRequest();
                $converted_res = $allowed ? 'true' : 'false';
                self::$sesStatusString = $converted_res;
            } else {
                $allowed = $sesStatus;
            }
            $return = Array();
            foreach($methods as $m) {
                if (($m["name"] == 'byjuno_payment_invoice' || $m["name"] == 'byjuno_payment_installment') && !$allowed) {
                    continue;
                }
                $return[] = $m;
            }
            return $return;
        }
        return $methods;
    }

    public function getUser()
    {
        try {
            $userData = Shopware()->Modules()->Admin()->sGetUserData();
            if (!empty($userData)) {
                return $userData;
            } else {
                return null;
            }
        } catch(\Exception $e) {
            return null;
        }
    }

    public function addCron()
    {
        $connection = $this->container->get('dbal_connection');
        $connection->insert(
            's_crontab',
            [
                'name'             => 'ByjunoPayment',
                'action'           => 'ByjunoPaymentCron',
                'next'             => new \DateTime(),
                'start'            => null,
                '`interval`'       => '30',
                'active'           => true,
                'end'              => null,
                'pluginID'         => null
            ],
            [
                'next' => 'datetime',
                'end'  => 'datetime',
            ]
        );
    }

    public function removeCron()
    {
        $this->container->get('dbal_connection')->executeQuery('DELETE FROM s_crontab WHERE `name` = ?', [
            'ByjunoPayment'
        ]);
    }

    public function ByjunoPaymentCron(\Shopware_Components_Cron_CronJob $job)
    {
        $time = time() - 30 * 60;
        $documents = Shopware()->Db()->fetchAll("
                        SELECT *
                        FROM s_plugin_byjuno_documents
                        WHERE document_sent = false AND document_try_time < ?
                        ORDER BY id DESC
                        ",
            array($time)
        );
        if (count($documents) == 0) {
            return;
        }
        foreach ($documents as $document) {
            $statusLog = "";
            if ($document["document_type"] == 1) {
                $request = Byjuno_CreateShopRequestS4($document["document_id"], $document["amount"], $document["order_amount"], $document["order_currency"], $document["order_id"], $document["customer_id"], $document["date"]);
                $statusLog = "S4 Request";
            } else if ($document["document_type"] == 2) {
                $request = Byjuno_CreateShopRequestS5Refund($document["document_id"], $document["amount"], $document["order_currency"], $document["order_id"], $document["customer_id"], $document["date"]);
                $statusLog = "S5 refund request";
            } else if ($document["document_type"] == 3) {
                $request = Byjuno_CreateShopRequestS5Cancel($document["amount"], $document["order_currency"], $document["order_id"], $document["customer_id"], $document["date"]);
                $statusLog = "S5 cancel request";
            }

            $xml = $request->createRequest();
            $byjunoCommunicator = new \ByjunoCommunicator();
            $mode = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_mode");
            $timeout = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_timeout");
            if (isset($mode) && $mode == 'Live') {
                $byjunoCommunicator->setServer('live');
            } else {
                $byjunoCommunicator->setServer('test');
            }
            $response = $byjunoCommunicator->sendS4Request($xml, $timeout);
            if (!empty($response)) {
                $byjunoResponse = new \ByjunoS4Response();
                $byjunoResponse->setRawResponse($response);
                $byjunoResponse->processResponse();
                $statusCDP = $byjunoResponse->getProcessingInfoClassification();
                if ($document["document_type"] == 1) {
                    Byjuno_SaveS4LogCron($request, $xml, $response, $statusCDP, $statusLog, "-", "-");
                } else if ($document["document_type"] == 2) {
                    Byjuno_SaveS5LogCron($request, $xml, $response, $statusCDP, $statusLog, "-", "-");
                } else if ($document["document_type"] == 3) {
                    Byjuno_saveS5LogCron($request, $xml, $response, $statusCDP, $statusLog, "-", "-");
                }
                $sql = 'UPDATE `s_plugin_byjuno_documents` SET `document_sent`= true WHERE id = ?';
                Shopware()->Db()->query($sql, array($document["id"]));
            } else {
                Byjuno_saveS5LogCron($request, $xml, "", 0, $statusLog, "-", "-");
                $sql = 'UPDATE `s_plugin_byjuno_documents` SET `document_try_time`= true WHERE id = ?';
                Shopware()->Db()->query($sql, array(time(), $document["id"]));
            }
        }
        return true;
    }

}
