<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #4a4a4a;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #d4d4d4;
        }
        .header {
            background-color: #6b6b6b;
            padding: 20px 20px 15px 20px;
            text-align: center;
            border-bottom: 4px solid #c0392b;
        }
        .header .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 5px;
        }
        .header .sub-title {
            color: #e8e8e8;
            font-size: 14px;
            margin-top: 5px;
            letter-spacing: 2px;
        }
        .content {
            padding: 30px;
        }
        .content h3 {
            color: #4a4a4a;
            margin-top: 0;
        }
        .info-note {
            background-color: #f8f8f8;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #c0392b;
            font-size: 14px;
        }
        .info-note strong {
            color: #4a4a4a;
        }
        .button {
            display: inline-block;
            background-color: #c0392b;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 30px;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: opacity 0.3s;
        }
        .button:hover {
            opacity: 0.85;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #8a8a8a;
            background-color: #f5f5f5;
            border-top: 1px solid #e0e0e0;
        }
        .footer p {
            margin: 3px 0;
        }
        hr {
            border: none;
            border-top: 2px solid #c0392b;
            margin: 25px 0;
        }
        .accent-red {
            color: #c0392b;
        }
        @media only screen and (max-width: 600px) {
            .content { padding: 20px; }
            .button { display: block; text-align: center; }
            .header .logo { max-width: 90px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!--<img src="{{ asset('assets/images/Qelvuno Official Logos/1- Qelvuno_Official-Logo.jpg') }}" alt="Qelvuno Logo" class="logo" style="max-width: 120px; height: auto; margin-bottom: 5px;">-->
            {{-- 
                Embed the logo directly into the email as an inline attachment (CID).
                This prevents issues where email clients cannot load images from asset() URLs.
            --}}
            <!--<img src="{{ $message->embed(public_path('assets/images/Qelvuno Official Logos/1- Qelvuno_Official-Logo.jpg')) }}"
                alt="Qelvuno Logo"
                class="logo"
                style="max-width: 120px; height: auto; margin-bottom: 5px; border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic;">-->
            <div class="sub-title" style="color: #e8e8e8; font-size: 14px; margin-top: 5px; letter-spacing: 2px;">QELVUNO</div>
        </div>
        
        <div class="content">
            <h3 style="color: #4a4a4a; margin-top: 0;">Hello {{ $name ?? 'Applicant' }},</h3>
            
            <p style="color: #4a4a4a;">We received a request to reset your password for your Qelvuno Recruitment Portal account.</p>
            
            <div style="text-align: center;">
                <?php /*<a href="{{ $resetUrl }}" class="button" style="display: inline-block; background-color: #c0392b; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 6px; margin: 20px 0; font-weight: 600; letter-spacing: 0.5px;">Reset Your Password</a>*/ ?>

                <a href="{{ $resetUrl }}"
                    class="button"
                    style="
                        display:inline-block;
                        background-color:#c0392b;
                        color:#ffffff !important;
                        text-decoration:none !important;
                        padding:14px 30px;
                        border-radius:6px;
                        margin:20px 0;
                        font-weight:600;
                        letter-spacing:0.5px;
                ">
                    Reset Your Password
                </a>
            </div>
            
            <div class="info-note" style="background-color: #f8f8f8; padding: 15px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #c0392b; font-size: 14px;">
                <p style="margin: 0; color: #4a4a4a;">⏱️ This password reset link will expire in <strong style="color: #4a4a4a;">60 minutes</strong>.</p>
            </div>
            
            <p style="color: #4a4a4a;">If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
            
            <hr style="border: none; border-top: 2px solid #c0392b; margin: 25px 0;">
            
            <p style="font-size: 12px; color: #8a8a8a;">If the button doesn't work, copy and paste this link into your browser:</p>
            <?php /*<p style="font-size: 12px; word-break: break-all; color: #8a8a8a;">{{ $resetUrl }}</p>*/ ?>
            <p style="
                font-size:12px;
                word-break:break-all;
                color:#8a8a8a !important;
            ">
                <span style="color:#8a8a8a !important;">
                    {{ $resetUrl }}
                </span>
            </p>
            
            <p style="color: #4a4a4a;">Best regards,<br>
            <strong style="color: #c0392b;">Qelvuno Recruitment Team</strong></p>
            <p style="font-size: 12px; color: #8a8a8a; margin-top: 5px;">Qelvuno</p>
        </div>
        
        <div class="footer" style="text-align: center; padding: 20px; font-size: 12px; color: #8a8a8a; background-color: #f5f5f5; border-top: 1px solid #e0e0e0;">
            <p style="margin: 3px 0;">&copy; {{ date('Y') }} Qelvuno. All rights reserved.</p>
            <p style="margin: 3px 0; font-size: 11px; color: #a0a0a0;">This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>