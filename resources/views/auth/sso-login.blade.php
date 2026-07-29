<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login ke Sistem Informasi Kampus Terintegrasi">
    <title>SSO Login — Sistem Kampus</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            position: relative;
            overflow: hidden;
        }

        /* Animated background orbs */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            animation: float 8s ease-in-out infinite;
        }
        body::before {
            width: 500px; height: 500px;
            background: #3b82f6;
            top: -100px; left: -100px;
        }
        body::after {
            width: 400px; height: 400px;
            background: #8b5cf6;
            bottom: -100px; right: -100px;
            animation-delay: -4s;
        }
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.05); }
        }

        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 48px 44px;
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 10;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 32px;
        }
        .logo-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(59,130,246,0.4);
        }
        .logo-text h1 {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }
        .logo-text span {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            font-weight: 400;
        }

        .sso-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(59,130,246,0.15);
            border: 1px solid rgba(59,130,246,0.3);
            color: #93c5fd;
            font-size: 12px;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 100px;
            margin-bottom: 20px;
        }
        .sso-badge::before {
            content: '';
            width: 6px; height: 6px;
            background: #60a5fa;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .heading { margin-bottom: 28px; }
        .heading h2 {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
        }
        .heading p {
            font-size: 14px;
            color: rgba(255,255,255,0.5);
            line-height: 1.5;
        }
        .heading strong { color: #93c5fd; }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 13px 16px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .form-group input::placeholder { color: rgba(255,255,255,0.3); }
        .form-group input:focus {
            border-color: #3b82f6;
            background: rgba(59,130,246,0.08);
        }
        .form-group .error-msg {
            color: #f87171;
            font-size: 12px;
            margin-top: 6px;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.15s;
            margin-top: 8px;
            box-shadow: 0 6px 20px rgba(59,130,246,0.4);
        }
        .btn-login:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-login:active { transform: translateY(0); }

        .footer-note {
            margin-top: 28px;
            text-align: center;
            font-size: 12px;
            color: rgba(255,255,255,0.35);
            line-height: 1.6;
        }
        .footer-note a { color: rgba(255,255,255,0.5); text-decoration: none; }

        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-wrap">
            <div class="logo-icon">🎓</div>
            <div class="logo-text">
                <h1>Sistem Kampus</h1>
                <span>Single Sign-On Portal</span>
            </div>
        </div>

        <div class="sso-badge">SSO Aktif</div>

        <div class="heading">
            <h2>Masuk ke Akun Anda</h2>
            <p>Login sekali untuk mengakses <strong>{{ $app_name }}</strong> dan seluruh ekosistem aplikasi kampus.</p>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/sso/login" id="sso-login-form">
            @csrf
            <input type="hidden" name="_query" value="{{ $query }}">

            <div class="form-group">
                <label for="email">Email Institusi</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="nama@kampus.ac.id"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required
                >
                @error('email')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
                @error('password')
                    <div class="error-msg">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn-login" id="btn-submit">
                Masuk & Lanjutkan
            </button>
        </form>

        <div class="footer-note">
            Lupa password? <a href="/api/auth/forgot-password">Reset di sini</a><br>
            Dengan login, Anda menyetujui kebijakan penggunaan sistem kampus.
        </div>
    </div>

    <script>
        // Prevent double submit
        document.getElementById('sso-login-form').addEventListener('submit', function() {
            document.getElementById('btn-submit').textContent = 'Memproses...';
            document.getElementById('btn-submit').disabled = true;
        });
    </script>
</body>
</html>
