<?php

use Cembrapay\CembrapayPayments\Api\CembraPayCheckoutAutRequest;
use Cembrapay\CembrapayPayments\Api\CembraPayCheckoutChkRequest;
use Cembrapay\CembrapayPayments\Api\CembraPayCommunicator;
use Cembrapay\CembrapayPayments\Api\CembraPayConstants;
use Cembrapay\CembrapayPayments\Api\CembraPayLoginDto;
use Cembrapay\CembrapayPayments\Api\CustomerConsents;


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
    if ($type == 'single_invoice') {
        return CembraPayConstants::$SINGLEINVOICE;
    } else {
        return CembraPayConstants::$CEMBRAPAYINVOICE;
    }
}

function Cembrapay_SaveLog($requestId, $firstname, $lastname, $xml_request, $xml_response, $status, $type) {
    $sql     = '
            INSERT INTO s_plugin_cembrapay_transactions (requestid, requesttype, firstname, lastname, ip, status, datecolumn, xml_request, xml_responce)
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
    $accessData->timeout = 30;
    if ($mode == 'test') {
        $accessData->mode = 'test';
        $accessData->username = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembra_clientid_live");
        $accessData->password = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembra_password_live");
    } else {
        $accessData->mode = 'live';
        $accessData->username = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembra_clientid_test");
        $accessData->password = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembra_password_test");
    }
    return $accessData;
}

