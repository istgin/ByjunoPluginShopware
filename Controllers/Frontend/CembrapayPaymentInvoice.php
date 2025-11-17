<?php

use Shopware\Bundle\AccountBundle\Form\Account\PersonalFormType;

include(__DIR__."/BaseController.php");
class Shopware_Controllers_Frontend_CembrapayPaymentInvoice extends Shopware_Controllers_Frontend_BasecembrapayController
{
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
            case 'cembrapay_payment_invoice':

                $minMaxCheck = $this->minMaxCheck();
                if (!$minMaxCheck) {
                    $this->forward('cancelminmax');
                    break;
                }

                $cdp_enabled = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_cdpenable");
                $user = $this->getUser();
                $billing = $user['billingaddress'];
                $b2bEnabled = Shopware()->Config()->getByNamespace("CembrapayPayments", "cembrapay_b2b");
                $IsB2BPayment = false;
                if ($b2bEnabled == 'Enabled' && !empty($billing["company"]))
                {
                    $IsB2BPayment = true;
                }
                if ($cdp_enabled == 'Enabled') {
                    if (!empty(self::$sesStatusString)) {
                        if (self::$sesStatusString == 'true') {
                            $sesStatus = true;
                        } else {
                            $sesStatus = false;
                        }
                    }
                    if (!isset($sesStatus)) {
                        $allowed = Cembrapay_ScreeningRequest($this->getUser());
                        $converted_res = $allowed ? 'true' : 'false';
                        self::$sesStatusString = $converted_res;
                    } else {
                        $allowed = $sesStatus;
                    }
                    if (!$allowed) {
                        $this->forward('cancelcdp');
                        break;
                    }
                }

                $snippets = Shopware()->Snippets()->getNamespace('frontend/cembrapay/index');
                $config = Shopware()->Config();
                $custom_fields_gender = 1;
                if ($config->getByNamespace("CembrapayPayments", "cembrapay_gender") == "Disabled") {
                    $custom_fields_gender = 0;
                }
                $custom_fields_birthday = 0;
                if ($config->getByNamespace("CembrapayPayments", "cembrapay_birthday") == "Enabled"
                    && (empty($user["additional"]["user"]['birthday']) || substr($user["additional"]["user"]['birthday'], 0, 4) == '0000')) {
                    $custom_fields_birthday = 1;
                }
                $cembrapay_allowpostal = 1;
                if ($config->getByNamespace("CembrapayPayments", "cembrapay_allowpostal") == "Disabled") {
                    $cembrapay_allowpostal = 0;
                }
                $checked = 'checked=\"\"';
                $paymentplans = Array();

                if ($b2bEnabled == 'Enabled' && Cembrapay_IsB2bCembrapay($billing)) {
                    if ($config->getByNamespace("CembrapayPayments", "cembrapay_invoice_b2b") == "Enabled" && !$IsB2BPayment) {
                        $paymentplans[] = Array(
                            "checked" => $checked,
                            "key" => "cembrapay_invoice",
                            "val" => $snippets->get('cembrapay_invoice', "CembraPay invoice"),
                            "url" => $snippets->get('cembrapay_invoice_toc_url', "https://cembrapay.ch/de/terms")
                        );
                        $checked = '';
                    }
                    if ($config->getByNamespace("CembrapayPayments", "single_invoice_b2b") == "Enabled") {
                        $paymentplans[] =
                            Array(
                                "checked" => $checked,
                                "key" => "sinlge_invoice",
                                "val" => $snippets->get('single_invoice', "Single invoice"),
                                "url" => $snippets->get('single_invoice_toc_url', "https://cembrapay.ch/de/terms")
                            );
                    }
                } else {
                    if ($config->getByNamespace("CembrapayPayments", "cembrapay_invoice") == "Enabled" && !$IsB2BPayment) {
                        $paymentplans[] = Array(
                            "checked" => $checked,
                            "key" => "cembrapay_invoice",
                            "val" => $snippets->get('cembrapay_invoice', "CembraPay invoice"),
                            "url" => $snippets->get('cembrapay_invoice_toc_url', "https://cembrapay.ch/de/terms")
                        );
                        $checked = '';
                    }
                    if ($config->getByNamespace("CembrapayPayments", "single_invoice") == "Enabled") {
                        $paymentplans[] =
                            Array(
                                "checked" => $checked,
                                "key" => "sinlge_invoice",
                                "val" => $snippets->get('single_invoice', "Single invoice"),
                                "url" => $snippets->get('single_invoice_toc_url', "https://cembrapay.ch/de/terms")
                            );
                    }
                }

