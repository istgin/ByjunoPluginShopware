<?php

use ByjunoPayments\Components\ByjunoPayment\PaymentResponse;
use ByjunoPayments\Components\ByjunoPayment\InvoicePaymentService;
include(__DIR__."/BaseController.php");
class Shopware_Controllers_Frontend_PaymentInvoice extends Shopware_Controllers_Frontend_BasebyjunoController
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
            case 'byjuno_payment_invoice':

                $minMaxCheck = $this->minMaxCheck();
                if (!$minMaxCheck) {
                    $this->forward('cancelminmax');
                    break;
                }

                $cdp_enabled = Shopware()->Config()->getByNamespace("ByjunoPayments", "byjuno_cdpenable");
                if ($cdp_enabled == 'Enabled') {
                    $allowed = $this->CDPRequest("byjuno_payment_invoice");
                    if (!$allowed) {
                        $this->forward('cancelcdp');
                        break;
                    }
                }

                $snippets = Shopware()->Snippets()->getNamespace('frontend/byjuno/index');
                $config = Shopware()->Config();
				$custom_fields = 1;
				if ($config->getByNamespace("ByjunoPayments", "byjuno_genderbirthday") == "Disabled") {
					$custom_fields = 0;
				}
                $byjuno_allowpostal = 1;
                if ($config->getByNamespace("ByjunoPayments", "byjuno_allowpostal") == "Disabled") {
                    $byjuno_allowpostal = 0;
                }
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
                    'byjuno_allowpostal' => $byjuno_allowpostal,
                    'custom_bd_enable' => $custom_fields,
                    'custom_gender_enable' => $custom_fields,
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
        $this->baseConfirmActions();
        switch ($this->getPaymentShortName()) {
            case 'byjuno_payment_invoice':
                if ($this->gatewayAction('byjuno_payment_invoice')) {
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
