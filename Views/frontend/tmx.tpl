{$scriptLink = "https://h.online-metrix.net/fp/tags.js?org_id=`$tmx_orgid|escape:'javascript'`&session_id=`$tmx_session|escape:'javascript'`&pageid=checkout"}
<script>
    window.addEventListener('load', function () {
        window.setTimeout(function() {
            const s = document.createElement('script');
            s.type = 'text/javascript';
            s.src = {$scriptLink};
            s.async = true;

            $('head').append(s);
        }, 0);
    });
</script>
<link rel="preload" href="{$scriptLink}" as="script">
<noscript>
    <iframe style="width: 100px; height: 100px; border: 0; position: absolute; top: -5000px;" src="https://h.online-metrix.net/tags?org_id={$tmx_orgid|escape:'javascript'}&session_id={$tmx_session|escape:'javascript'}&pageid=checkout"></iframe>
</noscript>