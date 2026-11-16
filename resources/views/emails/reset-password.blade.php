<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi SIK</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0"
       style="background:#f1f5f9; padding:35px 15px;">
    <tr>
        <td align="center">

            <table width="600" cellpadding="0" cellspacing="0"
                   style="max-width:600px; width:100%; background:#ffffff;
                   border-radius:12px; overflow:hidden;">

                <!-- Header -->
                <tr>
                    <td align="center"
                        style="background:#123b70; padding:30px 20px; color:#ffffff;">

                        <div style="font-size:28px; font-weight:bold;
                                    letter-spacing:2px;">
                            SIK
                        </div>

                        <div style="font-size:12px; margin-top:8px;
                                    color:#dbeafe;">
                            SISTEM INFORMASI KEHADIRAN
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style="padding:35px 30px; color:#334155;">

                        <h2 style="color:#123b70; font-size:23px;
                                   margin-top:0;">
                            Permintaan Reset Kata Sandi
                        </h2>

                        <p style="font-size:14px; line-height:1.8;">
                            Halo,
                        </p>

                        <p style="font-size:14px; line-height:1.8;">
                            Kami menerima permintaan untuk mengatur ulang
                            kata sandi akun SIK Anda. Silakan klik tombol
                            di bawah ini untuk membuat kata sandi baru.
                        </p>

                        <div style="text-align:center; margin:30px 0;">
                            <a href="{{ $url }}"
                               style="display:inline-block;
                               background:#2563eb;
                               color:#ffffff;
                               text-decoration:none;
                               padding:14px 30px;
                               border-radius:7px;
                               font-size:14px;
                               font-weight:bold;">
                                Reset Kata Sandi
                            </a>
                        </div>

                        <p style="font-size:13px; line-height:1.8;">
                            Tautan ini berlaku selama
                            {{ $expire }} menit.
                            Demi keamanan akun Anda, jangan bagikan
                            tautan ini kepada siapa pun.
                        </p>

                        <p style="font-size:13px; line-height:1.8;">
                            Jika Anda tidak meminta pengaturan ulang
                            kata sandi, abaikan email ini. Akun Anda
                            tetap aman.
                        </p>

                        <p style="font-size:14px; margin-top:30px;">
                            Salam hormat,<br>
                            <strong style="color:#123b70;">
                                Tim Sistem Informasi Kehadiran (SIK)
                            </strong>
                        </p>

                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align="center"
                        style="background:#f8fafc; padding:20px;
                        border-top:1px solid #e2e8f0;">

                        <p style="font-size:11px; color:#64748b;
                                  line-height:1.7; margin:0;">
                            Email ini dikirim secara otomatis oleh sistem SIK.
                            Mohon tidak membalas email ini.
                            <br>
                            © {{ date('Y') }} Sistem Informasi Kehadiran.
                            Semua hak dilindungi.
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>