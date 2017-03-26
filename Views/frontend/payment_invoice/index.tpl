{extends file='frontend/index/index.tpl'}

{block name='frontend_index_content_left'}{/block}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb = [['name'=>"{s name=PayByjunoInvoice namespace=frontend/byjuno/index}{/s}"]]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div id="payment" class="grid_20" style="margin:10px 0 10px 0;">
        {s name="PayByjunoInvoice2" namespace="frontend/byjuno/index"}111111{/s}
        TESTOK!!111
    </div>
{/block}
