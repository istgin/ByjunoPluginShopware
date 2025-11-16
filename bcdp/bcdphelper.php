<?php

use Byjuno\ByjunoPayments\Api\CembraPayCheckoutAutRequest;
use Byjuno\ByjunoPayments\Api\CembraPayCommunicator;
use Byjuno\ByjunoPayments\Api\CembraPayConstants;
use Byjuno\ByjunoPayments\Api\CembraPayLoginDto;
use Byjuno\ByjunoPayments\Api\CustomerConsents;

function Cembrapay_mapMethod($method) {
    if ($method == 'byjuno_payment_installment') {
        return "INSTALLMENT";
    } else {
        return "INVOICE";
    }
}

function Cembrapay_getClientIp() {
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

function Cembrapay_mapRepayment($type) {
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

function Cembrapay_SaveLog($requestId, $firstname, $lastname, $xml_request, $xml_response, $status, $type) {
    $sql     = '
            INSERT INTO s_plugin_byjuno_transactions (requestid, requesttype, firstname, lastname, ip, status, datecolumn, xml_request, xml_responce)
                    VALUES (?,?,?,?,?,?,?,?,?)
        ';
    Shopware()->Db()->query($sql, Array(
        $requestId,
        $type,
        $firstname,
        $lastname,
        $_SERVER['REMOTE_ADDR'],
        $status,
        date('Y-m-d\TH:i:sP'),
        $xml_request,
        $xml_response
    ));
}

function Cembrapay_GetAccessData($mode) {
    $accessData = new CembraPayLoginDto();
    $accessData->timeout = (int)Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_timeout");
    if ($accessData->timeout < 0) {
        $accessData->timeout = 30;
    }
    if ($mode == 'test') {
        $accessData->mode = 'test';
        $accessData->username = Shopware()->Config()->getByNamespace("ByjunoPayments", "cembra_clientid_live");
        $accessData->password = Shopware()->Config()->getByNamespace("ByjunoPayments", "cembra_password_live");
    } else {
        $accessData->mode = 'live';
        $accessData->username = Shopware()->Config()->getByNamespace("ByjunoPayments", "cembra_clientid_test");
        $accessData->password = Shopware()->Config()->getByNamespace("ByjunoPayments", "cembra_password_test");
    }
    return $accessData;
}

function Cembrapay_ScreeningRequest($user)
{
    $mode = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_mode");
    $b2b = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_b2b");
    $billing = $user['billingaddress'];
    $shipping = $user['shippingaddress'];
    $basket = Shopware()->Modules()->Basket()->sGetAmount();
    $request = Cembrapay_CreateShopWareShopRequestUserBillingCDP($user, $billing, $shipping, $basket['totalAmount']);
    $statusLog = "Screening request";
    if ($request->custDetails->custType == CembraPayConstants::$CUSTOMER_BUSINESS && $b2b) {
        $statusLog = "Screening request company";
    }
    $json = $request->createRequest();
    $cembrapayCommunicator = new CembraPayCommunicator();
    if (isset($mode) && $mode == 'Live') {
        $cembrapayCommunicator->setServer('live');
    } else {
        $cembrapayCommunicator->setServer('test');
    }
    $accessData = Cembrapay_GetAccessData($mode);
    $response = $cembrapayCommunicator->sendScreeningRequest($json, $accessData, function ($object, $token, $accessData) {
        $object->saveToken($token, $accessData);
    });
    if (!empty($response)) {
        $responseRes = CembraPayConstants::screeningResponse($response);
        $screeningStatus = $responseRes->processingStatus;
        Cembrapay_SaveLog($request->requestMsgId, $request->custDetails->firstName, $request->custDetails->lastName, $json, $response, $screeningStatus, $statusLog);
    } else {
        $screeningStatus = CembraPayConstants::$SCREENING_NET_ERROR;
        Cembrapay_SaveLog($request->requestMsgId, $request->custDetails->firstName, $request->custDetails->lastName, $json, "", $screeningStatus, $statusLog);
    }
    if ($screeningStatus == CembraPayConstants::$SCREENING_OK) {
        return true;
    }
    return false;
}

function Cembrapay_IsB2bByjuno($billing) {
    if (!empty($billing["company"])) {
        return true;
    }
    return false;
}

/* @var $controller \Shopware_Controllers_Frontend_BasebyjunoController  */
function Cembrapay_CreateShopWareShopRequestUserBilling($user, $billing, $shipping, $controller, $paymentmethod, $repayment, $invoiceDelivery, $riskOwner, $orderId = "", $orderClosed = "NO", $transactionNumber = "") {

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
    $addressAdd = '';
    if (!empty($billing['additionalAddressLine1'])) {
        $addressAdd = ' '.trim((String)$billing['additionalAddressLine1']);
    }
    if (!empty($billing['additionalAddressLine2'])) {
        $addressAdd = $addressAdd.' '.trim((String)$billing['additionalAddressLine2']);
    }
    $request->setFirstLine(trim((String)$billing['street'].' '.$billing['streetnumber'].$addressAdd));
    $request->setCountryCode(strtoupper((String)$countryBilling));
    $request->setPostCode((String)$billing['zipcode']);
    $request->setTown((String)$billing['city']);
    $request->setFax((String)$billing['fax']);

    if (!empty($billing["company"])) {
        $request->setCompanyName1($billing["company"]);
    }
    if (!empty($billing["company"]) && !empty($billing["vatId"])) {
        $request->setCompanyVatId($billing["vatId"]);
    }
    if (!empty($shipping["company"])) {
        $request->setDeliveryCompanyName1($shipping["company"]);
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

    if ($transactionNumber != "") {
        $extraInfo["Name"] = 'TRANSACTIONNUMBER';
        $extraInfo["Value"] = $transactionNumber;
        $request->setExtraInfo($extraInfo);
    }

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
    $extraInfo["Value"] = Byjuno_getClientIp();
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

    $addressShippingAdd = '';
    if (!empty($shipping['additionalAddressLine1'])) {
        $addressShippingAdd = ' '.trim((String)$shipping['additionalAddressLine1']);
    }
    if (!empty($shipping['additionalAddressLine2'])) {
        $addressShippingAdd = $addressShippingAdd.' '.trim((String)$shipping['additionalAddressLine2']);
    }

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $extraInfo["Value"] = trim($shipping['street'].' '.$shipping['streetnumber'].$addressShippingAdd);
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

    if (!empty($orderId)) {
        $extraInfo["Name"] = 'ORDERID';
        $extraInfo["Value"] = $orderId;
        $request->setExtraInfo($extraInfo);
    }
    $extraInfo["Name"] = 'PAYMENTMETHOD';
    $extraInfo["Value"] = Byjuno_mapMethod($paymentmethod);
    $request->setExtraInfo($extraInfo);

    if ($repayment != "") {
        $extraInfo["Name"] = 'REPAYMENTTYPE';
        $extraInfo["Value"] = Byjuno_mapRepayment($repayment);
        $request->setExtraInfo($extraInfo);
    }

    if ($riskOwner != "") {
        $extraInfo["Name"] = 'RISKOWNER';
        $extraInfo["Value"] = $riskOwner;
        $request->setExtraInfo($extraInfo);
    }

    $extraInfo["Name"] = 'CONNECTIVTY_MODULE';
    $extraInfo["Value"] = 'CembraPay ShopWare module 1.4.1';
    $request->setExtraInfo($extraInfo);
    return $request;

}

function Cembrapay_SaveS4Log(ByjunoS4Request $request, $xml_request, $xml_response, $status, $type, $firstName, $lastName)
{
    $sql     = '
            INSERT INTO s_plugin_byjuno_transactions (requestid, requesttype, firstname, lastname, ip, status, datecolumn, xml_request, xml_responce)
                    VALUES (?,?,?,?,?,?,?,?,?)
        ';
    Shopware()->Db()->query($sql, Array(
        $request->getRequestId(),
        $type,
        $firstName,
        $lastName,
        $_SERVER['REMOTE_ADDR'],
        (($status != "") ? $status : 'Error'),
        date('Y-m-d\TH:i:sP'),
        $xml_request,
        $xml_response
    ));
}

function Cembrapay_SaveS5Log(ByjunoS5Request $request, $xml_request, $xml_response, $status, $type, $firstName, $lastName)
{
    $sql     = '
            INSERT INTO s_plugin_byjuno_transactions (requestid, requesttype, firstname, lastname, ip, status, datecolumn, xml_request, xml_responce)
                    VALUES (?,?,?,?,?,?,?,?,?)
        ';
    Shopware()->Db()->query($sql, Array(
        $request->getRequestId(),
        $type,
        $firstName,
        $lastName,
        $_SERVER['REMOTE_ADDR'],
        (($status != "") ? $status : 'Error'),
        date('Y-m-d\TH:i:sP'),
        $xml_request,
        $xml_response
    ));
}

function Cembrapay_SaveS4LogCron(ByjunoS4Request $request, $xml_request, $xml_response, $status, $type, $firstName, $lastName)
{
    $sql     = '
            INSERT INTO s_plugin_byjuno_transactions (requestid, requesttype, firstname, lastname, ip, status, datecolumn, xml_request, xml_responce)
                    VALUES (?,?,?,?,?,?,?,?,?)
        ';
    Shopware()->Db()->query($sql, Array(
        $request->getRequestId(),
        $type,
        $firstName,
        $lastName,
        "Cron",
        (($status != "") ? $status : 'Error'),
        date('Y-m-d\TH:i:sP'),
        $xml_request,
        $xml_response
    ));
}

function Cembrapay_SaveS5LogCron(ByjunoS5Request $request, $xml_request, $xml_response, $status, $type, $firstName, $lastName)
{
    $sql     = '
            INSERT INTO s_plugin_byjuno_transactions (requestid, requesttype, firstname, lastname, ip, status, datecolumn, xml_request, xml_responce)
                    VALUES (?,?,?,?,?,?,?,?,?)
        ';
    Shopware()->Db()->query($sql, Array(
        $request->getRequestId(),
        $type,
        $firstName,
        $lastName,
        "Cron",
        (($status != "") ? $status : 'Error'),
        date('Y-m-d\TH:i:sP'),
        $xml_request,
        $xml_response
    ));
}

function Cembrapay_CreateShopWareShopRequestUserBillingCDP($user, $billing, $shipping, $amount) {

    $b2b = true; // TODO

    $sql     = 'SELECT `countryiso` FROM s_core_countries WHERE id = ' . intval($billing["countryID"]);
    $countryBilling = Shopware()->Db()->fetchOne($sql);

    $sql     = 'SELECT `countryiso` FROM s_core_countries WHERE id = ' . intval($shipping["countryID"]);
    $countryShipping = Shopware()->Db()->fetchOne($sql);

    $sql     = 'SELECT `locale` FROM s_core_locales WHERE id = ' . intval(Shopware()->Shop()->getLocale()->getId());
    $langName = Shopware()->Db()->fetchRow($sql);
    $lang = 'de';
    if (!empty($langName["locale"]) && strlen($langName["locale"]) > 4) {
        $lang = substr($langName["locale"], 0, 2);
    }

    $request = new CembraPayCheckoutAutRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_SCREENING;
    $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
    $request->merchantOrderRef = null;
    $request->amount = round(number_format($amount, 2, '.', '') * 100);
    $request->currency = Shopware()->Config()->currency;

    $reference = $billing["id"];
    if (empty($reference)) {
        $request->custDetails->merchantCustRef = uniqid("guest_");
        $request->custDetails->loggedIn = false;
    } else {
        $request->custDetails->merchantCustRef = (string)$reference;
        $request->custDetails->loggedIn = true;
    }

    if (!empty($billing["company"]) && $b2b) {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_BUSINESS;
        $request->custDetails->companyName = $billing["company"];
    } else {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_PRIVATE;
    }

    $request->custDetails->firstName = html_entity_decode((String)$billing['firstname'], ENT_COMPAT, 'UTF-8');
    $request->custDetails->lastName = html_entity_decode((String)$billing['lastname'], ENT_COMPAT, 'UTF-8');
    $request->custDetails->language = $lang;

    $request->custDetails->salutation = CembraPayConstants::$GENTER_UNKNOWN;
    $additionalInfo = $user["additional"]["user"];
    if (!empty($additionalInfo['salutation'])) {
        if (strtolower($additionalInfo['salutation']) == 'ms') {
            $request->custDetails->salutation = CembraPayConstants::$GENTER_FEMALE;
        } else if (strtolower($additionalInfo['salutation']) == 'mr') {
            $request->custDetails->salutation = CembraPayConstants::$GENTER_MALE;
        }
    }

    if (!empty($additionalInfo['birthday']) && substr($additionalInfo['birthday'], 0, 4) != '0000') {
        $request->custDetails->dateOfBirth = (String)$additionalInfo['birthday'];
    }
    $addressAdd = '';
    if (!empty($billing['additionalAddressLine1'])) {
        $addressAdd = ' '.trim((String)$billing['additionalAddressLine1']);
    }
    if (!empty($billing['additionalAddressLine2'])) {
        $addressAdd = $addressAdd.' '.trim((String)$billing['additionalAddressLine2']);
    }
    $request->billingAddr->addrFirstLine = html_entity_decode(trim((String)$billing['street'].' '.$billing['streetnumber'].$addressAdd), ENT_COMPAT, 'UTF-8');
    $request->billingAddr->postalCode = (String)$billing['zipcode'];
    $request->billingAddr->town = html_entity_decode((String)$billing['city'], ENT_COMPAT, 'UTF-8');
    $request->billingAddr->country = strtoupper((String)$countryBilling);
    $request->custContacts->email = (String)$user["additional"]["user"]["email"];
    $request->custContacts->phonePrivate = (String)$billing['phone'];

    $request->deliveryDetails->deliveryDetailsDifferent = true;
    $request->deliveryDetails->deliveryFirstName = html_entity_decode($shipping['firstname'], ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliverySecondName =  html_entity_decode($shipping['lastname'], ENT_COMPAT, 'UTF-8');
    if (!empty($shipping["company"]) && $b2b) {
        $request->deliveryDetails->deliveryCompanyName = html_entity_decode($shipping["company"], ENT_COMPAT, 'UTF-8');
    }
    $request->deliveryDetails->deliverySalutation = null;
    $addressShippingAdd = '';
    if (!empty($shipping['additionalAddressLine1'])) {
        $addressShippingAdd = ' '.trim((String)$shipping['additionalAddressLine1']);
    }
    if (!empty($shipping['additionalAddressLine2'])) {
        $addressShippingAdd = $addressShippingAdd.' '.trim((String)$shipping['additionalAddressLine2']);
    }
    $request->deliveryDetails->deliveryAddrFirstLine = html_entity_decode(trim($shipping['street'].' '.$shipping['streetnumber'].$addressShippingAdd), ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliveryAddrPostalCode = $shipping['zipcode'];
    $request->deliveryDetails->deliveryAddrTown = html_entity_decode($shipping['city'], ENT_COMPAT, 'UTF-8');
    $request->deliveryDetails->deliveryAddrCountry = strtoupper($countryShipping);

    if (isset($tmx_enable) && $tmx_enable == 'Enabled' && isset($tmxorgid) && $tmxorgid != '' && !empty($_SESSION["byjuno_tmx"])) {
        $request->sessionInfo->tmxSessionId = $_SESSION["byjuno_tmx"];
    }

    $request->cembraPayDetails->riskOnlyOnCembraPay = true;
    $request->sessionInfo->sessionIp = Cembrapay_getClientIp();

    $customerConsents = new CustomerConsents();
    $customerConsents->consentType = "SCREENING";
    $customerConsents->consentProvidedAt = "MERCHANT";
    $customerConsents->consentDate = CembraPayCheckoutAutRequest::Date();
    $customerConsents->consentReference = "MERCHANT DATA PRIVACY";
    $request->customerConsents = array($customerConsents);

    $request->merchantDetails->transactionChannel = "WEB";
    $request->merchantDetails->integrationModule = "CembraPay Shopware 5.7.X module 2.0.0";

    return $request;
}