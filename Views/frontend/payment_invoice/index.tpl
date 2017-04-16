{extends file='frontend/index/index.tpl'}

{block name='frontend_index_content_left'}{/block}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb = [['name'=>"{s name=PayByjunoInvoice namespace=frontend/byjuno/index}Pay with byjuno invoice{/s}"]]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div id="payment" class="grid_20" style="margin:10px 0 10px 0;">

        <form action="{url action='confirm'}" method="post" id="proceed_byjuno_invoice" name="proceed_byjuno_invoice">
            {if $custom_gender_enable == 1}
                <div style="padding: 0 0 5px 0"><label for="custom_gender" style="font-size: 18px"><b>Gender</b></label></div>
                <div style="padding: 0 0 15px 0">
                    <select id="custom_gender" name="custom_gender" required>
                        {foreach from=$genders item=gender}
                            <option value="{$gender.key}" {if $gender.key == $customer_gender}selected{/if}>{$gender.val}</option>
                        {/foreach}
                    </select>
                </div>
            {/if}
            {if $custom_bd_enable == 1}
                <div style="padding: 0 0 5px 0"><label for="custom_birthday" style="font-size: 18px"><b>Date of birth</b></label></div>
                <div style="padding: 0 0 15px 0">
                    {block name="frontend_account_profile_profile_input_birthday_day"}
                        <div class="profile--birthday field--select" style="display: inline-block">
                            <select name="custom_birthday[day]" required="required" aria-required="true"
                                    class="is--required">

                                <option value="">{s name='RegisterBirthdaySelectDay' namespace="frontend/register/personal_fieldset"}{/s}</option>

                                {for $day = 1 to 31}
                                    <option value="{$day}" {if $day == $customer_day}selected{/if}>{$day}</option>
                                {/for}
                            </select>
                        </div>
                    {/block}

                    {block name="frontend_account_profile_profile_input_birthday_month"}
                        <div class="profile--birthmonth field--select" style="display: inline-block">
                            <select name="custom_birthday[month]" required="required" aria-required="true"
                                    class="is--required">

                                <option value="">{s name='RegisterBirthdaySelectMonth' namespace="frontend/register/personal_fieldset"}{/s}</option>

                                {for $month = 1 to 12}
                                    <option value="{$month}" {if $month == $customer_month}selected{/if}>{$month}</option>
                                {/for}
                            </select>
                        </div>
                    {/block}

                    {block name="frontend_account_profile_profile_input_birthday_year"}
                        <div class="profile--birthyear field--select" style="display: inline-block">
                            <select name="custom_birthday[year]"
                                    required="required" aria-required="true"
                                    class="is--required">

                                <option value="">{s name='RegisterBirthdaySelectYear' namespace="frontend/register/personal_fieldset"}{/s}</option>

                                {for $year = date("Y") to date("Y")-120 step=-1}
                                    <option value="{$year}" {if $year == $customer_year}selected{/if}>{$year}</option>
                                {/for}
                            </select>
                        </div>
                    {/block}
                </div>
            {/if}

            <div style="padding: 0 0 5px 0"><label for="payment_plan" style="font-size: 18px"><b>Select payment plan</b></label></div>
            <div style="padding: 0 0 15px 0">
                {foreach from=$paymentplans item=paymentplan}
                    <input type="radio" name="payment_plan" {$paymentplan.checked} value="{$paymentplan.key}"> &nbsp;{$paymentplan.val} <a href="{$paymentplan.url}" target="_blank">(T&C)</a><br>
                {/foreach}
                {if count($paymentplans) == 0}
                    {s name=PaymentPlansNotAvailable namespace=frontend/byjuno/index}No any payment plans are available{/s}
                {/if}
            </div>
            <div style="padding: 0 0 5px 0"><label for="invoice_send" style="font-size: 18px"><b>Select invoice delivery method</b></label></div>
            <div style="padding: 0 0 15px 0">
                {foreach from=$paymentdelivery item=paymentdeliver}
                    {if $paymentdeliver.key == "email"}
                        <input type="radio" name="invoice_send" checked="checked" value="{$paymentdeliver.key}"> &nbsp;Rechnungsversand via E-Mail (ohne Gebühr) an: {$paymentdeliver.val}<br>
                    {/if}
                    {if $paymentdeliver.key == "postal"}
                        <input type="radio" name="invoice_send" value="{$paymentdeliver.key}"> &nbsp;Rechnungsversand in Papierform via Post (gegen Gebühr von CHF 3.50) an: {$paymentdeliver.val}<br>
                    {/if}
                {/foreach}
            </div>
            <button type="submit" class="btn is--primary is--large left is--icon-right"{if count($paymentplans) == 0} disabled="disabled"{/if} form="proceed_byjuno_invoice" data-preloader-button="true">Proceed payment<i class="icon--arrow-right"></i>
            </button>
        </form>
    </div>
{/block}
