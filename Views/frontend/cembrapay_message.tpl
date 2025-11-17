{block name='frontend_index_breadcrumb' prepend}
    {if $messageCembrapay != ''}
        {include file="frontend/_includes/messages.tpl" type="error" content="$messageCembrapay"}
    {/if}
{/block}