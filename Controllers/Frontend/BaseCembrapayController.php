<?php


use Cembrapay\CembrapayPayments\Api\CembraPayCheckoutAuthorizationResponse;
use Cembrapay\CembrapayPayments\Api\CembraPayCommunicator;
use Cembrapay\CembrapayPayments\Api\CembraPayConstants;
use Cembrapay\CembrapayPayments\Api\CembraPayLoginDto;
use \Shopware\Components\Logger;
use CembrapayPayments\CembrapayPayments;
use Shopware\Components\NumberRangeIncrementerInterface;

abstract class Shopware_Controllers_Frontend_BaseCembrapayController extends Shopware_Controllers_Frontend_Payment
{
    private $PAYMENTSTATUSPAID = 12;
    private $PAYMENTSTATUSOPEN = 17;
    private $PAYMENTSTATUSVOID = 30;

    private $ORDERSTATUSCANCEL = 4;
    private $ORDERSTATUSINPROGRESS = 1;
    public $custom_birthday;
    public $custom_gender;
    protected $payment_plan;
    protected $payment_send;
    protected $payment_send_to;
    public static $sesStatusString = '';
    protected function saveTransactionPaymentData($orderId, $key, $paymentData)
    {
        $sql = 'UPDATE `s_order_attributes` SET `'.$key.'`=? WHERE orderID = ?';
        Shopware()->Db()->query($sql, array(serialize($paymentData), $orderId));
    }

    protected function getPaymentDataFromOrder($orderId, $key)
    {
        $sql = 'SELECT `'.$key.'` FROM `s_order_attributes` WHERE orderID = ?';
        $paymentData = Shopware()->Db()->fetchOne($sql, $orderId);

        return unserialize($paymentData);
    }

    protected function minMaxCheck()
    {
        $min = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_minimum");
        $max = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_maximum");
        $amount = $this->getAmount();
        if ((isset($min) && $min != "" && $amount < $min) || (isset($max) && $max != "" && $amount > $max))
        {
            return false;
        }
        return true;
    }

    /**
     * Cancel action method
     */
    public function cancelcdpAction()
    {
        $snippets = Shopware()->Snippets()->getNamespace('frontend/cembrapay/index');
        $_SESSION["cembrapay"]["paymentMessage"] = $snippets->get('paymentcdp_canceled', "CembraPay invoice");
        $this->redirect(array(
            'controller' => 'checkout',
            'action' => 'payment'
        ));
    }

    /**
     * Cancel action method
     */
    public function cancelAction()
    {
        $snippets = Shopware()->Snippets()->getNamespace('frontend/cembrapay/index');
        $_SESSION["cembrapay"]["paymentMessage"] = $snippets->get('payment_canceled', "CembraPay invoice");
        $this->redirect(array(
            'controller' => 'checkout',
            'action' => 'cart'
        ));
    }
    /**
     * Cancel action method
     */
    public function cancelminmaxAction()
    {
        $snippets = Shopware()->Snippets()->getNamespace('frontend/cembrapay/index');
        $_SESSION["cembrapay"]["paymentMessage"] = $snippets->get('paymentminmax_canceled', "CembraPay invoice");
        $this->redirect(array(
            'controller' => 'checkout',
            'action' => 'payment'
        ));
    }

