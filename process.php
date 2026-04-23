<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect Data
    $nama = $_POST['nama'] ?? '';
    $nik = preg_replace('/\D/', '', $_POST['nik'] ?? ''); // angka saja
    $kewarganegaraan = $_POST['kewarganegaraan'] ?? '';
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $usia = (int)($_POST['usia'] ?? 0);

    // Normalisasi nomor WA → format 62xxx untuk Fonnte
    $no_whatsapp = preg_replace('/\D/', '', $_POST['no_whatsapp'] ?? '');
    if (substr($no_whatsapp, 0, 2) === '62') {
        // sudah benar
    } elseif (substr($no_whatsapp, 0, 1) === '0') {
        $no_whatsapp = '62' . substr($no_whatsapp, 1);
    } else {
        $no_whatsapp = '62' . $no_whatsapp;
    }

    $tujuan_lampung = $_POST['tujuan_lampung'] ?? '';
    $keperluan = $_POST['keperluan'] ?? '';
    $lama_tinggal = (int)($_POST['lama_tinggal'] ?? 0);
    
    // Gejala Utama (Golden Questions)
    $batuk_2_minggu = $_POST['batuk_2_minggu'] ?? 'Tidak';
    $penurunan_bb = $_POST['penurunan_bb'] ?? 'Tidak';
    $keringat_malam = $_POST['keringat_malam'] ?? 'Tidak';
    $hiv_aids = $_POST['hiv_aids'] ?? 'Tidak';
    
    // Gejala Tambahan
    $demam_2_minggu = $_POST['demam_2_minggu'] ?? 'Tidak';
    $kontak_tb = $_POST['kontak_tb'] ?? 'Tidak';
    $diabetes = $_POST['diabetes'] ?? 'Tidak';
    
    // Pertanyaan Emas Baru
    $anggota_keluarga_sakit = $_POST['anggota_keluarga_sakit'] ?? 'Tidak';

    // Logika Skrining TB
    $is_risk = false;
    if ($batuk_2_minggu === 'Ya' || $penurunan_bb === 'Ya' || $keringat_malam === 'Ya' || $hiv_aids === 'Ya' || $anggota_keluarga_sakit === 'Ya') {
        $is_risk = true;
    }

    $hasil = $is_risk ? 'Rekomendasi periksakan diri ke RSUD/Puskesmas terdekat' : 'Bukan TB';

    // Tambahkan kolom jika belum ada (SQLite ALTER TABLE)
    try { $db->exec("ALTER TABLE skrining ADD COLUMN anggota_keluarga_sakit TEXT DEFAULT 'Tidak'"); } catch(Exception $e) {}

    try {
        $stmt = $db->prepare("INSERT INTO skrining 
            (nama, nik, kewarganegaraan, jenis_kelamin, usia, no_whatsapp, tujuan_lampung, keperluan, lama_tinggal, 
            batuk_2_minggu, penurunan_bb, keringat_malam, hiv_aids, demam_2_minggu, kontak_tb, diabetes, anggota_keluarga_sakit, hasil) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $nama, $nik, $kewarganegaraan, $jenis_kelamin, $usia, $no_whatsapp, $tujuan_lampung, $keperluan, $lama_tinggal,
            $batuk_2_minggu, $penurunan_bb, $keringat_malam, $hiv_aids, $demam_2_minggu, $kontak_tb, $diabetes, $anggota_keluarga_sakit, $hasil
        ]);

        
        // Load instansi settings untuk pesan WA
        $stmt_inst = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('instansi_nama','instansi_kota')");
        $inst_cfg = [];
        foreach ($stmt_inst->fetchAll(PDO::FETCH_ASSOC) as $r) $inst_cfg[$r['setting_key']] = $r['setting_value'];
        $instansi_nama = $inst_cfg['instansi_nama'] ?? 'Dinas Kesehatan Provinsi Lampung';
        $instansi_kota = $inst_cfg['instansi_kota'] ?? 'Bandar Lampung';

        // Nomor dokumen unik
        $last_id = $db->lastInsertId();
        $cert_id = 'SKR-TB-' . date('Ymd') . '-' . strtoupper(substr(md5($last_id), 0, 8));
        $tgl_skrining = date('d/m/Y H:i');

        // Separator
        $sep = "━━━━━━━━━━━━━━━━━━━━━━\n";

        $pesan_wa  = "🏥 *{$instansi_nama}*\n";
        $pesan_wa .= $sep;
        $pesan_wa .= "Halo *{$nama}*! 👋\n";
        $pesan_wa .= "Terima kasih telah menyelesaikan Skrining Tuberkulosis (TB) digital.\n\n";

        $pesan_wa .= "📋 *DATA SKRINING*\n";
        $pesan_wa .= $sep;
        $pesan_wa .= "👤 Nama     : {$nama}\n";
        $pesan_wa .= "🪪 NIK      : {$nik}\n";
        $pesan_wa .= "🎂 Usia/JK  : {$usia} thn / {$jenis_kelamin}\n";
        $pesan_wa .= "📍 Tujuan   : {$tujuan_lampung}\n";
        $pesan_wa .= "🗓️ Keperluan: {$keperluan} ({$lama_tinggal} hari)\n";
        $pesan_wa .= "🕐 Tanggal  : {$tgl_skrining}\n\n";

        $pesan_wa .= "🔬 *HASIL SKRINING*\n";
        $pesan_wa .= $sep;

        if ($is_risk) {
            $pesan_wa .= "⚠️ *REKOMENDASI PEMERIKSAAN*\n\n";
            $pesan_wa .= "Berdasarkan hasil skrining, ditemukan *gejala yang mengindikasikan risiko Tuberkulosis*.\n\n";
            $pesan_wa .= "✅ *Langkah selanjutnya:*\n";
            $pesan_wa .= "Segera kunjungi *RSUD / Puskesmas terdekat* di {$instansi_kota} untuk pemeriksaan lebih lanjut. Bawa surat keterangan ini sebagai referensi.\n";
        } else {
            $pesan_wa .= "✅ *BUKAN TB — AMAN*\n\n";
            $pesan_wa .= "Tidak ditemukan gejala khas Tuberkulosis pada skrining Anda. Tetap jaga kesehatan dan selamat menikmati kegiatan di Lampung! 🌿\n";
        }

        $pesan_wa .= "\n📄 *SERTIFIKAT DIGITAL*\n";
        $pesan_wa .= $sep;
        $pesan_wa .= "📌 No. Dokumen : *{$cert_id}*\n\n";
        $pesan_wa .= "Untuk melihat & mencetak sertifikat skrining Anda:\n";
        $pesan_wa .= "1️⃣ Buka tautan berikut:\n";
        $pesan_wa .= "   👉 https://peduli-tb-lampung.web.id/cek-hasil.php\n";
        $pesan_wa .= "2️⃣ Masukkan NIK: *{$nik}*\n";
        $pesan_wa .= "3️⃣ Klik *\"Cari Data Saya\"* lalu *\"Cetak / Unduh PDF\"*\n\n";

        $pesan_wa .= $sep;
        $pesan_wa .= "_Pesan ini dikirim otomatis oleh Sistem Skrining TB Digital_\n";
        $pesan_wa .= "_{$instansi_nama}_";


        // Kirim WA via Fonnte jika token sudah diisi
        $stmt_token = $db->query("SELECT setting_value FROM settings WHERE setting_key='fonnte_token'");
        $fonnte_token = $stmt_token->fetchColumn() ?: '';
        $wa_response = null;
        if(!empty($fonnte_token)) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://api.fonnte.com/send',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => array(
                'target' => $no_whatsapp,
                'message' => $pesan_wa,
                'countryCode' => '62',
              ),
              CURLOPT_HTTPHEADER => array(
                "Authorization: $fonnte_token"
              ),
            ));
    
            $response = curl_exec($curl);
            curl_close($curl);
            $wa_response = json_decode($response);
        }

        echo json_encode([
            'status' => 'success', 
            'message' => 'Data berhasil disimpan',
            'hasil' => $hasil,
            'is_risk' => $is_risk,
            'wa_response' => $wa_response
        ]);

    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
