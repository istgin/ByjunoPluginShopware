<?php


namespace ByjunoPayments;

use Byjuno\ByjunoPayments\Api\CembraPayCommunicator;
use Byjuno\ByjunoPayments\Api\CembraPayConstants;
use Byjuno\ByjunoPayments\Api\CembraPayLoginDto;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use ByjunoPayments\Models\ByjunoTransactions;
use ByjunoPayments\Models\ByjunoDocuments;
use Shopware\Models\Payment\Payment;
use Doctrine\ORM\Tools\SchemaTool;

require (__DIR__) . '/bcdp/cembrapay.php';
require (__DIR__) . '/bcdp/bcdphelper.php';

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
            'Enlight_Controller_Action_PostDispatch' => 'onPostDispatchByjunoMessage',
            'Enlight_Controller_Action_PreDispatch' => 'onPreDispatchByjunoMessage',
            'Shopware_Modules_Admin_GetPaymentMeans_DataFilter' => 'Byjuno_CdpStatusCall'
        ];
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
            'description' => 'CembraPay invoice',
            'action' => 'PaymentInvoice',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="https://byjuno.ch/Content/logo/de/6639/BJ_Rechnung_BLK.gif" />'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        $options = [
            'name' => 'byjuno_payment_installment',
            'description' => 'CembraPay installment',
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

    public function SaveLog($requestId, $firstname, $lastname, $xml_request, $xml_response, $status, $type) {
        $sql     = '
            INSERT INTO s_plugin_byjuno_transactions (requestid, requesttype, firstname, lastname, ip, status, datecolumn, xml_request, xml_responce)
                    VALUES (?,?,?,?,?,?,?,?,?)
        ';
        Shopware()->Db()->query($sql, Array(
            $requestId,
            $type,
            $firstname,
            $lastname,
            $_SERVER['REMOTE_ADDR'],
            $status,
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
            $return = Array();
            foreach($methods as $m) {
                if (($m["name"] == 'byjuno_payment_invoice' || $m["name"] == 'byjuno_payment_installment')) {
                    continue;
                }
                $return[] = $m;
            }
            return $return;
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
                $allowed = Cembrapay_ScreeningRequest($this->getUser());
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

    public function removeCron()
    {
        $this->container->get('dbal_connection')->executeQuery('DELETE FROM s_crontab WHERE `name` = ?', [
            'ByjunoPayment'
        ]);
    }

}
