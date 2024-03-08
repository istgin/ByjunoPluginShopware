{extends file="parent:frontend/index/index.tpl"}

{block name="frontend_index_body_classes"}
	{if $Controller == 'PaymentInvoice'}
		{strip}
		    is--ctl-checkout is--act-confirm
		    {if $sUserLoggedIn} is--user{/if}
		    {if $sOneTimeAccount} is--one-time-account{/if}
		    {if $sTarget} is--target-{$sTarget|escapeHtml}{/if} is--minimal-header		    
		    {if !$theme.displaySidebar} is--no-sidebar{/if}
		 {/strip}
	{else}
		{$smarty.block.parent}
	{/if}
 {/block}