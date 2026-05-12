<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Interview Invitation — Calibehr LMS</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;padding:40px 16px;">
  <tr><td align="center">

    <!-- Brand -->
    <table width="580" cellpadding="0" cellspacing="0" border="0" style="max-width:580px;">
      <tr><td align="center" style="padding-bottom:20px;">
        <div style="font-size:15px;font-weight:700;color:#10b981;letter-spacing:0.04em;text-transform:uppercase;font-family:Arial,sans-serif;">Calibehr Learning</div>
        <div style="font-size:12px;color:#9ca3af;margin-top:4px;font-family:Arial,sans-serif;">Learning Management System</div>
      </td></tr>
    </table>

    <!-- Card -->
    <table width="580" cellpadding="0" cellspacing="0" border="0" style="max-width:580px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);">

      <!-- Hero -->
      <tr><td align="center" bgcolor="#065f46" style="padding:40px 32px 32px;background-color:#065f46;">
        <div style="font-size:36px;margin-bottom:12px;">🎥</div>
        <h1 style="color:#ffffff;font-size:22px;font-weight:700;margin:0 0 6px;font-family:Arial,sans-serif;">Interview Invitation</h1>
        <p style="color:#a7f3d0;font-size:14px;margin:0;font-family:Arial,sans-serif;">You have been selected for a video interview</p>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:32px;">

        <!-- Greeting -->
        <p style="font-size:15px;color:#374151;line-height:1.7;margin:0 0 20px;font-family:Arial,sans-serif;">
          Dear Candidate,<br><br>
          You have been invited to complete a <strong>video interview</strong> on <strong>Calibehr LMS</strong>. Please find the details below:
        </p>

        <!-- Interview Name Card -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;margin-bottom:24px;">
          <tr><td style="padding:18px 22px;">
            <div style="font-size:18px;font-weight:700;color:#065f46;margin-bottom:4px;font-family:Arial,sans-serif;">{{ $interview->name }}</div>
            @if($interview->description)
            <div style="font-size:13px;color:#6b7280;line-height:1.5;font-family:Arial,sans-serif;">{{ $interview->description }}</div>
            @endif
          </td></tr>
        </table>

        <!-- Info Rows -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb;border-radius:10px;margin-bottom:24px;">
          <tr><td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;">
            <span style="font-size:13px;color:#6b7280;font-family:Arial,sans-serif;">📧 &nbsp;Invited Email: &nbsp;</span>
            <span style="font-size:13px;color:#111827;font-weight:600;font-family:Arial,sans-serif;">{{ $invite->email }}</span>
          </td></tr>
          @if($invite->expire_on)
          <tr><td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;">
            <span style="font-size:13px;color:#6b7280;font-family:Arial,sans-serif;">📅 &nbsp;Link Expires: &nbsp;</span>
            <span style="font-size:13px;color:#111827;font-weight:600;font-family:Arial,sans-serif;">{{ \Carbon\Carbon::parse($invite->expire_on)->format('d M Y') }}</span>
          </td></tr>
          @endif
          @if($interview->time_limit)
          <tr><td style="padding:12px 16px;">
            <span style="font-size:13px;color:#6b7280;font-family:Arial,sans-serif;">⏱️ &nbsp;Time per Question: &nbsp;</span>
            <span style="font-size:13px;color:#111827;font-weight:600;font-family:Arial,sans-serif;">{{ $interview->time_limit }} minutes</span>
          </td></tr>
          @endif
        </table>

        <!-- Tips -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;margin-bottom:28px;">
          <tr><td style="padding:16px 20px;">
            <div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:10px;font-family:Arial,sans-serif;">📌 Before you begin</div>
            <ul style="margin:0;padding-left:18px;color:#78350f;font-size:13px;line-height:1.9;font-family:Arial,sans-serif;">
              <li>Use a device with a working camera and microphone</li>
              <li>Find a quiet, well-lit location</li>
              <li>Use Chrome or Firefox for best experience</li>
              <li>Allow camera/microphone access when prompted</li>
              <li>Ensure a stable internet connection</li>
            </ul>
          </td></tr>
        </table>

        <!-- CTA Button -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
          <tr><td align="center" style="padding:8px 0;">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#059669" style="border-radius:12px;box-shadow:0 4px 0 #047857;">
                  <a href="{{ config('app.url') }}/interview-take/{{ $invite->unique_id }}"
                     style="display:inline-block;background-color:#059669;color:#ffffff;text-decoration:none;padding:16px 52px;border-radius:12px;font-size:16px;font-weight:700;font-family:Arial,sans-serif;letter-spacing:0.01em;border-bottom:4px solid #047857;">
                    🎥 &nbsp; Start Interview &nbsp; →
                  </a>
                </td>
              </tr>
            </table>
          </td></tr>
        </table>

        <!-- Fallback -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb;border-radius:8px;margin-bottom:20px;">
          <tr><td style="padding:12px 16px;">
            <p style="font-size:12px;color:#6b7280;margin:0;line-height:1.6;font-family:Arial,sans-serif;">
              If the button doesn't work, copy this link:<br>
              <a href="{{ config('app.url') }}/interview-take/{{ $invite->unique_id }}" style="color:#059669;word-break:break-all;font-family:Arial,sans-serif;">
                {{ config('app.url') }}/interview-take/{{ $invite->unique_id }}
              </a>
            </p>
          </td></tr>
        </table>

        <p style="text-align:center;font-size:12px;color:#9ca3af;margin:0;font-family:Arial,sans-serif;">🔒 This link is unique to you. Please do not share it with others.</p>

      </td></tr>

      <!-- Footer -->
      <tr><td align="center" style="padding:20px 32px;border-top:1px solid #f3f4f6;">
        <p style="font-size:12px;color:#9ca3af;margin:0;font-family:Arial,sans-serif;">This email was sent by <strong style="color:#6b7280;">Calibehr LMS</strong></p>
        <p style="font-size:12px;color:#9ca3af;margin:4px 0 0;font-family:Arial,sans-serif;">© {{ date('Y') }} Calibehr. All rights reserved.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>