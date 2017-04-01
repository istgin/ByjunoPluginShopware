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

                    <div style="padding: 0 0 5px 0"><label for="payment_plan" style="font-size: 16px"><b>Select payment plan</b></label></div>
                    <div style="padding: 0 0 15px 0">
                        <select id="payment_plan" name="payment_plan" required>
                            {foreach from=$paymentplans item=paymentplan}
                                <option value="{$paymentplan.key}">{$paymentplan.val}</option>
                            {/foreach}
                        </select>
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
                    <button type="submit" class="btn is--primary is--large left is--icon-right" form="proceed_byjuno_invoice" data-preloader-button="true">Proceed payment<i class="icon--arrow-right"></i>
                    </button>
        </form>
    </div>
{/block}
