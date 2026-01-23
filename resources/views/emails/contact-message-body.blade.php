<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr>
        <td style="padding-bottom:12px; font-size:15px; font-weight:700; color:#0f172a;">
            New message from {{ $name }}
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:8px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>
                    <td style="padding:6px 0; font-size:12px; color:#64748b;">Name</td>
                    <td style="padding:6px 0; font-size:13px; color:#0f172a; font-weight:600;">{{ $name }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0; font-size:12px; color:#64748b;">Email</td>
                    <td style="padding:6px 0; font-size:13px; color:#0f172a; font-weight:600;">
                        @if($email)
                            <a href="mailto:{{ $email }}" style="color:#0ea5e9; text-decoration:none;">{{ $email }}</a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 0; font-size:12px; color:#64748b;">Subject</td>
                    <td style="padding:6px 0; font-size:13px; color:#0f172a; font-weight:600;">{{ $subject ?? 'Contact request' }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding-top:8px; font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.08em;">
            Message
        </td>
    </tr>
    <tr>
        <td style="padding-top:6px; font-size:13px; color:#0f172a; line-height:1.6; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:12px;">
            {!! nl2br(e($messageBody)) !!}
        </td>
    </tr>
</table>
