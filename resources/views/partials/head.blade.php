<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="theme-color" content="#05060B" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
