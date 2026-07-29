<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verifikasi Email</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .header {
            background-color: #10B981;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .content p {
            margin-bottom: 20px;
            font-size: 16px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            background-color: #10B981;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 16px;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verifikasi Email Anda</h1>
        </div>
        <div class="content">
            <p>Halo {{ $name }},</p>
            <p>Terima kasih telah mendaftar di Sistem Terintegrasi Kampus. Silakan verifikasi alamat email Anda dengan menekan tombol di bawah ini.</p>
            <div class="button-container">
                <a href="{{ $verifyUrl }}" class="button">Verifikasi Email Sekarang</a>
            </div>
            <p>Jika Anda tidak mendaftar di aplikasi ini, Anda dapat mengabaikan email ini.</p>
            <br>
            <p>Terima kasih,<br>Tim IT Kampus</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Sistem Terintegrasi Kampus. Hak Cipta Dilindungi.</p>
            <p>Jika Anda kesulitan menekan tombol di atas, salin dan tempel URL berikut ke browser Anda:<br>
            <a href="{{ $verifyUrl }}" style="color: #10B981; word-break: break-all;">{{ $verifyUrl }}</a></p>
        </div>
    </div>
</body>
</html>
