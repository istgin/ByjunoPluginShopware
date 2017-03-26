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
                $viewAssignments = array(
                    'sBreadcrumb' => 'xXXX'
                );
                $this->View()->assign($viewAssignments);
                break;
            default:
                $this->redirect(['controller' => 'checkout']);
                break;
        }
    }

    public function confirmAction()
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
        $statusS1 = 0;
        $statusS2 = 0;
        $request = CreateShopWareShopRequestUserBilling($user, $billing, $shipping, $this, "NO");
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
            $statusS1 = (int)$byjunoResponse->getCustomerRequestStatus();
            $statusLog = "Order request (S1)";
            $this->saveLog($request, $xml, $response, $statusS1, $statusLog);
            if (intval($statusS1) > 15) {
                $statusS1 = 0;
            }
        }
        if ($statusS1 == 2) {
            $request = CreateShopWareShopRequestUserBilling($user, $billing, $shipping, $this, "YES");
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
                $statusS2 = (int)$byjunoResponse->getCustomerRequestStatus();
                $statusLog = "Order complete (S3)";
                $this->saveLog($request, $xml, $response, $statusS2, $statusLog);
                if (intval($statusS2) > 15) {
                    $statusS2 = 0;
                }
            }
        } else {
            return false;
        }
        if ($statusS1 == 2 && $statusS2 == 2) {
            $this->saveOrder(1, uniqid("byjuno_"), self::PAYMENTSTATUSOPEN);
            /* @var $order \Shopware\Models\Order\Order */
            $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')
                ->findOneBy(array('number' => $this->getOrderNumber()));
            $orderModule = Shopware()->Modules()->Order();
            $orderModule->setPaymentStatus($order->getId(), self::PAYMENTSTATUSPAID, false);
            $orderModule->setOrderStatus($order->getId(), self::ORDERSTATUSCANCEL, false);
            $mail = $orderModule->createStatusMail($order->getId(), self::PAYMENTSTATUSPAID);
            $mail->clearRecipients();
            $mail->addTo(Shopware()->Config()->get("ByjunoPayments", "byjuno_email"));
            $orderModule->sendStatusMail($mail);
            return true;
        }
        return false;
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
