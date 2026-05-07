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
            background: #1F4E79;
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
            background: #f0f4f8;
            border-left: 4px solid #1F4E79;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .interview-box h2 {
            color: #1F4E79;
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        .interview-box p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        .btn {
            display: block;
            width: 220px;
            margin: 25px auto;
            padding: 14px 0;
            background: #1F4E79;
            color: #ffffff !important;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }
        .footer {
            background: #f4f4f4;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .info-row {
            display: flex;
            margin: 8px 0;
        }
        .info-label {
            font-weight: bold;
            color: #333;
            width: 160px;
        }
        .info-value {
            color: #555;
        }
        .tips-box {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 14px 18px;
            margin: 20px 0;
        }
        .tips-box p {
            margin: 0 0 6px;
            font-size: 13px;
            color: #92400e;
            font-weight: bold;
        }
        .tips-box ul {
            margin: 0;
            padding-left: 18px;
            color: #78350f;
            font-size: 13px;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="container">

        <div class="header">
            <h1>🎥 Interview Invitation</h1>
        </div>

        <div class="body">
            <p>Dear Candidate,</p>
            <p>You have been invited to complete a video interview on <strong>Calibehr LMS</strong>. Please find the details below:</p>

            <div class="interview-box">
                <h2>{{ $interview->name }}</h2>
                @if($interview->description)
                    <p>{{ $interview->description }}</p>
                @endif
            </div>

            @if($interview->time_limit)
            <div class="info-row">
                <span class="info-label">⏱️ Time per Question:</span>
                <span class="info-value">{{ $interview->time_limit }} minutes</span>
            </div>
            @endif

            @if($invite->expire_on)
            <div class="info-row">
                <span class="info-label">📅 Link Expires:</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($invite->expire_on)->format('d M Y') }}</span>
            </div>
            @endif

            <div class="info-row">
                <span class="info-label">📧 Invited Email:</span>
                <span class="info-value">{{ $invite->email }}</span>
            </div>

            <div class="tips-box">
                <p>📌 Before you begin:</p>
                <ul>
                    <li>Use a device with a working camera and microphone</li>
                    <li>Find a quiet, well-lit location</li>
                    <li>Use Chrome or Firefox for best experience</li>
                    <li>Allow camera/microphone access when prompted</li>
                </ul>
            </div>

            <p style="margin-top: 20px;">Click the button below to start your interview:</p>

            <a href="{{ config('app.url') }}/interview-take/{{ $invite->unique_id }}" class="btn">
                Start Interview →
            </a>

            <p style="font-size: 13px; color: #999;">
                If the button doesn't work, copy this link:<br>
                <a href="{{ config('app.url') }}/interview-take/{{ $invite->unique_id }}">
                    {{ config('app.url') }}/interview-take/{{ $invite->unique_id }}
                </a>
            </p>

            <p style="font-size: 13px; color: #aaa; margin-top: 20px;">
                This link is unique to you. Please do not share it with others.
            </p>
        </div>

        <div class="footer">
            <p>This email was sent by <strong>Calibehr LMS</strong></p>
            <p>© {{ date('Y') }} Calibehr. All rights reserved.</p>
        </div>

    </div>
</body>
</html>