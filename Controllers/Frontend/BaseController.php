<?php

use ByjunoPayments\Components\ByjunoPayment\PaymentResponse;
use ByjunoPayments\Components\ByjunoPayment\InvoicePaymentService;

class Shopware_Controllers_Frontend_BasebyjunoController extends Shopware_Controllers_Frontend_Payment
{
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
}