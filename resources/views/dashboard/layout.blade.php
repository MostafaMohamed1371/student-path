<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('school-icon.svg') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        :root {
            color-scheme: light;
            --bg: #eef3f8;
            --card-bg: #ffffff;
            --card-border: #d9e2ec;
            --text-main: #1a2744;
            --text-muted: #6b7280;
            --primary: #1a2744;
            --primary-hover: #152238;
            --danger-bg: #fff1f2;
            --danger-border: #fecdd3;
            --danger-text: #be123c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text-main);
        }

        .page-login {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 16px 40px -24px rgba(15, 23, 42, 0.35);
        }

        .login-title {
            margin: 0;
            font-size: 40px;
            line-height: 1.1;
            color: var(--text-main);
            font-weight: 700;
        }

        .login-subtitle {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 16px;
        }

        .login-form {
            margin-top: 28px;
            display: grid;
            gap: 20px;
        }

        .field-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #475569;
            font-weight: 600;
        }

        .phone-row {
            display: flex;
            align-items: stretch;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .phone-prefix {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            background: #f8fafc;
            border-inline-end: 1px solid #e2e8f0;
            font-size: 14px;
            color: #334155;
            white-space: nowrap;
            font-weight: 600;
        }

        .input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            padding: 12px;
            font-size: 16px;
            outline: none;
        }

        .phone-row .input {
            border: 0;
            border-radius: 0;
        }

        .input:focus,
        .phone-row:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 39, 68, 0.12);
        }

        .help {
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .btn-primary {
            width: 100%;
            border: 0;
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            padding: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .login-links {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .link {
            color: var(--text-main);
            text-decoration: none;
        }

        .link:hover {
            text-decoration: underline;
        }

        .link-muted {
            color: var(--text-muted);
        }

        .alert {
            margin-top: 18px;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--danger-border);
            background: var(--danger-bg);
            color: var(--danger-text);
            font-size: 14px;
        }

        .alert-success {
            border-color: #86efac;
            background: #dcfce7;
            color: #166534;
        }

        .dashboard-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 250px 1fr;
        }

        .sidebar {
            background: #111f37;
            color: #dbe5f2;
            padding: 18px 16px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .sidebar-title {
            margin: 0;
            font-size: 18px;
        }

        .sidebar-subtitle {
            margin: 4px 0 0;
            color: #9fb2cf;
            font-size: 13px;
        }

        .sidebar-nav {
            display: grid;
            gap: 8px;
        }

        .sidebar-link {
            display: block;
            text-decoration: none;
            color: #dbe5f2;
            padding: 10px 12px;
            border-radius: 8px;
        }

        .sidebar-link:hover,
        .sidebar-link.is-active {
            background: #243858;
        }

        .sidebar-footer {
            margin-top: auto;
            font-size: 13px;
            color: #c1d1e7;
        }

        .sidebar-footer p {
            margin: 4px 0;
        }

        .mono {
            font-family: ui-monospace, Menlo, Monaco, monospace;
        }

        .dash-main {
            padding: 18px;
        }

        .dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .dash-header h1 {
            margin: 0;
            font-size: 24px;
        }

        .card {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 16px;
        }

        .stats-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 16px;
        }

        .stat-card h3 {
            margin: 0 0 6px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
        }

        .stat-card p {
            margin: 0;
            font-size: 30px;
            font-weight: 700;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .table th,
        .table td {
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            padding: 10px 8px;
            font-size: 14px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge.ok {
            background: #ecfdf3;
            color: #166534;
        }

        .badge.off {
            background: #fef2f2;
            color: #991b1b;
        }

        .btn-muted {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #1f2937;
            border-radius: 10px;
            padding: 8px 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-muted:hover {
            background: #f8fafc;
        }

        .form-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .form-grid label {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .form-grid label > span {
            font-size: 14px;
            color: #475569;
            font-weight: 600;
        }

        .form-grid input,
        .form-grid select,
        .form-grid textarea {
            width: 100%;
            max-width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
        }

        .form-grid input:focus,
        .form-grid select:focus,
        .form-grid textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 39, 68, 0.12);
        }

        @media (max-width: 520px) {
            .login-card {
                padding: 22px;
                border-radius: 16px;
            }

            .login-title {
                font-size: 34px;
            }
        }

        @media (max-width: 980px) {
            .dashboard-shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                gap: 10px;
            }

            .stats-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .dash-main {
                padding: 12px;
            }

            .card {
                padding: 12px;
            }

            .form-grid .btn-primary,
            .form-grid .btn-muted {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
