<?php

use ByjunoPayments\Components\ByjunoPayment\PaymentResponse;
use ByjunoPayments\Components\ByjunoPayment\InvoicePaymentService;

class Shopware_Controllers_Frontend_PaymentInvoice extends Shopware_Controllers_Frontend_Payment
{
    const PAYMENTSTATUSPAID = 12;
    const PAYMENTSTATUSOPEN = 17;
    const PAYMENTSTATUSVOID = 30;


    const ORDERSTATUSCANCEL = 4;
    const ORDERSTATUSINPROGRESS = 1;

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        /**
         * Check if one of the payment methods is selected. Else return to default controller.
         */
        switch ($this->getPaymentShortName()) {
            case 'byjuno_payment_invoice':
                if ($this->gatewayAction()) {
                    $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                    break;
                } else {
                    $this->forward('cancel');
                    break;
                }
            default:
                $this->redirect(['controller' => 'checkout']);
                break;
        }
    }

    public function SaveLog(ByjunoRequest $request, $xml_request, $xml_response, $status, $type) {
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

    /**
     * Gateway action method.
     *
     * Collects the payment information and transmit it to the payment provider.
     */
    private function gatewayAction()
    {
        $mode = Shopware()->Config()->get("ByjunoPayments", "plugin_mode");

        $user = $this->getUser();
        $billing = $user['billingaddress'];
        $shipping = $user['shippingaddress'];
        $status = 0;
        $request = CreateShopWareShopRequestUserBilling($user, $billing, $shipping, $this);
        $xml = $request->createRequest();
        $byjunoCommunicator = new \ByjunoCommunicator();
        if (isset($mode) && $mode == 'live') {
            $byjunoCommunicator->setServer('live');
        } else {
            $byjunoCommunicator->setServer('test');
        }
        $response = $byjunoCommunicator->sendRequest($xml);
        if ($response) {
            $byjunoResponse = new ByjunoResponse();
            $byjunoResponse->setRawResponse($response);
            $byjunoResponse->processResponse();
            $status = (int)$byjunoResponse->getCustomerRequestStatus();
            $statusLog = "Order request (S1)";
            $this->saveLog($request, $xml, $response, $status, $statusLog);
            if (intval($status) > 15) {
                $status = 0;
            }
        }
        if ($status == 2) {
            $this->saveOrder(1, uniqid("byjuno_"), self::PAYMENTSTATUSOPEN);
            /* @var $order \Shopware\Models\Order\Order */
            $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')
                ->findOneBy(array('number' => $this->getOrderNumber()));
            $orderModule = Shopware()->Modules()->Order();
            $orderModule->setPaymentStatus($order->getId(), self::PAYMENTSTATUSPAID, false);
            $orderModule->setOrderStatus($order->getId(), self::ORDERSTATUSCANCEL, false);
            $mail = $orderModule->createStatusMail($order->getId(), self::PAYMENTSTATUSPAID);
            $mail->clearRecipients();
            $mail->addTo("igor.sutugin@gmail.com");
            $orderModule->sendStatusMail($mail);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Cancel action method
     */
    public function cancelAction()
    {
        $_SESSION["byjuno"]["paymentMessage"] = "Payment canceled";
        $this->redirect(array(
            'controller' => 'checkout',
            'action' => 'payment'
        ));
    }

}
