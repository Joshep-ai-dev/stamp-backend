<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Kroo verification code</title>
</head>
<body style="margin:0;padding:0;background:#f7f0e4;font-family:Arial,Helvetica,sans-serif;color:#f7f0e4;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f7f0e4;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#002f24;border-radius:18px;overflow:hidden;border:1px solid #bd8058;">
                <tr>
                    <td align="center" style="padding:34px 30px 18px;">
                        <img src="{{ rtrim(config('app.url'), '/') }}/assets/kroo-logo.png" width="190" alt="Kroo" style="display:block;width:190px;max-width:70%;height:auto;border:0;">
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:4px 30px 34px;">
                        <h1 style="margin:0 0 14px;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:1.25;color:#f7f0e4;">Verify your email</h1>
                        <p style="margin:0 auto 24px;max-width:420px;font-size:16px;line-height:1.55;color:#e4d3bd;">Use this verification code to {{ $purpose === 'create-account' ? 'create your Kroo passport' : 'sign in to your Kroo passport' }}.</p>
                        <div style="display:inline-block;padding:16px 24px;border:1px solid #bd8058;border-radius:12px;background:#f7f0e4;color:#002f24;font-family:'Courier New',monospace;font-size:32px;font-weight:700;letter-spacing:8px;">{{ $code }}</div>
                        <p style="margin:24px 0 0;font-size:14px;line-height:1.5;color:#cbb89f;">This code expires in 10 minutes and can only be used once.</p>
                        <p style="margin:8px 0 0;font-size:13px;line-height:1.5;color:#9f8f7c;">If you didn’t request this code, you can safely ignore this email.</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:16px 24px;border-top:1px solid rgba(189,128,88,.45);font-size:12px;color:#9f8f7c;">Kroo · Your travel passport</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
