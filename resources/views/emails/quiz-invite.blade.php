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
        .quiz-box {
            background: #f0f4f8;
            border-left: 4px solid #1F4E79;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .quiz-box h2 {
            color: #1F4E79;
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        .quiz-box p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        .btn {
            display: block;
            width: 200px;
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
            width: 140px;
        }
        .info-value {
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">

        <div class="header">
            <h1>📝 Quiz Invitation</h1>
        </div>

        <div class="body">
            <p>Dear Candidate,</p>
            <p>You have been invited to take a quiz on <strong>Calibehr LMS</strong>. Please find the details below:</p>

            <div class="quiz-box">
                <h2>{{ $quiz->name }}</h2>
                <p>{{ $quiz->description }}</p>
            </div>

            <div class="info-row">
                <span class="info-label">⏱️ Time Limit:</span>
                <span class="info-value">{{ $quiz->time > 0 ? $quiz->time . ' minutes' : 'No time limit' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">🎯 Passing Score:</span>
                <span class="info-value">{{ $quiz->passing_percentage }}%</span>
            </div>
            <div class="info-row">
                <span class="info-label">🔄 Attempts:</span>
                <span class="info-value">{{ $quiz->number_of_attempt == 0 ? 'Unlimited' : $quiz->number_of_attempt }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">📧 Invite ID:</span>
                <span class="info-value">#{{ $invite->id }}</span>
            </div>

            <p style="margin-top: 20px;">Click the button below to start your quiz:</p>

            <a href="{{ config('app.url') }}/quiz-take/{{ $invite->id }}" class="btn">
                Start Quiz →
            </a>

            <p style="font-size: 13px; color: #999;">
                If the button doesn't work, copy this link:<br>
                <a href="{{ config('app.url') }}/quiz-take/{{ $invite->id }}">
                    {{ config('app.url') }}/quiz-take/{{ $invite->id }}
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