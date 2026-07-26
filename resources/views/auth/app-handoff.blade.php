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
        .open-app {
            display: inline-block; padding: 14px 32px; border-radius: 14px;
            background: #fff; color: #3730A3; font-weight: 800; font-size: 15px;
            text-decoration: none; box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }
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

    {{-- Real user-tap link, not an auto-redirect: Chrome/Android block or
    mishandle custom-scheme navigation ("item not found") when it isn't
    triggered by an actual gesture. --}}
    <a class="open-app" href="intent://mnchkenyamentorship.org/#Intent;scheme=https;package=com.mnch.mentorship.app;end">
        Open MNCH App
    </a>

    <div class="fallback">
        Already have the app open? Just switch back to it and log in.<br>
        Or <a href="{{ $loginUrl }}">continue in your browser instead</a>.
    </div>
</body>
</html>