function Cembrapay_ScreeningRequest($user)
{
    $mode = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_mode");
    $b2b = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_b2b");
    $billing = $user['billingaddress'];
    $shipping = $user['shippingaddress'];
    $basket = Shopware()->Modules()->Basket()->sGetAmount();
    $request = Cembrapay_CreateShopWareShopRequestUserBillingScreening($user, $billing, $shipping, $basket['totalAmount']);
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

function Cembrapay_IsB2bCembrapay($billing) {
    if (!empty($billing["company"])) {
        return true;
    }
    return false;
}

/* @var $controller \Shopware_Controllers_Frontend_BaseCembrapayController  */
function Cembrapay_CreateShopWareShopRequestUserBilling($user, $billing, $shipping, $controller, $repayment, $invoiceDelivery, $orderId) {

    $b2b = false;
    $b2bEnabled = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_b2b");
    if ($b2bEnabled == 'Enabled')
    {
        $b2b = true;
    }
    $instantSettlement = false;
    $instantSettlementEnabled = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembra_instant_settlement");
    if ($instantSettlementEnabled == 'Enabled')
    {
        $instantSettlement = true;
    }
    $sql     = 'SELECT `countryiso` FROM s_core_countries WHERE id = ' . intval($billing["countryID"]);
    $countryBilling = Shopware()->Db()->fetchOne($sql);
    $sql     = 'SELECT `countryiso` FROM s_core_countries WHERE id = ' . intval($shipping["countryID"]);
    $countryShipping = Shopware()->Db()->fetchOne($sql);
    $sql     = 'SELECT `locale` FROM s_core_locales WHERE id = ' . intval(Shopware()->Shop()->getLocale()->getId());
    $langName = Shopware()->Db()->fetchRow($sql);

    $request = new CembraPayCheckoutAutRequest();
    $request->requestMsgType = CembraPayConstants::$MESSAGE_AUTH;
    $request->requestMsgId = CembraPayCheckoutAutRequest::GUID();
    $request->requestMsgDateTime = CembraPayCheckoutAutRequest::Date();
    $request->merchantOrderRef = $orderId;
    $request->amount = round(number_format($controller->getAmount(), 2, '.', '') * 100);
    $request->currency = $controller->getCurrencyShortName();
    $reference = $billing["id"];
    if (empty($reference)) {
        $request->custDetails->merchantCustRef = (string)uniqid("guest_");
        $request->custDetails->loggedIn = false;
    } else {
        $request->custDetails->merchantCustRef = (string)$reference;
        $request->custDetails->loggedIn = true;
    }
    if ($b2b && !empty($billing["company"])) {
        $vatId = "";
        if (!empty($billing["vatId"])) {
            $vatId =$billing["vatId"];
        }
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_BUSINESS;
        $request->custDetails->companyName = $billing["company"];
        $request->custDetails->companyRegNum = $vatId;
    } else {
        $request->custDetails->custType = CembraPayConstants::$CUSTOMER_PRIVATE;
    }
    $request->custDetails->firstName = (String)$billing['firstname'];
    $request->custDetails->lastName = (String)$billing['lastname'];
    $lang = 'de';
    if (!empty($langName["locale"]) && strlen($langName["locale"]) > 4) {
        $lang = substr($langName["locale"], 0, 2);
    }
    $request->custDetails->language = (string)$lang;
    $additionalInfo = $user["additional"]["user"];
    if (!empty($additionalInfo['birthday']) && substr($additionalInfo['birthday'], 0, 4) != '0000') {
        $request->custDetails->dateOfBirth = (String)$additionalInfo['birthday'];
    }
    if ($controller->custom_birthday != null) {
        $request->custDetails->dateOfBirth = $controller->custom_birthday;
    }

    $request->custDetails->salutation = CembraPayConstants::$GENTER_UNKNOWN;

    if (!empty($additionalInfo['salutation'])) {
        if (strtolower($additionalInfo['salutation']) == 'ms') {
            $request->custDetails->salutation = CembraPayConstants::$GENTER_FEMALE;
        } else if (strtolower($additionalInfo['salutation']) == 'mr') {
            $request->custDetails->salutation = CembraPayConstants::$GENTER_MALE;
        }
    }
    if ($controller->custom_gender != null) {
        if ($controller->custom_gender == 2) {
            $request->custDetails->salutation = CembraPayConstants::$GENTER_FEMALE;
        } else if ($controller->custom_gender == 1) {
            $request->custDetails->salutation = CembraPayConstants::$GENTER_MALE;
        }
    }

    $addressAdd = '';
    if (!empty($billing['additionalAddressLine1'])) {
        $addressAdd = ' '.trim((String)$billing['additionalAddressLine1']);
    }
    if (!empty($billing['additionalAddressLine2'])) {
        $addressAdd = $addressAdd.' '.trim((String)$billing['additionalAddressLine2']);
    }
    $request->billingAddr->addrFirstLine = trim((String)$billing['street'].' '.$billing['streetnumber'].$addressAdd);
    $request->billingAddr->postalCode = (string)(String)$billing['zipcode'];
    $request->billingAddr->country = strtoupper(strtoupper((String)$countryBilling));
    $request->billingAddr->town = (String)$billing['city'];

    $request->custContacts->phonePrivate = (String)$billing['phone'];
    $request->custContacts->email = (String)$user["additional"]["user"]["email"];

    $request->deliveryDetails->deliveryDetailsDifferent = true;

    $request->deliveryDetails->deliveryFirstName = (string)($shipping['firstname'] ?? "");
    $request->deliveryDetails->deliverySecondName = (string)($shipping['lastname'] ?? "");

    if ($b2b && !empty($shipping["company"])) {
        $request->deliveryDetails->deliveryCompanyName = (string)($shipping["company"] ?? "");
    }
    $request->deliveryDetails->deliverySalutation = null;

    $addressShippingAdd = '';
    if (!empty($shipping['additionalAddressLine1'])) {
        $addressShippingAdd = ' '.trim((String)$shipping['additionalAddressLine1']);
    }
    if (!empty($shipping['additionalAddressLine2'])) {
        $addressShippingAdd = $addressShippingAdd.' '.trim((String)$shipping['additionalAddressLine2']);
    }

    $extraInfo["Name"] = 'DELIVERY_FIRSTLINE';
    $request->deliveryDetails->deliveryAddrFirstLine = trim($shipping['street'].' '.$shipping['streetnumber'].$addressShippingAdd);
    $request->deliveryDetails->deliveryAddrPostalCode = $shipping['zipcode'] ?? "";
    $request->deliveryDetails->deliveryAddrTown = $shipping['city'] ?? "";
    $request->deliveryDetails->deliveryAddrCountry = $countryShipping ?? "";

    $request->order->basketItemsGoogleTaxonomies = array();
    $request->order->basketItemsPrices = array();

    if (!empty($_SESSION["cembrapay_tmx"])) {
        $request->sessionInfo->tmxSessionId = $_SESSION["cembrapay_tmx"];
    }

    $request->cembraPayDetails->riskOnlyOnCembraPay = false; // TODO
    $request->sessionInfo->sessionIp = Cembrapay_getClientIp();
    $request->cembraPayDetails->cembraPayPaymentMethod =  Cembrapay_mapRepayment($repayment);
    if ($invoiceDelivery == 'postal') {
        $request->cembraPayDetails->invoiceDeliveryType = "POSTAL";
    } else {
        $request->cembraPayDetails->invoiceDeliveryType = "EMAIL";
    }

    if ($instantSettlement) {
        $request->settlementDetails->instantSettlement = true;
        $request->settlementDetails->merchantInvoiceRef = $orderId;
    } else {
        $request->settlementDetails->instantSettlement = false;
    }

    $customerConsents = new CustomerConsents();
    $customerConsents->consentType = "CEMBRAPAY-TC";
    $customerConsents->consentProvidedAt = "MERCHANT";
    $customerConsents->consentDate = CembraPayCheckoutAutRequest::Date();
    $link = "https://cembrapay.ch/".$lang."/terms";
    $exLink = explode("/", $link);
    $consentReference = end($exLink);
    if (empty($consentReference) && isset($exLink[count($exLink) - 1])) {
        $consentReference = $exLink[count($exLink) - 2];
    }
    $customerConsents->consentReference = base64_encode($consentReference);
    $request->customerConsents = array($customerConsents);

    $request->merchantDetails->transactionChannel = "WEB";
    $request->merchantDetails->integrationModule = "CembraPay ShopWare 5 module 2.0.0";

    return $request;

}

function Cembrapay_CreateShopWareShopRequestUserBillingScreening($user, $billing, $shipping, $amount) {

    $b2b = false;
    $b2bEnabled = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_b2b");
    if ($b2bEnabled == 'Enabled')
    {
        $b2b = true;
    }
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

    if (!empty($_SESSION["cembrapay_tmx"])) {
        $request->sessionInfo->tmxSessionId = $_SESSION["cembrapay_tmx"];
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
    $request->merchantDetails->integrationModule = "CembraPay ShopWare 5 module 2.0.0";

    return $request;
}