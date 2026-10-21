SATSET

{{ $heading }}

@foreach ($lines as $line)
{{ $line }}
@endforeach

@if ($verifyUrl)
Verifikasi email Anda (wajib agar pemberitahuan dikirim): {{ $verifyUrl }}

@endif
Lihat antrean Anda: {{ $url }}

Email ini dikirim karena Anda mengambil antrean di SatSet. Nomor dan email Anda dihapus dari sistem setelah antrean selesai, dibatalkan, atau dilewati.