    /**
     * Gateway action method.
     *
     * Collects the payment information and transmit it to the payment provider.
     */
    protected function gatewayAction()
    {
        if (!empty($_SESSION["cembrapay"]["processing"]) && $_SESSION["cembrapay"]["processing"] == true) {
            return false;
        }
        $logger = Shopware()->Container()->get('corelogger');
        $_SESSION["cembrapay"]["processing"] = true;
        $mode = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_mode");
        $user = $this->getUser();
        $requestScreening = Cembrapay_ScreeningRequest($user);
        if ($requestScreening) {
            $orderModule = Shopware()->Modules()->Order();
            $this->saveOrder(1, uniqid("cembrapay_"), $this->PAYMENTSTATUSOPEN);
            /* @var $order \Shopware\Models\Order\Order */
            $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')
                ->findOneBy(array('number' => $this->getOrderNumber()));

            $billing = $user['billingaddress'];
            $shipping = $user['shippingaddress'];
            $requestAUT = Cembrapay_CreateShopWareShopRequestUserBilling($user, $billing, $shipping, $this, $this->payment_plan, $this->payment_send, $this->getOrderNumber());
            $CembraPayRequestName = "Authorization request";
            if ($requestAUT->custDetails->custType == CembraPayConstants::$CUSTOMER_BUSINESS) {
                $CembraPayRequestName = "Authorization request company";
            }

            $json = $requestAUT->createRequest();
            $cembrapayCommunicator = new CembraPayCommunicator();
            if (isset($mode) && $mode == 'Live') {
                $cembrapayCommunicator->setServer('live');
            } else {
                $cembrapayCommunicator->setServer('test');
            }
            $accessData = Cembrapay_GetAccessData($mode);
            $response = $cembrapayCommunicator->sendAuthRequest($json, $accessData, function ($object, $token, $accessData) {

            });
            if ($response) {
                /* @var $responseRes CembraPayCheckoutAuthorizationResponse */
                $responseRes = CembraPayConstants::authorizationResponse($response);
                $status = $responseRes->processingStatus;
                Cembrapay_SaveLog($requestAUT->requestMsgId, $requestAUT->custDetails->firstName, $requestAUT->custDetails->lastName, $json, $response, $status, $CembraPayRequestName);
            } else {
                Cembrapay_SaveLog($requestAUT->requestMsgId, $requestAUT->custDetails->firstName, $requestAUT->custDetails->lastName, $json, "", "ERROR", $CembraPayRequestName);
            }
            $cancelStatusId = Shopware()->Config()->getByNamespace("CembrapayPayments", "S5_default_cancel_id");
            $cancelStatusId = intval($cancelStatusId);
            if ($cancelStatusId <= 0) {
                $cancelStatusId = $this->ORDERSTATUSCANCEL;
            }

            $successStatusId = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_order_default_success_id");
            $successStatusId = intval($successStatusId);
            if ($successStatusId < 0) {
                $successStatusId = $this->ORDERSTATUSINPROGRESS;
            }

            $successPaymentStatusId = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_payment_default_success_id");
            $successPaymentStatusId = intval($successPaymentStatusId);
            if ($successPaymentStatusId <= 0) {
                $successPaymentStatusId = $this->PAYMENTSTATUSPAID;
            }
            if ($status == CembraPayConstants::$AUTH_OK) {
                $logger->log(Logger::INFO, "Cembrapay AUTH order Ok");
                $orderModule->setPaymentStatus($order->getId(), $successPaymentStatusId, false);
                if ($successStatusId != 0) {
                    $orderModule->setOrderStatus($order->getId(), $successStatusId, false);
                }
                $this->saveTransactionPaymentData($order->getId(), 'payment_plan', $this->payment_plan);
                $_SESSION["cembrapay"]["processing"] = false;
                return true;
            } else {
                $logger->log(Logger::INFO, "Cembrapay AUTH order Failed");
                $orderModule->setPaymentStatus($order->getId(), $this->PAYMENTSTATUSVOID, false);
                $orderModule->setOrderStatus($order->getId(), $cancelStatusId, false);
                $_SESSION["cembrapay"]["processing"] = false;
                return false;
            }
        } else {
            $logger->log(Logger::INFO, "Cembrapay screening failed");
            $_SESSION["cembrapay"]["processing"] = false;
            return false;
        }

    }

    protected function baseConfirmActions()
    {
        /**
         * Check if one of the payment methods is selected. Else return to default controller.
         */
        if ($this->Request()->isPost()) {
            $this->payment_plan = $this->Request()->getParam('payment_plan');
            $config = Shopware()->Config();
            if ($config->getByNamespace("CembrapayPayments", "cembrapay_allowpostal") == "Disabled") {
                $this->payment_send = "email";
            } else {
                $this->payment_send = $this->Request()->getParam('invoice_send');
            }
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
            if ($custom_birthday != null && !empty($custom_birthday["day"]) && !empty($custom_birthday["month"]) && !empty($custom_birthday["year"])) {
                $this->custom_birthday = $custom_birthday["year"]."-".$custom_birthday["month"]."-".$custom_birthday["day"];
                if (!empty($user["additional"]["user"]["id"])) {
                    if (!checkdate($custom_birthday["month"], $custom_birthday["day"], $custom_birthday["year"])) {
                        throw new \Exception("wrong_dob");
                    }
                    /* @var $customer \Shopware\Models\Customer\Customer */
                    $customer = Shopware()->Models()->getRepository('Shopware\Models\Customer\Customer')
                        ->findOneBy(array('id' => $user["additional"]["user"]["id"]));
                    try {
                        $customer->setBirthday(new \DateTime($this->custom_birthday));
                        Shopware()->Models()->persist($customer);
                        Shopware()->Models()->flush();
                    } catch (Exception $e) {
                        $logger = Shopware()->Container()->get('corelogger');
                        $logger->log($logger, $e->getMessage());
                    }
                }
            }

        }
    }
    protected function getSnippet(array $snippet)
    {
        $snippets = Shopware()->Snippets();
        return $snippets->getNamespace($snippet['namespace'])->get($snippet['name'], $snippet['default'], true);
    }


}