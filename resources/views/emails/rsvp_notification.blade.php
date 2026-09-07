@extends('emails.layouts.master')

@section('content')
<!-- Header Type Indicator -->
<div style="margin-bottom: 20px;">
  <span style="display: inline-block; background-color: {{ $attending ? '#DCFCE7' : ($type === 'wish' ? '#E0F2FE' : '#FEE2E2') }}; color: {{ $attending ? '#15803D' : ($type === 'wish' ? '#0369A1' : '#B91C1C') }}; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;">
    {{ $type === 'wish' ? 'Ucapan Doa Baru' : ($attending ? 'Konfirmasi: Hadir' : 'Konfirmasi: Tidak Hadir') }}
  </span>
  <h1 style="font-size: 20px; font-weight: 700; color: #0F172A; margin: 12px 0 6px 0;">
    Tamu Mengisi {{ $type === 'wish' ? 'Ucapan Doa' : 'RSVP Kehadiran' }}
  </h1>
  <p style="margin: 0; color: #64748B; font-size: 13px;">
    Undangan Pernikahan: <strong>{{ $weddingTitle }}</strong>
  </p>
</div>

<!-- Details Card -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; margin: 20px 0;">
  <tr>
    <td style="padding: 10px 18px; color: #64748B; font-size: 13px; width: 35%;">Nama Tamu</td>
    <td style="padding: 10px 18px; font-weight: 700; color: #0F172A; font-size: 14px;">{{ $guestName }}</td>
  </tr>
  @if($type === 'rsvp')
  <tr>
    <td style="padding: 10px 18px; color: #64748B; font-size: 13px; border-top: 1px solid #EDF2F7;">Status Kehadiran</td>
    <td style="padding: 10px 18px; font-weight: 600; color: {{ $attending ? '#16A34A' : '#DC2626' }}; font-size: 13px; border-top: 1px solid #EDF2F7;">
      {{ $attending ? '✓ Akan Hadir (' . $attendeeCount . ' Orang)' : '✗ Berhalangan Hadir' }}
    </td>
  </tr>
  @endif
  <tr>
    <td style="padding: 10px 18px; color: #64748B; font-size: 13px; border-top: 1px solid #EDF2F7;">Waktu Kirim</td>
    <td style="padding: 10px 18px; font-weight: 600; color: #0F172A; font-size: 13px; border-top: 1px solid #EDF2F7;">{{ $submittedAt }}</td>
  </tr>
  @if($wishes)
  <tr>
    <td colspan="2" style="padding: 14px 18px; border-top: 1px solid #E2E8F0; background-color: #FFFFFF;">
      <span style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">
        Ucapan &amp; Doa Restu:
      </span>
      <p style="margin: 0; font-style: italic; color: #1E293B; font-size: 13px; line-height: 1.6; background-color: #F8FAFC; padding: 12px; border-radius: 8px; border-left: 3px solid #03AC0E;">
        &ldquo;{{ $wishes }}&rdquo;
      </p>
    </td>
  </tr>
  @endif
</table>

<!-- CTA Button -->
<div style="text-align: center; margin: 28px 0 12px 0;">
  <a href="{{ $guestManagerUrl }}" class="btn-primary" style="background-color: #03AC0E; color: #FFFFFF !important; display: inline-block; padding: 12px 28px; font-weight: 600; font-size: 13px; text-decoration: none; border-radius: 8px;">
    Buka Buku Tamu &amp; Manajemen RSVP &rarr;
  </a>
</div>
@endsection
