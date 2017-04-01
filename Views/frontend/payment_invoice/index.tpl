{extends file='frontend/index/index.tpl'}

{block name='frontend_index_content_left'}{/block}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb = [['name'=>"{s name=PayByjunoInvoice namespace=frontend/byjuno/index}{/s}"]]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div id="payment" class="grid_20" style="margin:10px 0 10px 0;">
        {s name="PayByjunoInvoice" namespace="frontend/byjuno/index"}Pay with byjuno invoice{/s}

        <form action="{url action='confirm'}" method="post" id="proceed_byjuno_invoice" name="proceed_byjuno_invoice">
            <table>
                <tr>
                    <td><label for="payment_plan">Select payment plan</label></td>
                    <td>
                        <select id="payment_plan" name="payment_plan" required>
                            {foreach from=$paymentplans item=paymentplan}
                                <option value="{$paymentplan.key}">{$paymentplan.val}</option>
                            {/foreach}
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Please select invoice delivery method</td>
                    <td>
                    </td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td>
                        <button type="submit" class="btn is--primary is--large right is--icon-right" form="proceed_byjuno_invoice" data-preloader-button="true">Proceed payment<i class="icon--arrow-right"></i>
                        </button>
                    </td>
                </tr>
            </table>
        </form>
    </div>
{/block}
