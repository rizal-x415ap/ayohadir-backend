<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <title>{{ $subject ?? 'Notifikasi Ayo Hadir' }}</title>
  <!--[if mso]>
  <noscript>
    <xml>
      <o:OfficeDocumentSettings>
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
  </noscript>
  <![endif]-->
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #F4F6F8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
    .email-container { max-width: 600px; margin: 0 auto; width: 100%; }
    .email-card { background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04); }
    .btn-primary { background-color: #03AC0E; color: #FFFFFF !important; display: inline-block; padding: 12px 28px; font-weight: 600; font-size: 14px; text-decoration: none; border-radius: 8px; text-align: center; }
    .btn-primary:hover { background-color: #028A0B; }
    @media screen and (max-width: 600px) {
      .email-content { padding: 24px 16px !important; }
      .header-padding { padding: 20px 16px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 24px 12px; background-color: #F4F6F8;">
  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
      <td align="center">
        <table role="presentation" class="email-container" border="0" cellpadding="0" cellspacing="0">
          <!-- Top Accent Line -->
          <tr>
            <td style="height: 4px; background: linear-gradient(90deg, #028A0B 0%, #03AC0E 100%); border-radius: 16px 16px 0 0;"></td>
          </tr>

          <!-- Main Email Card -->
          <tr>
            <td class="email-card">
              <!-- Header with Logo & Brand -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="header-padding" style="padding: 28px 32px 20px 32px; border-bottom: 1px solid #F1F5F9; background-color: #FAFCFA;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="vertical-align: middle; padding-right: 12px;">
                          <!-- Logo SVG / PNG Asset -->
                          <img src="{{ config('app.url') }}/images/icon-ayohadir.svg" alt="Ayo Hadir Logo" width="38" height="29" style="display: block; width: 38px; height: auto;" />
                        </td>
                        <td style="vertical-align: middle;">
                          <span style="font-size: 18px; font-weight: 700; color: #0F172A; letter-spacing: -0.3px; display: block; line-height: 1.2;">Ayo Hadir</span>
                          <span style="font-size: 11px; font-weight: 500; color: #64748B; display: block; line-height: 1.2; margin-top: 2px;">Platform Undangan Digital</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Main Content Area -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="email-content" style="padding: 32px; color: #334155; font-size: 14px; line-height: 1.6;">
                    @yield('content')
                  </td>
                </tr>
              </table>

              <!-- Footer Security & Support Info -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td style="padding: 24px 32px; background-color: #F8FAFC; border-top: 1px solid #F1F5F9; font-size: 11px; color: #64748B; line-height: 1.6;">
                    <p style="margin: 0 0 8px 0; font-weight: 600; color: #475569;">Ayo Hadir — Platform Undangan Digital &amp; Visual Wedding Builder</p>
                    <p style="margin: 0 0 8px 0;">Email ini dikirimkan secara otomatis dari server resmi <strong>inv.ayohadir.id</strong>. Mohon tidak membalas langsung email ini.</p>
                    <p style="margin: 0 0 4px 0;">Pertanyaan atau kendala? Hubungi tim bantuan kami melalui <a href="mailto:support@inv.ayohadir.id" style="color: #03AC0E; text-decoration: underline;">support@inv.ayohadir.id</a></p>
                    <p style="margin: 12px 0 0 0; color: #94A3B8; font-size: 10px;">&copy; {{ date('Y') }} Ayo Hadir. Hak cipta dilindungi undang-undang.</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
