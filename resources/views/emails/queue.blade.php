<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f4;font-family:Arial,Helvetica,sans-serif;color:#1c1917;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f4;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:12px;padding:28px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 4px;font-size:12px;letter-spacing:.08em;color:#57534e;">SATSET</p>
                            <h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;">{{ $heading }}</h1>
                            @foreach ($lines as $line)
                                <p style="margin:0 0 10px;font-size:15px;line-height:1.5;">{{ $line }}</p>
                            @endforeach
                            <p style="margin:24px 0 0;">
                                <a href="{{ $url }}" style="display:inline-block;background:#15803d;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">Lihat antrean Anda</a>
                            </p>
                            <p style="margin:24px 0 0;font-size:12px;line-height:1.5;color:#57534e;">Email ini dikirim karena Anda mengambil antrean di SatSet. Nomor dan email Anda dihapus dari sistem setelah antrean selesai, dibatalkan, atau dilewati.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
