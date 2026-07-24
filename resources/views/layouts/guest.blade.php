<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body, html { height: 100%; margin: 0; }

            .login-bg {
                min-height: 100vh;
                background-image: url('{{ asset('images/mup.jpg') }}');
                background-size: cover;
                background-position: calc(50% - 210px) center;
                display: flex;
                align-items: stretch;
            }

            /* Lijeva strana - pokazuje pozadinu, logo centriran */
            .login-left {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
                padding: 2.5rem;
                /* lagani tamni overlay odozdo za čitljivost teksta */
                background: linear-gradient(to top, rgba(6,15,34,0.7) 0%, transparent 50%);
            }

            .login-left .brand {
                color: #f0c040;
                text-shadow: 0 2px 8px rgba(0,0,0,0.6);
            }

            .login-left .brand h1 {
                font-size: 2rem;
                font-weight: 700;
                margin: 0.5rem 0 0.25rem;
                letter-spacing: 1px;
            }

            .login-left .brand p {
                font-size: 0.95rem;
                color: rgba(255,255,255,0.7);
                margin: 0;
            }

            /* Desna strana - forma s blend prijelazom */
            .login-right {
                width: 420px;
                min-height: 100vh;
                background: linear-gradient(to right,
                    rgba(10, 25, 55, 0.55) 0%,
                    rgba(10, 25, 55, 0.90) 18%,
                    rgba(10, 25, 55, 0.97) 100%
                );
                backdrop-filter: blur(8px);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 3rem 2.5rem;
            }

            .login-right .logo-wrap {
                margin-bottom: 1.5rem;
                text-align: center;
            }

            .login-right .logo-wrap img {
                height: 170px;
                width: 170px;
                border-radius: 50%;
                object-fit: contain;
                padding: 12px;
                background: #fff;
                border: 3px solid rgba(240,192,64,0.6);
                box-shadow: 0 4px 24px rgba(0,0,0,0.6);
            }

            .login-right .app-title {
                color: #f0c040;
                font-size: 1.4rem;
                font-weight: 700;
                letter-spacing: 2px;
                text-align: center;
                margin-bottom: 0.25rem;
            }

            .login-right .app-sub {
                color: rgba(255,255,255,0.5);
                font-size: 0.78rem;
                text-align: center;
                letter-spacing: 1px;
                margin-bottom: 2rem;
                text-transform: uppercase;
            }

            /* Override Breeze form styles za tamnu temu */
            .login-right label {
                color: rgba(255,255,255,0.8) !important;
            }

            .login-right input[type="email"],
            .login-right input[type="password"],
            .login-right input[type="text"] {
                background: rgba(255,255,255,0.08) !important;
                border-color: rgba(255,255,255,0.2) !important;
                color: #fff !important;
            }

            .login-right input::placeholder {
                color: rgba(255,255,255,0.3) !important;
            }

            .login-right input:focus {
                background: rgba(255,255,255,0.13) !important;
                border-color: #f0c040 !important;
                outline: none;
                box-shadow: 0 0 0 2px rgba(240,192,64,0.25) !important;
            }

            .login-right a {
                color: rgba(200,200,255,0.75) !important;
            }

            .login-right a:hover {
                color: #f0c040 !important;
            }

            .login-right span.text-gray-600 {
                color: rgba(255,255,255,0.6) !important;
            }

            .login-right .form-wrap {
                width: 100%;
            }

            @media (max-width: 640px) {
                .login-left  { display: none; }
                .login-right { width: 100%; min-height: 100vh; }
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="login-bg">

            {{-- Lijeva strana --}}
            <div class="login-left">
                <div class="brand">
                    <p>Ministarstvo unutarnjih poslova RH</p>
                    <h1>Granična policija</h1>
                    <p>Sustav upravljanja dronovima</p>
                </div>
            </div>

            {{-- Desna strana - forma --}}
            <div class="login-right">
                <div class="logo-wrap">
                    <img src="{{ asset('images/granicna_policija_mala.png') }}" alt="GP Logo">
                </div>
                <div class="app-title">SkyGuard</div>
                <div class="app-sub">Sustav za upravljanje letovima</div>

                <div class="form-wrap">
                    {{ $slot }}
                </div>
            </div>

        </div>
    </body>
</html>
