<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Esticly</title>
    <style nonce="{{ $nonce }}">
        html, body { margin: 0; background: #fff; }
        body { min-height: 100vh; display: grid; place-items: center; }
        #challenge { width: min(100%, 340px); }
    </style>
    <script nonce="{{ $nonce }}">
        function send(message) {
            if (window.EsticlyCaptcha) window.EsticlyCaptcha.postMessage(JSON.stringify(message));
        }
        function startChallenge() {
            turnstile.render('#challenge', {
                sitekey: {{ Illuminate\Support\Js::from($siteKey) }},
                action: {{ Illuminate\Support\Js::from($action) }},
                cData: {{ Illuminate\Support\Js::from($challenge) }},
                language: {{ Illuminate\Support\Js::from($locale) }},
                theme: 'light',
                size: 'flexible',
                callback: token => send({token: token}),
                'error-callback': () => { send({error: true}); return true; },
                'expired-callback': () => send({error: true}),
                'timeout-callback': () => send({error: true})
            });
            send({ready: true});
        }
    </script>
    <script nonce="{{ $nonce }}" src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=startChallenge&amp;render=explicit" async defer></script>
</head>
<body><div id="challenge"></div></body>
</html>
