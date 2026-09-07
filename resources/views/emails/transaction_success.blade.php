@extends('emails.layouts.master')

@section('content')
<!-- Success Badge Header -->
<div style="text-align: center; margin-bottom: 24px;">
  <span style="display: inline-block; background-color: #DCFCE7; color: #15803D; font-size: 12px; font-weight: 700; padding: 6px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;">
    &check; Pembayaran Berhasil
  </span>
  <h1 style="font-size: 20px; font-weight: 700; color: #0F172A; margin: 12px 0 6px 0;">
    Terima Kasih, {{ $userName }}!
  </h1>
  <p style="margin: 0; color: #64748B; font-size: 13px;">
    Pembayaran Anda telah kami terima dan lisensi template telah aktif permanen untuk undangan Anda.
  </p>
</div>

<!-- Invoice Receipt Table -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; margin: 20px 0;">
  <tr>
    <td colspan="2" style="padding: 14px 18px; border-bottom: 1px solid #E2E8F0; background-color: #F1F5F9; font-weight: 700; color: #0F172A; font-size: 13px;">
      Rincian Transaksi
    </td>
  </tr>
  <tr>
    <td style="padding: 10px 18px; color: #64748B; font-size: 13px; width: 40%;">Nomor Pesanan</td>
    <td style="padding: 10px 18px; font-weight: 600; color: #0F172A; font-size: 13px; font-family: monospace;">{{ $orderId }}</td>
  </tr>
  <tr>
    <td style="padding: 10px 18px; color: #64748B; font-size: 13px; border-top: 1px solid #EDF2F7;">Judul Undangan</td>
    <td style="padding: 10px 18px; font-weight: 600; color: #0F172A; font-size: 13px; border-top: 1px solid #EDF2F7;">{{ $weddingTitle }}</td>
  </tr>
  <tr>
    <td style="padding: 10px 18px; color: #64748B; font-size: 13px; border-top: 1px solid #EDF2F7;">Tema Template</td>
    <td style="padding: 10px 18px; font-weight: 600; color: #0F172A; font-size: 13px; border-top: 1px solid #EDF2F7;">{{ $templateName }}</td>
  </tr>
  <tr>
    <td style="padding: 10px 18px; color: #64748B; font-size: 13px; border-top: 1px solid #EDF2F7;">Metode Pembayaran</td>
    <td style="padding: 10px 18px; font-weight: 600; color: #0F172A; font-size: 13px; border-top: 1px solid #EDF2F7;">{{ $paymentMethod }}</td>
  </tr>
  <tr>
    <td style="padding: 10px 18px; color: #64748B; font-size: 13px; border-top: 1px solid #EDF2F7;">Waktu Pembayaran</td>
    <td style="padding: 10px 18px; font-weight: 600; color: #0F172A; font-size: 13px; border-top: 1px solid #EDF2F7;">{{ $paidAt }}</td>
  </tr>
  <tr>
    <td style="padding: 14px 18px; color: #0F172A; font-size: 14px; font-weight: 700; border-top: 2px solid #CBD5E1;">Total Pembayaran</td>
    <td style="padding: 14px 18px; font-weight: 700; color: #03AC0E; font-size: 16px; border-top: 2px solid #CBD5E1;">{{ $amountFormatted }}</td>
  </tr>
</table>

<!-- CTA Actions -->
<div style="text-align: center; margin: 28px 0 16px 0;">
  <a href="{{ $editorUrl }}" class="btn-primary" style="background-color: #03AC0E; color: #FFFFFF !important; display: inline-block; padding: 14px 32px; font-weight: 600; font-size: 14px; text-decoration: none; border-radius: 8px;">
    Lanjutkan Edit di Studio Editor &rarr;
  </a>
  <div style="margin-top: 12px;">
    <a href="{{ $transactionsUrl }}" style="font-size: 12px; color: #64748B; text-decoration: underline;">
      Lihat Riwayat Transaksi di Akun Anda
    </a>
  </div>
</div>
@endsection
