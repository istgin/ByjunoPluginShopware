{extends file="parent:frontend/index/logo-container.tpl"}

{* Support Info *}
{block name='frontend_index_logo_supportinfo'}
    {if $theme.checkoutHeader && (({controllerName|lower} === 'checkout' && {controllerAction|lower} !== 'cart') || $Controller == 'PaymentInvoice')}
        <div class="logo--supportinfo block">
            {s name='RegisterSupportInfo' namespace='frontend/register/index'}{/s}
        </div>
    {/if}
{/block}