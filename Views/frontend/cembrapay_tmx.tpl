{block name='frontend_index_header_javascript' append}
{$scriptLink = "https://h.online-metrix.net/fp/tags.js?org_id=`$tmx_orgid|escape:'javascript'`&session_id=`$tmx_session|escape:'javascript'`&pageid=checkout"}
    <script>
        window.addEventListener('load', function () {
            window.setTimeout(function() {
                const s = document.createElement('script');
                s.type = 'text/javascript';
                s.src = 'https://cdn.cembrapay.ch/public/fp-clientlib-v5.js';
                s.async = true;
                s.onload = function() {
                    threatmetrix.profile("prof4rs.cembrapay.ch", "{$tmx_orgid|escape}", "{$tmx_session|escape}");
                };
                document.body.appendChild(s);
            }, 0);
        });
    </script>
    <link rel="preload" href="https://cdn.cembrapay.ch/public/fp-clientlib-v5.js" as="script">
    <noscript>
        <iframe style="width: 100px; height: 100px; border: 0; position: absolute; top: -5000px;" src="https://prof4rs.cembrapay.ch/fp/tags?org_id={$tmx_orgid|escape}&session_id={$tmx_session|escape}"></iframe>
    </noscript>
{/block}