                if (count($paymentplans) == 0) {
                    $this->forward('cancelcdp');
                    return;
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
                $messagecembrapay = '';
                if (!empty($_SESSION["cembrapay"]["controllerMessage"])) {
                    $messagecembrapay = $_SESSION["cembrapay"]["controllerMessage"];
                    unset($_SESSION["cembrapay"]["controllerMessage"]);
                }
                $viewAssignments = array(
                    'genders' => Array(
                        Array("key" => "1",
                            "val" => $snippets->get('mr', "Mr")
                        ),
                        Array("key" => "2",
                            "val" => $snippets->get('ms', "Ms")
                        )
                    ),
                    'cembrapay_allowpostal' => $cembrapay_allowpostal,
                    'custom_bd_enable' => $custom_fields_birthday,
                    'custom_gender_enable' => $custom_fields_gender,
                    'customer_day' => $customer_day,
                    'customer_month' => $customer_month,
                    'customer_year' => $customer_year,
                    'customer_gender' => $customer_gender,
                    'paymentplans' => $paymentplans,
                    'messagecembrapay' => $messagecembrapay,
                    'paymentdelivery' => Array(
                        Array("key" => "email",
                            "val" => (String)$user["additional"]["user"]["email"]
                        ),
                        Array("key" => "postal",
                            "val" => $address
                        )
                    )
                );
                $_SESSION["cembrapay"]["processing"] = false;
                if ($custom_fields_birthday == 0 && $custom_fields_gender == 0 && $cembrapay_allowpostal == 0 && count($paymentplans) == 1) {
                    $this->payment_plan = $paymentplans[0]["key"];
                    $this->payment_send = "email";
                    $this->payment_send_to = (String)$user["additional"]["user"]["email"];
                    if ($this->gatewayAction()) {
                        $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                        break;
                    } else {
                        $this->forward('cancel');
                        break;
                    }
                } else if ($custom_fields_birthday == 1 && $custom_fields_gender == 0 && $cembrapay_allowpostal == 0 && count($paymentplans) == 1) {
                    $additionalInfo = $user["additional"]["user"];
                    if (!empty($additionalInfo['birthday']) && substr($additionalInfo['birthday'], 0, 4) != '0000') {
                        $this->payment_plan = $paymentplans[0]["key"];
                        $this->payment_send = "email";
                        $this->payment_send_to = (String)$user["additional"]["user"]["email"];
                        if ($this->gatewayAction()) {
                            $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                            break;
                        } else {
                            $this->forward('cancel');
                            break;
                        }
                    } else {
                        $this->View()->assign($viewAssignments);
                    }
                } else {
                    $this->View()->assign($viewAssignments);
                }
                break;
            default:
                $this->redirect(['controller' => 'checkout']);
                break;
        }
    }
    public function confirmAction()
    {
        $_SESSION["cembrapay"]["controllerMessage"] = null;
        try {
            $this->baseConfirmActions();
        } catch (Exception $e) {
            $snippets = Shopware()->Snippets()->getNamespace('frontend/cembrapay/index');
            if ($e->getMessage() == 'wrong_dob') {
                $_SESSION["cembrapay"]["controllerMessage"] = $this->getSnippet(PersonalFormType::SNIPPET_BIRTHDAY);
            } else {
                $_SESSION["cembrapay"]["controllerMessage"] = $snippets->get('payment_canceled', "Payment cancelled");
            }
            $this->redirect(['controller' => 'CembrapayPaymentInvoice']);
            return;
        }
        switch ($this->getPaymentShortName()) {
            case 'cembrapay_payment_invoice':
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

}
