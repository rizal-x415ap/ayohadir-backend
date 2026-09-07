@extends('emails.layouts.master')

@section('content')
<h1 style="font-size: 20px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">
  Permintaan Penggantian Kata Sandi
</h1>

<p style="margin: 0 0 16px 0; color: #475569; line-height: 1.6;">
  Halo <strong>{{ $name }}</strong>, kami menerima permintaan untuk mengatur ulang kata sandi akun Ayo Hadir Anda. Silakan masukkan kode verifikasi OTP berikut pada formulir verifikasi:
</p>

<!-- OTP Highlight Box -->
<div style="background-color: #F8FAFC; border: 2px dashed #03AC0E; border-radius: 12px; padding: 24px 16px; text-align: center; margin: 24px 0;">
  <span style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 6px;">
    KODE VERIFIKASI OTP ANDA
  </span>
  <div style="font-size: 34px; font-weight: 800; color: #03AC0E; letter-spacing: 8px; font-family: monospace;">
    {{ $otp }}
  </div>
  <span style="font-size: 12px; color: #E11D48; font-weight: 600; display: block; margin-top: 8px;">
    &bull; Kode ini berlaku selama {{ $expiryMinutes }} menit
  </span>
</div>

<div style="background-color: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 12px 16px; margin: 20px 0; font-size: 12px; color: #92400E; line-height: 1.5;">
  <strong>Penting:</strong> Jangan pernah membagikan kode OTP ini kepada siapa pun, termasuk staf Ayo Hadir. Jika Anda tidak merasa meminta perubahan kata sandi, akun Anda tetap aman dan Anda dapat mengabaikan email ini.
</div>

<p style="margin: 24px 0 0 0; color: #64748B; font-size: 13px; line-height: 1.6;">
  Salam hormat,<br>
  <strong style="color: #1E293B;">Tim Keamanan Ayo Hadir</strong>
</p>
@endsection
