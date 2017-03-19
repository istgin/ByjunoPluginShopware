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

class ByjunoPayments extends Plugin
{

    private function getPaymentId(\sOrder $sOrder)
    {
        if (!empty($sOrder->sUserData['additional']['payment']['id'])) {
            return $sOrder->sUserData['additional']['payment']['id'];
        }
        return $sOrder->sUserData['additional']['user']['paymentID'];
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
            'Shopware_Modules_Order_SendMail_Send' => 'sendOrderConfirmationEmail'
        ];
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
                //TODO: Email from config
                $mail->addTo("igor.sutugin@gmail.com");
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
            $this->getPath() . '/Resources/views/'
        );

        return $this->getPath() . '/Controllers/Frontend/PaymentInstallment.php';
    }

    public function registerControllerInvoice(\Enlight_Event_EventArgs $args)
    {
        $this->container->get('Template')->addTemplateDir(
            $this->getPath() . '/Resources/views/'
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

/*

        $parent = $this->Menu()->findOneBy(array('id' => 65));
        $item   = $this->createMenuItem(array(
            'label'  => 'Byjuno',
            'class'  => 'byjunoicon',
            'active' => 1,
            'parent' => $parent,
        ));
        $parent = $item;
        $this->createMenuItem(array(
            'label'      => 'Byjuno Log',
            'controller' => 'ByjunoTransactions',
            'action'     => 'Index',
            'class'      => 'sprite-cards-stack',
            'active'     => 1,
            'parent'     => $parent,
        ));
*/
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
                '<img src="http://your-image-url"/>'
                . '<div id="payment_desc">'
                . '  Pay with byjuno invoice.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        $options = [
            'name' => 'byjuno_payment_installment',
            'description' => 'Byjuno installment',
            'action' => 'PaymentInstallment',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="http://your-image-url"/>'
                . '<div id="payment_desc">'
                . '  Pay with byjuno installment.'
                . '</div>'
        ];

        $installer->createOrUpdate($context->getPlugin(), $options);
        parent::install($context);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        try {
            $this->removeSchema();
        } catch(\Exception $e) {

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
