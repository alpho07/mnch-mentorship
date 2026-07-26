<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MNCH Mentorship</title>
    <style>
        body {
            margin: 0; min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center; padding: 32px;
            font-family: -apple-system, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%);
            color: #fff;
        }
        .logo {
            width: 64px; height: 64px; border-radius: 20px;
            background: linear-gradient(135deg, #4F6AF5, #6C63FF);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 24px; box-shadow: 0 8px 28px rgba(79,106,245,0.4);
        }
        h1 { font-size: 20px; font-weight: 800; margin: 0 0 8px; }
        p { font-size: 14px; color: rgba(255,255,255,0.7); margin: 0 0 28px; line-height: 1.5; max-width: 320px; }
        .spinner {
            width: 28px; height: 28px; border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.25); border-top-color: #fff;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .fallback { margin-top: 28px; font-size: 13px; color: rgba(255,255,255,0.55); }
        .fallback a { color: #fff; font-weight: 700; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="logo">
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
            <rect x="13" y="2" width="8" height="30" rx="4" fill="white" fill-opacity="0.95"/>
            <rect x="2" y="13" width="30" height="8" rx="4" fill="white" fill-opacity="0.95"/>
            <circle cx="17" cy="17" r="5" fill="white"/>
        </svg>
    </div>
    <h1>{{ $heading }}</h1>
    <p>{{ $message }}</p>
    <div class="spinner"></div>
    <div class="fallback">
        Nothing happening? <a href="{{ $loginUrl }}">Continue in your browser instead</a>.
    </div>

    <script>
        // Fired ~2s after page load so the confirmation message above is
        // actually readable before the handoff happens. No Play Store
        // fallback is set on the intent — the app isn't published there
        // yet, so an uninstalled-app case just leaves the user on this
        // page with the "Continue in your browser" link above.
        setTimeout(function () {
            window.location.href = 'intent://mnchkenyamentorship.org/#Intent;scheme=https;package=com.mnch.mentorship.app;end';
        }, 2000);
    </script>
</body>
</html>
