{block name='frontend_index_breadcrumb' prepend}
    {if $messageByjuno != ''}
        {include file="frontend/_includes/messages.tpl" type="error" content="$messageByjuno"}
    {/if}
{/block}