<?php

function mapMethod($method) {
    if ($method == 'byjuno_payment_installment') {
        return "INSTALLMENT";
    } else {
        return "INVOICE";
    }
}

function getClientIp() {
    $ipaddress = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if(!empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if(!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if(!empty($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } else if(!empty($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    $ipd = explode(",", $ipaddress);
    return trim(end($ipd));
}

function mapRepayment($type) {
    if ($type == 'installment_3') {
        return "10";
    } else if ($type == 'installment_10') {
        return "5";
    } else if ($type == 'installment_12') {
        return "8";
    } else if ($type == 'installment_24') {
        return "9";
    } else if ($type == 'installment_4x12') {
        return "1";
    } else if ($type == 'installment_4x10') {
        return "2";
    } else if ($type == 'sinlge_invoice') {
        return "3";
    } else {
        return "4";
    }
}

function CreateShopRequestS4($doucmentId, $amount, $orderAmount, $orderCurrency, $orderId, $customerId, $date)
{
    $request = new \ByjunoS4Request();
    $request->setClientId(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_clientid"));
    $request->setUserID(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_userid"));
    $request->setPassword(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_password"));
    $request->setVersion("1.00");
    $request->setRequestEmail(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_techemail"));

    $request->setRequestId(uniqid((String)$orderId . "_"));
    $request->setOrderId($orderId);
    $request->setClientRef($customerId);
    $request->setTransactionDate($date);
    $request->setTransactionAmount(number_format($amount, 2, '.', ''));
    $request->setTransactionCurrency($orderCurrency);
    $request->setAdditional1("INVOICE");
    $request->setAdditional2($doucmentId);
    $request->setOpenBalance(number_format($orderAmount, 2, '.', ''));

    return $request;

}

function CreateShopRequestS5($doucmentId, $amount, $orderCurrency, $orderId, $customerId, $date)
{

    $request = new \ByjunoS5Request();
    $request->setClientId(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_clientid"));
    $request->setUserID(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_userid"));
    $request->setPassword(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_password"));
    $request->setVersion("1.00");
    $request->setRequestEmail(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_techemail"));

    $request->setRequestId(uniqid((String)$orderId . "_"));
    $request->setOrderId($orderId);
    $request->setClientRef($customerId);
    $request->setTransactionDate($date);
    $request->setTransactionAmount(number_format($amount, 2, '.', ''));
    $request->setTransactionCurrency($orderCurrency);
    $request->setTransactionType("REFUND");
    $request->setAdditional2($doucmentId);

    return $request;
}

/* @var $controller \Shopware_Controllers_Frontend_BasebyjunoController  */
function CreateShopWareShopRequestUserBilling($user, $billing, $shipping, $controller, $paymentmethod, $repayment, $invoiceDelivery, $riskOwner, $orderId = "", $orderClosed = "NO") {

    $sql     = 'SELECT `countryiso` FROM s_core_countries WHERE id = ' . intval($billing["countryID"]);
    $countryBilling = Shopware()->Db()->fetchOne($sql);
    $sql     = 'SELECT `countryiso` FROM s_core_countries WHERE id = ' . intval($shipping["countryID"]);
    $countryShipping = Shopware()->Db()->fetchOne($sql);
    $request = new \ByjunoRequest();
    $request->setClientId(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_clientid"));
    $request->setUserID(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_userid"));
    $request->setPassword(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_password"));
    $request->setVersion("1.00");
    $request->setRequestEmail(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_techemail"));

    $sql     = 'SELECT `locale` FROM s_core_locales WHERE id = ' . intval(Shopware()->Shop()->getLocale()->getId());
    $langName = Shopware()->Db()->fetchRow($sql);
    $lang = 'de';
    if (!empty($langName["locale"]) && strlen($langName["locale"]) > 4) {
        $lang = substr($langName["locale"], 0, 2);
    }
    $request->setLanguage($lang);
    $request->setRequestId(uniqid((String)$billing["id"]."_"));
    $reference = $billing["id"];
    if (empty($reference)) {
        $request->setCustomerReference(uniqid("guest_"));
    } else {
        $request->setCustomerReference($billing["id"]);
    }
    $request->setFirstName((String)$billing['firstname']);
    $request->setLastName((String)$billing['lastname']);
    $request->setFirstLine(trim((String)$billing['street'].' '.$billing['streetnumber']));
    $request->setCountryCode(strtoupper((String)$countryBilling));
    $request->setPostCode((String)$billing['zipcode']);
    $request->setTown((String)$billing['city']);
    $request->setFax((String)$billing['fax']);

    if (!empty($billing["company"])) {
        $request->setCompanyName1($billing["company"]);
    }

    $request->setGender(0);
    $additionalInfo = $user["additional"]["user"];
    if (!empty($additionalInfo['salutation'])) {
        if (strtolower($additionalInfo['salutation']) == 'ms') {
            $request->setGender(2);
        } else if (strtolower($additionalInfo['salutation']) == 'mr') {
            $request->setGender(1);
        }
    }
    if ($controller->custom_gender != null) {
        $request->setGender($controller->custom_gender);
    }

    if (!empty($additionalInfo['birthday']) && substr($additionalInfo['birthday'], 0, 4) != '0000') {
        $request->setDateOfBirth((String)$additionalInfo['birthday']);
    }
    if ($controller->custom_birthday != null) {
        $request->setDateOfBirth($controller->custom_birthday);
    }

    $request->setTelephonePrivate((String)$billing['phone']);
    $request->setEmail((String)$user["additional"]["user"]["email"]);

    $extraInfo["Name"] = 'ORDERCLOSED';
    $extraInfo["Value"] = $orderClosed;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERAMOUNT';
    $extraInfo["Value"] = $controller->getAmount();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERCURRENCY';
    $extraInfo["Value"] = $controller->getCurrencyShortName();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'IP';
    $extraInfo["Value"] = getClientIp();
    $request->setExtraInfo($extraInfo);

    $tmx_enable = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_threatmetrixenable");
    $tmxorgid = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_threatmetrix");
    if (isset($tmx_enable) && $tmx_enable == 'Enabled' && isset($tmxorgid) && $tmxorgid != '' && !empty($_SESSION["byjuno_tmx"])) {
        $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
        $extraInfo["Value"] = $_SESSION["byjuno_tmx"];
        $request->setExtraInfo($extraInfo);
    }

    if ($invoiceDelivery == 'postal') {
        $extraInfo["Name"] = 'PAPER_INVOICE';
        $extraInfo["Value"] = 'YES';
        $request->setExtraInfo($extraInfo);
    }

    /* shipping information */
    $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
    $extraInfo["Value"] = $shipping['firstname'];
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_LASTNAME';
    $extraInfo["Value"] = $shipping['lastname'];
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $extraInfo["Value"] = trim($shipping['street'].' '.$shipping['streetnumber']);
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
    $extraInfo["Value"] = '';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
    $extraInfo["Value"] = $countryShipping;
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_POSTCODE';
    $extraInfo["Value"] = $shipping['zipcode'];
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_TOWN';
    $extraInfo["Value"] = $shipping['city'];
    $request->setExtraInfo($extraInfo);

    if ($orderId != "") {
        $extraInfo["Name"] = 'ORDERID';
        $extraInfo["Value"] = $orderId;
        $request->setExtraInfo($extraInfo);
    }
    $extraInfo["Name"] = 'PAYMENTMETHOD';
    $extraInfo["Value"] = mapMethod($paymentmethod);
    $request->setExtraInfo($extraInfo);

    if ($repayment != "") {
        $extraInfo["Name"] = 'REPAYMENTTYPE';
        $extraInfo["Value"] = mapRepayment($repayment);
        $request->setExtraInfo($extraInfo);
    }

    if ($riskOwner != "") {
        $extraInfo["Name"] = 'RISKOWNER';
        $extraInfo["Value"] = $riskOwner;
        $request->setExtraInfo($extraInfo);
    }

    $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
    $extraInfo["Value"] = 'Byjuno ShopWare module 1.0.0';
    $request->setExtraInfo($extraInfo);
    return $request;

}

function CreateShopWareShopRequest(\Shopware_Controllers_Frontend_PaymentInvoice $order)
{
    /* @var \Shopware\Models\Order\Billing $billing */
    $billing = $order->getBilling();
    /* @var \Shopware\Models\Order\Shipping $shipping */
    $shipping = $order->getShipping();
    $request = new \ByjunoRequest();
    $request->setClientId(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_clientid"));
    $request->setUserID(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_userid"));
    $request->setPassword(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_password"));
    $request->setVersion("1.00");
    $request->setRequestEmail(Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_techemail"));


    $sql     = 'SELECT `locale` FROM s_core_locales WHERE id = ' . intval(Shopware()->Shop()->getLocale()->getId());
    $langName = Shopware()->Db()->fetchRow($sql);
    $lang = 'de';
    if (!empty($langName["locale"]) && strlen($langName["locale"]) > 4) {
        $lang = substr($langName["locale"], 0, 2);
    }
    $request->setLanguage($lang);

    $request->setRequestId(uniqid((String)$billing->getId()));
    $reference = $billing->getCustomer();
    if (empty($reference)) {
        $request->setCustomerReference("guest_".$billing->getId());
    } else {
        $request->setCustomerReference($billing->getCustomer()->getId());
    }
    $request->setFirstName((String)$billing->getFirstName());
    $request->setLastName((String)$billing->getLastName());
    $request->setFirstLine(trim((String)$billing->getStreet().' '.$billing->getAdditionalAddressLine1().' '.$billing->getAdditionalAddressLine1()));
    $request->setCountryCode(strtoupper((String)$billing->getCountry()->getIso()));
    $request->setPostCode((String)$billing->getZipCode());
    $request->setTown((String)$billing->getCity());

	if (!empty($reference) && !empty($billing->getCustomer()->getBirthday()) && substr($billing->getCustomer()->getBirthday(), 0, 4) != '0000') {
		$request->setDateOfBirth((String)$billing->getCustomer()->getBirthday());
	}

    $request->setTelephonePrivate((String)$billing->getPhone());
    if (!empty($reference)) {
        $request->setEmail((String)$billing->getCustomer()->getEmail());
    }

    $extraInfo["Name"] = 'ORDERCLOSED';
    $extraInfo["Value"] = 'NO';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERAMOUNT';
    $extraInfo["Value"] = $order->getInvoiceAmount();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'ORDERCURRENCY';
    $extraInfo["Value"] = $order->getCurrency();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'IP';
    $extraInfo["Value"] = getClientIp();
    $request->setExtraInfo($extraInfo);

    $tmx_enable = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_threatmetrixenable");
    $tmxorgid = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_threatmetrix");
    if (isset($tmx_enable) && $tmx_enable == 'Enabled' && isset($tmxorgid) && $tmxorgid != '' && !empty($_SESSION["byjuno_tmx"])) {
        $extraInfo["Name"] = 'DEVICE_FINGERPRINT_ID';
        $extraInfo["Value"] = $_SESSION["byjuno_tmx"];
        $request->setExtraInfo($extraInfo);
    }

    /* shipping information */
    $extraInfo["Name"] = 'DELIVERY_FIRSTNAME';
    $extraInfo["Value"] = $shipping->getFirstName();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_LASTNAME';
    $extraInfo["Value"] = $shipping->getLastName();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $extraInfo["Value"] = trim((String)$shipping->getStreet().' '.$shipping->getAdditionalAddressLine1().' '.$shipping->getAdditionalAddressLine1());
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_HOUSENUMBER';
    $extraInfo["Value"] = '';
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_COUNTRYCODE';
    $extraInfo["Value"] = $shipping->getCountry()->getIso();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_POSTCODE';
    $extraInfo["Value"] = $shipping->getZipCode();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'DELIVERY_TOWN';
    $extraInfo["Value"] = $shipping->getCity();
    $request->setExtraInfo($extraInfo);

    $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
    $extraInfo["Value"] = 'Byjuno ShopWare module 1.3.0';
    $request->setExtraInfo($extraInfo);
    return $request;

}