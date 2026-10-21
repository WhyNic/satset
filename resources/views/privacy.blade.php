<x-layouts.customer title="Kebijakan privasi | SatSet">
    <section class="content-shell" style="max-width:680px;margin:0 auto;padding:24px 16px;display:grid;gap:18px;">
        <a class="text-link" href="{{ route('home') }}">&larr; Beranda</a>
        <div>
            <p class="eyebrow">KEBIJAKAN PRIVASI</p>
            <h1 style="margin:4px 0 0;font-size:clamp(24px,6vw,30px);line-height:1.15;">Data yang kami pakai untuk antrean Anda</h1>
        </div>

        <div style="display:grid;gap:14px;line-height:1.6;">
            <p><strong>Data yang kami simpan.</strong> Saat Anda mengambil antrean, kami menyimpan email, jumlah tamu (untuk antrean meja), nomor antrean, dan status antrean Anda.</p>
            <p><strong>Untuk apa.</strong> Email hanya dipakai untuk pemberitahuan antrean di outlet tempat Anda mendaftar: konfirmasi, posisi antrean saat mendekati giliran, saat dipanggil, dan saat dibatalkan.</p>
            <p><strong>Verifikasi.</strong> Pemberitahuan baru dikirim setelah Anda mengklik tautan verifikasi di email pertama. Ini memastikan tidak ada orang yang mendaftarkan email orang lain.</p>
            <p><strong>Berapa lama.</strong> Email dihapus dari sistem saat antrean selesai, dilewati, atau dibatalkan. Tidak ada data yang dijual atau dipakai untuk iklan.</p>
            <p><strong>Siapa yang bisa melihat.</strong> Staf outlet melihat jumlah tamu dan email yang disamarkan (misalnya <code>t***@contoh.com</code>). Email lengkap tidak tampil di halaman publik.</p>
            <p><strong>Hak Anda.</strong> Untuk meminta penghapusan data lebih cepat, hubungi staf outlet tempat Anda mengambil antrean.</p>
            <p><strong>Keamanan.</strong> Tautan verifikasi hanya menyimpan hash, bukan token aslinya. Satu email hanya bisa punya 3 antrean aktif per outlet, dan email tidak pernah ditampilkan utuh di halaman publik.</p>
        </div>
    </section>
</x-layouts.customer>
