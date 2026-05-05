<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: #1a6b3c;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }
        .body {
            padding: 30px;
        }
        .body p {
            color: #555;
            font-size: 15px;
            line-height: 1.6;
        }
        .interview-box {
            background: #f0f8f4;
            border-left: 4px solid #1a6b3c;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .interview-box h2 {
            color: #1a6b3c;
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        .interview-box p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        .info-row {
            margin: 8px 0;
        }
        .info-label {
            font-weight: bold;
            color: #333;
        }
        .info-value {
            color: #555;
        }
        .btn {
            display: block;
            width: 200px;
            margin: 25px auto;
            padding: 14px 0;
            background: #1a6b3c;
            color: #ffffff !important;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }
        .warning {
            background: #fff8e1;
            border-left: 4px solid #ffa000;
            padding: 12px 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
            color: #555;
        }
        .footer {
            background: #f4f4f4;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">

        <div class="header">
            <h1>🎯 Interview Invitation</h1>
        </div>

        <div class="body">
            <p>Dear Candidate,</p>
            <p>You have been invited to complete a <strong>Video Interview</strong> on <strong>Calibehr LMS</strong>. Please find the details below:</p>

            <div class="interview-box">
                <h2>{{ $interview->name }}</h2>
                <p>{{ $interview->description }}</p>
            </div>

            <div class="info-row">
                <span class="info-label">📧 Invite ID:</span>
                <span class="info-value">#{{ $invite->id }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">📅 Expires On:</span>
                <span class="info-value">
                    {{ $invite->expire_on ? date('d-m-Y', strtotime($invite->expire_on)) : 'No expiry' }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">🎥 Format:</span>
                <span class="info-value">Video Response</span>
            </div>

            <div class="warning">
                ⚠️ <strong>Important:</strong> Please ensure you have a working camera and microphone before starting the interview. Use Google Chrome for best experience.
            </div>

            <p>Click the button below to start your interview:</p>

            <a href="{{ config('app.url') }}/interview-take/{{ $invite->unique_id }}" class="btn">
                Start Interview →
            </a>

            <p style="font-size: 13px; color: #999;">
                If the button doesn't work, copy this link:<br>
                <a href="{{ config('app.url') }}/interview-take/{{ $invite->unique_id }}">
                    {{ config('app.url') }}/interview-take/{{ $invite->unique_id }}
                </a>
            </p>
        </div>

        <div class="footer">
            <p>This email was sent by <strong>Calibehr LMS</strong></p>
            <p>© {{ date('Y') }} Calibehr. All rights reserved.</p>
        </div>

    </div>
</body>
</html>
