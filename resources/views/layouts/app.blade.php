<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Live Draw Kamar — Gathering SD 2026 Pangandaran')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;800&family=Inter:wght@400;600;700&family=Pacifico&display=swap"
      rel="stylesheet"
    />
    @vite(['resources/css/draw.css', 'resources/js/app.js'])
  </head>
  <body>
    @yield('body')
  </body>
</html>
