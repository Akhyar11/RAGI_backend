<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reset Password</title>
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
            background-color: #4F46E5;
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
            background-color: #4F46E5;
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
            <h1>Reset Password</h1>
        </div>
        <div class="content">
            <p>Halo,</p>
            <p>Anda menerima email ini karena kami menerima permintaan reset password untuk akun Anda di Sistem Terintegrasi Kampus.</p>
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="button">Reset Password Sekarang</a>
            </div>
            <p>Link reset password ini akan kedaluwarsa dalam 60 menit.</p>
            <p>Jika Anda tidak merasa meminta reset password, abaikan saja email ini dan akun Anda akan tetap aman.</p>
            <br>
            <p>Terima kasih,<br>Tim IT Kampus</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Sistem Terintegrasi Kampus. Hak Cipta Dilindungi.</p>
            <p>Jika Anda kesulitan menekan tombol di atas, salin dan tempel URL berikut ke browser Anda:<br>
            <a href="{{ $resetUrl }}" style="color: #4F46E5; word-break: break-all;">{{ $resetUrl }}</a></p>
        </div>
    </div>
</body>
</html>
