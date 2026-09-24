{{-- Independent of sessions/auth so a database outage cannot break error rendering. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · EasyKids</title>
    <style>{!! file_get_contents(resource_path('css/ui.css')).file_get_contents(resource_path('css/responsive.css')).file_get_contents(resource_path('css/motion.css')) !!}</style>
</head>
<body data-theme="easykids">
<main id="main-content" class="container">
    <div class="auth-shell"><section class="card auth-card" aria-labelledby="error-title">
        <span class="badge">{{ $status }}</span>
        <h1 id="error-title" style="margin-top:12px">{{ $title }}</h1>
        <p class="muted">{{ $help }}</p>
        <div class="actions"><a class="btn" href="{{ $retryUrl }}">{{ __('ui.continue') }}</a></div>
    </section></div>
</main>
</body>
</html>
