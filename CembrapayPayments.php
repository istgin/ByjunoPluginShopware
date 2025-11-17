<?php


namespace CembrapayPayments;

use Cembrapay\CembrapayPayments\Api\CembraPayCommunicator;
use Cembrapay\CembrapayPayments\Api\CembraPayConstants;
use Cembrapay\CembrapayPayments\Api\CembraPayLoginDto;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use CembrapayPayments\Models\CembrapayTransactions;
use CembrapayPayments\Models\CembrapayDocuments;
use Shopware\Models\Payment\Payment;
use Doctrine\ORM\Tools\SchemaTool;

require (__DIR__) . '/bcdp/cembrapay.php';
require (__DIR__) . '/bcdp/bcdphelper.php';

class CembrapayPayments extends Plugin
{

    public static $orderNumberGenerated = "";
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

        $file = $this->getPath() . '/Snippets/frontend/cembrapay/index.ini';
        $parsed = parse_ini_file($file, true);
        $date = new \DateTime();

        foreach ($shops as $localeId => $shopId)
        {
            foreach ($parsed as $sectionKey => $sectionValue) {
                foreach ($sectionValue as $key => $val) {

                    $locale = array_shift($repository->findBy(array('locale' => $sectionKey)));
                    $arr = Array(
                        'frontend/cembrapay/index',
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
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_PaymentInvoice' => 'cembra_registerControllerInvoice',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_CembrapayTransactions' => 'cembra_registerControllerTransactions',
            'Enlight_Controller_Action_PostDispatch' => 'cembra_onPostDispatchCembrapayMessage',
            'Enlight_Controller_Action_PreDispatch' => 'cembra_onPreDispatchCembrapayMessage',
            'Shopware_Modules_Admin_GetPaymentMeans_DataFilter' => 'cembra_CdpStatusCall',
            'Shopware_Modules_Order_GetOrdernumber_FilterOrdernumber' => 'cembra_onFilterOrdernumber'
        ];
    }

    public static $controller = "";
    public static $action = "";
    public static $method = "";

    public function cembra_onFilterOrdernumber(\Enlight_Event_EventArgs $args)
    {
        if (!empty(self::$orderNumberGenerated)) {
            return self::$orderNumberGenerated;
        } else {
            return $args->getReturn();
        }
    }

    function cembra_onPreDispatchCembrapayMessage(\Enlight_Event_EventArgs $args) {
        /* @var $request \Enlight_Controller_Request_RequestHttp */;
        $request = $args->getRequest();
        self::$controller = $request->getControllerName();
        self::$action = $request->getActionName();
        self::$method = $request->getMethod();
    }

    function cembra_onPostDispatchCembrapayMessage(\Enlight_Event_EventArgs $args) {

        self::$controller = $args->getRequest()->getControllerName();
        self::$action = $args->getRequest()->getActionName();
        self::$method = $args->getRequest()->getMethod();
        if (!empty($_SESSION["cembrapay"]["message"])) {
            if ($args->getSubject()->View()->hasTemplate()){
                $args->getSubject()->View()->assign("sBasketInfo", $_SESSION["cembrapay"]["message"]);
            }
            $_SESSION["cembrapay"]["message"] = null;
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
            && !strstr($args->getRequest()->getControllerName(), "PaymentInvoice")) {
            $view->messageCembrapay = "";
            if (!empty($_SESSION["cembrapay"]["paymentMessage"])) {
                $view->messageCembrapay = $_SESSION["cembrapay"]["paymentMessage"];
                unset($_SESSION["cembrapay"]["paymentMessage"]);
            }
            $this->container->get('Template')->addTemplateDir(
                $this->getPath() . '/Views/'
            );
            $view->extendsTemplate('frontend/cembrapay_message.tpl');
        }

        $tmxorgid = "lq866c5i";
        if (!isset($_SESSION["cembrapay_tmx"])) {
            $_SESSION["cembrapay_tmx"] = session_id();
            $view->tmx_enable = true;
            $view->tmx_orgid = $tmxorgid;
            $view->tmx_session = $_SESSION["cembrapay_tmx"];
            $this->container->get('Template')->addTemplateDir(
                $this->getPath() . '/Views/'
            );
            $view->extendsTemplate('frontend/cembrapay_tmx.tpl');
        }
    }


    public function cembra_registerControllerTransactions(\Enlight_Event_EventArgs $args)
    {
        $this->container->get('Template')->addTemplateDir(
            $this->getPath() . '/Resources/views/'
        );

        return $this->getPath() . '/Controllers/Backend/CembrapayTransactions.php';
    }

    public function cembra_registerControllerInvoice(\Enlight_Event_EventArgs $args)
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
            $this->container->get('models')->getClassMetadata(CembrapayTransactions::class),
            $this->container->get('models')->getClassMetadata(CembrapayDocuments::class)
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
            $this->container->get('models')->getClassMetadata(CembrapayTransactions::class),
            $this->container->get('models')->getClassMetadata(CembrapayDocuments::class)
        ];

        try {
            $tool->createSchema($classes);
        } catch (\Exception $e) {

        }
        $sql = "ALTER TABLE `s_plugin_cembrapay_transactions`
CHANGE COLUMN `xml_request` `xml_request` TEXT CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL ,
CHANGE COLUMN `xml_responce` `xml_responce` TEXT CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL";
        Shopware()->Db()->exec($sql);


        $options = [
            'name' => 'cembrapay_payment_invoice',
            'description' => 'CembraPay invoice',
            'action' => 'PaymentInvoice',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="https://cembrapay.ch/logo/jpg/660x390/CembraPay_Checkout_RGB_660x390.jpg" style="height:50px" />'
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
    private function cembra_setActiveFlag($payments, $active)
    {
        $em = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $em->flush();
    }

    public static $sesStatusString = '';
    public function cembra_CdpStatusCall(\Enlight_Event_EventArgs $args)
    {
        $cdp_enabled = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_cdpenable");
        $user = $this->getUser();
        $methods = $args->getReturn();
        if (self::$controller != "checkout" ||
            (self::$action != "shippingPayment" && self::$action != "saveShippingPayment")) {
            return $methods;
        }

        $needToCheck = false;
        foreach($methods as $m) {
            if ($m["name"] == 'cembrapay_payment_invoice') {
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
        $min = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_minimum");
        $max = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_maximum");
        $basket = Shopware()->Modules()->Basket()->sGetAmount();
        if ($basket == null || $min > $basket['totalAmount'] || $max < $basket['totalAmount']) {
            $return = Array();
            foreach($methods as $m) {
                if (($m["name"] == 'cembrapay_payment_invoice')) {
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
                if (($m["name"] == 'cembrapay_payment_invoice') && !$allowed) {
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

}
