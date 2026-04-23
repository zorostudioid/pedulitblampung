<?php
$db_file = __DIR__ . '/skrining.sqlite';
$is_new = !file_exists($db_file);

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($is_new) {
        $db->exec("CREATE TABLE skrining (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT,
            nik TEXT,
            kewarganegaraan TEXT,
            jenis_kelamin TEXT,
            usia INTEGER,
            no_whatsapp TEXT,
            tujuan_lampung TEXT,
            keperluan TEXT,
            lama_tinggal INTEGER,
            batuk_2_minggu TEXT,
            penurunan_bb TEXT,
            keringat_malam TEXT,
            hiv_aids TEXT,
            demam_2_minggu TEXT,
            kontak_tb TEXT,
            diabetes TEXT,
            hasil TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Tabel Settings untuk menyimpan konfigurasi seperti logo
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT
    )");
    
    // Set default logo path jika belum ada
    $stmt = $db->query("SELECT count(*) FROM settings WHERE setting_key='logo_path'");
    if($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('logo_path', 'logo.png')");
    }

    // Default instansi settings
    $instansi_defaults = [
        'instansi_nama'    => 'DINAS KESEHATAN PROVINSI LAMPUNG',
        'instansi_program' => 'Program Pengendalian Tuberkulosis (TB)',
        'instansi_alamat'  => 'Jl. Dr. Susilo No.46, Bandar Lampung &bull; Telp. (0721) 123456',
        'instansi_kota'    => 'Bandar Lampung',
        'petugas_jabatan'  => 'Petugas / Sistem Skrining TB',
        'disclaimer_text'  => 'Surat keterangan ini bukan merupakan diagnosis medis. Hasil skrining ini bersifat awal dan informatif. Untuk diagnosis yang akurat, wajib melakukan pemeriksaan lanjutan oleh tenaga medis profesional di fasilitas kesehatan terdekat.',
    ];
    foreach($instansi_defaults as $key => $val) {
        $chk = $db->prepare("SELECT count(*) FROM settings WHERE setting_key=?");
        $chk->execute([$key]);
        if($chk->fetchColumn() == 0) {
            $ins = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?)");
            $ins->execute([$key, $val]);
        }
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
