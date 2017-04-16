<?php

use ByjunoPayments\Components\ByjunoPayment\PaymentResponse;
use ByjunoPayments\Components\ByjunoPayment\InvoicePaymentService;
include(__DIR__."/BaseController.php");
class Shopware_Controllers_Frontend_PaymentInvoice extends Shopware_Controllers_Frontend_BasebyjunoController
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
                $snippets = Shopware()->Snippets()->getNamespace('frontend/byjuno/index');
                $config = Shopware()->Config();
                $checked = 'checked=\"\"';
                $paymentplans = Array();
                if ($config->getByNamespace("ByjunoPayments", "byjuno_invoice") == "Enabled") {
                    $paymentplans[] = Array(
                        "checked" => $checked,
                        "key" => "byjuno_invoice",
                        "val" => $snippets->get('byjuno_invoice', "Byjuno invoice"),
                        "url" => $snippets->get('byjuno_invoice_toc_url', "http://byjuno.ch/de/terms")
                    );
                    $checked = '';
                }
                if ($config->getByNamespace("ByjunoPayments", "single_invoice") == "Enabled") {
                    $paymentplans[] =
                        Array(
                            "checked" => $checked,
                            "key" => "sinlge_invoice",
                            "val" => $snippets->get('single_invoice', "Single invoice"),
                            "url" => $snippets->get('single_invoice_toc_url', "http://byjuno.ch/de/terms")
                        );
                }
                $user = $this->getUser();
                $addInfo = $user["additional"]["user"];
                $customer_gender = 1;
                if (!empty($addInfo['salutation'])) {
                    if (strtolower($addInfo['salutation']) == 'ms') {
                        $customer_gender = 2;
                    } else if (strtolower($addInfo['salutation']) == 'mr') {
                        $customer_gender = 1;
                    }
                }
                $customer_day = '';
                $customer_month = '';
                $customer_year = '';
                if (!empty($addInfo['birthday']) && substr($addInfo['birthday'], 0, 4) != '0000') {
                    $bd = explode("-", $addInfo['birthday']);
                    if (count($bd) == 3) {
                        $customer_day = $bd[2];
                        $customer_month = $bd[1];
                        $customer_year = $bd[0];
                    }
                }
                $billing = $user['billingaddress'];
                $address = trim(trim((String)$billing['street'].' '.$billing['streetnumber']).', '.(String)$billing['city'].', '.(String)$billing['zipcode']);
                $viewAssignments = array(
                    'genders' => Array(
                        Array("key" => "1",
                            "val" => $snippets->get('mr', "Mr")
                        ),
                        Array("key" => "2",
                            "val" => $snippets->get('ms', "Ms")
                        )
                    ),
                    'custom_bd_enable' => 1,
                    'custom_gender_enable' => 1,
                    'customer_day' => $customer_day,
                    'customer_month' => $customer_month,
                    'customer_year' => $customer_year,
                    'customer_gender' => $customer_gender,
                    'paymentplans' => $paymentplans,
                    'paymentdelivery' => Array(
                        Array("key" => "email",
                            "val" => (String)$user["additional"]["user"]["email"]
                        ),
                        Array("key" => "postal",
                            "val" => $address
                        )
                    )
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
        if ($this->Request()->isPost()) {
            $this->payment_plan = $this->Request()->getParam('payment_plan');
            $this->payment_send = $this->Request()->getParam('invoice_send');
            $user = $this->getUser();
            if ($this->payment_send == "email") {
                $this->payment_send_to = (String)$user["additional"]["user"]["email"];
            } else {
                $billing = $user['billingaddress'];
                $address = trim(trim((String)$billing['street'].' '.$billing['streetnumber']).', '.(String)$billing['city'].', '.(String)$billing['zipcode']);
                $this->payment_send_to = $address;
            }
            $custom_gender = $this->Request()->getParam('custom_gender');
            if ($custom_gender != null) {
                $this->custom_gender = $custom_gender;
            }
            $custom_birthday = $this->Request()->getParam('custom_birthday');

            if ($custom_birthday != null && isset($custom_birthday["day"]) && isset($custom_birthday["month"]) && isset($custom_birthday["year"])) {
                $this->custom_birthday = $custom_birthday["year"]."-".$custom_birthday["month"]."-".$custom_birthday["day"];
            }
        }
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
            $this->saveTransactionPaymentData($order->getId(), 'payment_plan', $this->payment_plan);
            //var_dump($this->getPaymentDataFromOrder($order->getId(), 'payment_plan'));
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
