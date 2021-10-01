{block name="frontend_checkout_error_messages_byjuno"}
    {if $messageByjuno != ''}
        {include file="frontend/_includes/messages.tpl" type="error" content="$messageByjuno"}
    {/if}
{/block}