<?php

namespace ByjunoPayments;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Models\Payment\Payment;

class ByjunoPayments extends Plugin
{
    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

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
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
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
