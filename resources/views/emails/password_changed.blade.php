<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Changed - Qelvuno Recruitment</title>
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
        .alert-box {
            background-color: #fdf0ed;
            border-left: 4px solid #c0392b;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .alert-box strong {
            color: #c0392b;
        }
        .info-box {
            background-color: #f8f8f8;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #c0392b;
        }
        .info-box strong {
            color: #4a4a4a;
        }
        .contact-box {
            background-color: #f8f8f8;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #c0392b;
        }
        .contact-box strong {
            color: #4a4a4a;
        }
        .contact-box a {
            color: #c0392b;
            text-decoration: underline;
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
        ul {
            color: #4a4a4a;
            padding-left: 20px;
        }
        ul li {
            margin: 5px 0;
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
            <div class="sub-title" style="color: #e8e8e8; font-size: 14px; margin-top: 5px; letter-spacing: 2px;">QELVUNO</div>-->
        </div>
        
        <div class="content">
            <h3 style="color: #4a4a4a; margin-top: 0;">Hello {{ $name ?? 'Applicant' }},</h3>
            
            <div class="alert-box" style="background-color: #fdf0ed; border-left: 4px solid #c0392b; padding: 15px; border-radius: 6px; margin: 20px 0;">
                <strong style="color: #c0392b;">⚠️ Security Alert</strong><br>
                Your password was successfully changed on <strong style="color: #4a4a4a;">{{ $changedAt }}</strong>.
            </div>
            
            <p style="color: #4a4a4a;">If you made this change, no further action is needed.</p>
            
            <div class="info-box" style="background-color: #f8f8f8; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #c0392b;">
                <strong style="color: #4a4a4a;">📋 Change Details:</strong><br>
                🕐 Date & Time: {{ $changedAt }}<br>
                🌐 IP Address: {{ $ipAddress }}<br>
                💻 Device: {{ $userAgent }}
            </div>
            
            <p style="color: #4a4a4a;"><strong>If you did NOT make this change:</strong></p>
            <ul style="color: #4a4a4a; padding-left: 20px;">
                <li>Contact our support team immediately</li>
                <li>Reset your password right away using the button below</li>
                <li>Review your account for any suspicious activity</li>
            </ul>
            
            <div style="text-align: center;">
                <?php /*<a href="{{ config('app.frontend_url', config('app.url')) }}/forgot-password" class="button" style="display: inline-block; background-color: #c0392b; color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 6px; margin: 20px 0; font-weight: 600; letter-spacing: 0.5px;">Reset Password Now</a>*/ ?>

                <a href="{{ config('app.frontend_url', config('app.url')) }}/forgot-password"
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
                    Reset Password Now
                </a>
            </div>
            
            <div class="contact-box" style="background-color: #f8f8f8; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #c0392b;">
                <strong style="color: #4a4a4a;">📞 Contact Support Immediately If This Wasn't You:</strong><br>
                📧 Email: 
                <?php /*<a href="mailto:{{ config('support.email') }}" style="color: #c0392b; text-decoration: underline;">{{ config('support.email') }}</a><br>*/ ?>
                <a href="mailto:{{ config('support.email') }}"
                    style="color:#c0392b !important;
                            text-decoration:underline !important;
                            font-weight:normal;">
                        {{ config('support.email') }}
                </a>
                <br>
                📱 Phone: {{ config('support.phone_display') }}<br>
                <strong style="color: #4a4a4a;">Please reference this incident with the time and IP address above.</strong>
            </div>
            
            <hr style="border: none; border-top: 2px solid #c0392b; margin: 25px 0;">
            
            <p style="font-size: 12px; color: #8a8a8a;">This is an automated security notification. If you have questions, please contact Qelvuno support.</p>
            
            <p style="color: #4a4a4a;">Best regards,<br>
            <strong style="color: #c0392b;">Qelvuno Security Team</strong></p>
            <p style="font-size: 12px; color: #8a8a8a; margin-top: 5px;">Qelvuno</p>
        </div>
        
        <div class="footer" style="text-align: center; padding: 20px; font-size: 12px; color: #8a8a8a; background-color: #f5f5f5; border-top: 1px solid #e0e0e0;">
            <p style="margin: 3px 0;">&copy; {{ date('Y') }} Qelvuno</p>
            <p style="margin: 3px 0; font-size: 11px; color: #a0a0a0;">Protecting your account is our priority</p>
        </div>
    </div>
</body>
</html>