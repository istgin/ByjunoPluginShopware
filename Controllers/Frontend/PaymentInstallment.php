<?php

use ByjunoPayments\Components\ByjunoPayment\PaymentResponse;
use ByjunoPayments\Components\ByjunoPayment\InvoicePaymentService;

class Shopware_Controllers_Frontend_PaymentInstallment extends Shopware_Controllers_Frontend_Payment
{
    const PAYMENTSTATUSPAID = 12;

    public function preDispatch()
    {
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['ByjunoPayments'];

        $this->get('template')->addTemplateDir($plugin->getPath() . '/Resources/views/');
    }

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        exit('aaa');
        /**
         * Check if one of the payment methods is selected. Else return to default controller.
         */
        switch ($this->getPaymentShortName()) {
            case 'byjuno_payment_installment':
                return $this->redirect(['action' => 'gateway', 'forceSecure' => true]);
            default:
                return $this->redirect(['controller' => 'checkout']);
        }
    }

    /**
     * Gateway action method.
     *
     * Collects the payment information and transmit it to the payment provider.
     */
    public function gatewayAction()
    {
        $providerUrl = $this->getProviderUrl();
        $this->View()->assign('gatewayUrl', $providerUrl . $this->getUrlParameters());
    }

    /**
     * Direct action method.
     *
     * Collects the payment information and transmits it to the payment provider.
     */
    public function directAction()
    {
        $providerUrl = $this->getProviderUrl();
        $this->redirect($providerUrl . $this->getUrlParameters());
    }

    /**
     * Return action method
     *
     * Reads the transactionResult and represents it for the customer.
     */
    public function returnAction()
    {
        /** @var InvoicePaymentService $service */
        $service = $this->container->get('byjuno_payment.byjuno_payment_service');
        $user = $this->getUser();
        $billing = $user['billingaddress'];
        /** @var PaymentResponse $response */
        $response = $service->createPaymentResponse($this->Request());
        $token = $service->createPaymentToken($this->getAmount(), $billing['customernumber']);

        if (!$service->isValidToken($response, $token)) {
            $this->forward('cancel');

            return;
        }

        switch ($response->status) {
            case 'accepted':
                $this->saveOrder(
                    $response->transactionId,
                    $response->token,
                    self::PAYMENTSTATUSPAID
                );
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                break;
            default:
                $this->forward('cancel');
                break;
        }
    }

    /**
     * Cancel action method
     */
    public function cancelAction()
    {
    }

    /**
     * Creates the url parameters
     */
    private function getUrlParameters()
    {
        /** @var InvoicePaymentService $service */
        $service = $this->container->get('byjuno_payment.byjuno_payment_service');
        $router = $this->Front()->Router();
        $user = $this->getUser();
        $billing = $user['billingaddress'];

        $parameter = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyShortName(),
            'firstName' => $billing['firstname'],
            'lastName' => $billing['lastname'],
            'returnUrl' => $router->assemble(['action' => 'return', 'forceSecure' => true]),
            'cancelUrl' => $router->assemble(['action' => 'cancel', 'forceSecure' => true]),
            'token' => $service->createPaymentToken($this->getAmount(), $billing['customernumber'])
        ];

        return '?' . http_build_query($parameter);
    }

    /**
     * Returns the URL of the payment provider. This has to be replaced with the real payment provider URL
     *
     * @return string
     */
    protected function getProviderUrl()
    {
        return $this->Front()->Router()->assemble(['controller' => 'DemoPaymentProvider', 'action' => 'pay']);
    }
}
