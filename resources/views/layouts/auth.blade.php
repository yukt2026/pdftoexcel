<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'AI Invoice to Excel') }} - Authentication</title>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-navy: #172554;
            --primary-teal: #0F766E;
            --secondary-teal: #134E4A;
            --teal-light: #F0FDFA;
            --teal-border: #CCFBF1;
            --border-color: #E2E8F0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: radial-gradient(circle at top center, #1e3a8a 0%, #172554 45%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .auth-card {
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
            overflow: hidden;
            width: 100%;
            max-width: 440px;
        }

        .form-control:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }

        .btn-teal {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: #ffffff;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }

        .btn-teal:hover {
            background-color: var(--secondary-teal);
            border-color: var(--secondary-teal);
            color: #ffffff;
        }
    </style>
</head>
<body>

    <div class="auth-card p-4 p-md-5">
        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
