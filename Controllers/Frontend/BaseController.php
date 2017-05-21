<?php

use ByjunoPayments\Components\ByjunoPayment\PaymentResponse;
use ByjunoPayments\Components\ByjunoPayment\InvoicePaymentService;

class Shopware_Controllers_Frontend_BasebyjunoController extends Shopware_Controllers_Frontend_Payment
{
    public $custom_birthday;
    public $custom_gender;
    protected $payment_plan;
    protected $payment_send;
    protected $payment_send_to;
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

    protected function CDPRequest($paymentMethod)
    {
        $statusCDP = 0;
        $mode = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_mode");
        $b2b = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_b2b");
        $user = $this->getUser();
        $billing = $user['billingaddress'];
        $shipping = $user['shippingaddress'];
        $request = CreateShopWareShopRequestUserBilling($user, $billing, $shipping, $this, $paymentMethod, "", "", "", "",  "NO");
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
        $response = $byjunoCommunicator->sendRequest($xml);
        if ($response) {
            $byjunoResponse = new ByjunoResponse();
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

    protected function isStatusOkS2($status) {
        try {
            $accepted_S2_ij = Shopware()->Config()->getByNamespace("ByjunoPayments", "allowed_s2");
            $accepted_S2_merhcant = Shopware()->Config()->getByNamespace("ByjunoPayments", "allowed_s2_merchant");
            $ijStatus = explode(",", $accepted_S2_ij);
            $merchantStatus = explode(",", $accepted_S2_merhcant);
            if (in_array($status, $ijStatus)) {
                return true;
            } else if (in_array($status, $merchantStatus)) {
                return true;
            }
            return false;

        } catch (Exception $e) {
            return false;
        }
    }

    protected function isStatusOkCDP($status) {
        try {
            $accepted_CDP = Shopware()->Config()->getByNamespace("ByjunoPayments", "allowed_cdp");
            $ijStatus = explode(",", $accepted_CDP);
            if (in_array($status, $ijStatus)) {
                return true;
            }
            return false;

        } catch (Exception $e) {
            return false;
        }
    }


    protected function isStatusOkS3($status) {
        try {
            $accepted_S3 = Shopware()->Config()->getByNamespace("ByjunoPayments", "allowed_s3");
            $ijStatus = explode(",", $accepted_S3);
            if (in_array($status, $ijStatus)) {
                return true;
            }
            return false;

        } catch (Exception $e) {
            return false;
        }
    }

    protected function getStatusRisk($status) {
        try {
            $accepted_S2_ij = Shopware()->Config()->getByNamespace("ByjunoPayments", "allowed_s2");
            $accepted_S2_merhcant = Shopware()->Config()->getByNamespace("ByjunoPayments", "allowed_s2_merchant");
            $ijStatus = explode(",", $accepted_S2_ij);
            $merchantStatus = explode(",", $accepted_S2_merhcant);
            if (in_array($status, $ijStatus)) {
                return "IJ";
            } else if (in_array($status, $merchantStatus)) {
                return "CLIENT";
            }
            return "No owner";

        } catch (Exception $e) {
            return "INTERNAL ERROR";
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
}