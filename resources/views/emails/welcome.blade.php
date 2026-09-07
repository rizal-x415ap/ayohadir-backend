@extends('emails.layouts.master')

@section('content')
<h1 style="font-size: 20px; font-weight: 700; color: #0F172A; margin: 0 0 16px 0; line-height: 1.3;">
  Selamat Datang di Ayo Hadir, {{ $name }}! 🎉
</h1>

<p style="margin: 0 0 16px 0; color: #475569; line-height: 1.6;">
  Terima kasih telah bergabung dengan <strong>Ayo Hadir</strong>. Kami sangat senang dapat menjadi bagian dari momen bahagia pernikahan Anda. Akun Anda dengan email <strong>{{ $email }}</strong> kini telah aktif dan siap digunakan.
</p>

<!-- 3 Steps Feature Box -->
<div style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin: 24px 0;">
  <p style="margin: 0 0 14px 0; font-size: 13px; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.5px;">
    3 Langkah Mudah Memulai Undangan Anda:
  </p>

  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
      <td style="vertical-align: top; width: 28px; padding-bottom: 12px;">
        <span style="display: inline-block; width: 22px; height: 22px; background-color: #DCFCE7; color: #15803D; font-weight: 700; border-radius: 50%; text-align: center; line-height: 22px; font-size: 12px;">1</span>
      </td>
      <td style="vertical-align: top; padding-bottom: 12px; padding-left: 8px;">
        <strong style="color: #1E293B;">Pilih Desain Tema Impian:</strong>
        <div style="font-size: 13px; color: #64748B; margin-top: 2px;">Jelajahi berbagai tema terkurasi (Minimalist, Botanical, Luxury, Modern, Traditional).</div>
      </td>
    </tr>
    <tr>
      <td style="vertical-align: top; width: 28px; padding-bottom: 12px;">
        <span style="display: inline-block; width: 22px; height: 22px; background-color: #DCFCE7; color: #15803D; font-weight: 700; border-radius: 50%; text-align: center; line-height: 22px; font-size: 12px;">2</span>
      </td>
      <td style="vertical-align: top; padding-bottom: 12px; padding-left: 8px;">
        <strong style="color: #1E293B;">Personalisasi di Visual Studio:</strong>
        <div style="font-size: 13px; color: #64748B; margin-top: 2px;">Cukup klik teks atau foto langsung di kanvas untuk mengisi data pengantin, tanggal acara, cerita cinta, galeri, dan musik latar.</div>
      </td>
    </tr>
    <tr>
      <td style="vertical-align: top; width: 28px;">
        <span style="display: inline-block; width: 22px; height: 22px; background-color: #DCFCE7; color: #15803D; font-weight: 700; border-radius: 50%; text-align: center; line-height: 22px; font-size: 12px;">3</span>
      </td>
      <td style="vertical-align: top; padding-left: 8px;">
        <strong style="color: #1E293B;">Publikasikan &amp; Pantau RSVP:</strong>
        <div style="font-size: 13px; color: #64748B; margin-top: 2px;">Sebarkan tautan undangan digital Anda ke keluarga serta sahabat, dan pantau konfirmasi kehadiran mereka secara real-time.</div>
      </td>
    </tr>
  </table>
</div>

<!-- CTA Button -->
<div style="text-align: center; margin: 32px 0;">
  <a href="{{ $dashboardUrl }}" class="btn-primary" style="background-color: #03AC0E; color: #FFFFFF !important; display: inline-block; padding: 14px 32px; font-weight: 600; font-size: 14px; text-decoration: none; border-radius: 8px;">
    Mulai Buat Undangan Sekarang &rarr;
  </a>
</div>

<p style="margin: 24px 0 0 0; color: #64748B; font-size: 13px; line-height: 1.6;">
  Salam hangat,<br>
  <strong style="color: #1E293B;">Tim Ayo Hadir</strong>
</p>
@endsection
