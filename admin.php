<?php
session_start();
if(!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

require_once 'db.php';

$success_msg = '';
$error_msg = '';

// Handle Logo Upload
// Handle Logo 1 Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file_tmp = $_FILES['logo']['tmp_name'];
    $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if ($file_tmp && in_array($file_ext, ['jpg','jpeg','png','gif','webp','svg'])) {
        $new_filename = 'logo_' . time() . '.' . $file_ext;
        if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'logo_path'");
            $stmt->execute(['uploads/' . $new_filename]);
            $success_msg = "Logo 1 berhasil diperbarui!";
        } else { $error_msg = "Gagal menyimpan file logo."; }
    } else { $error_msg = "Format file tidak valid. Gunakan JPG, PNG, GIF, WEBP, atau SVG."; }
}

// Handle Logo 2 Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo2'])) {
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file_tmp = $_FILES['logo2']['tmp_name'];
    $file_ext = strtolower(pathinfo($_FILES['logo2']['name'], PATHINFO_EXTENSION));
    if ($file_tmp && in_array($file_ext, ['jpg','jpeg','png','gif','webp','svg'])) {
        $new_filename = 'logo2_' . time() . '.' . $file_ext;
        if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
            $chk2 = $db->query("SELECT count(*) FROM settings WHERE setting_key='logo2_path'");
            if ($chk2->fetchColumn() > 0) {
                $s2 = $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='logo2_path'");
            } else {
                $s2 = $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('logo2_path',?)");
            }
            $s2->execute(['uploads/' . $new_filename]);
            $success_msg = "Logo 2 berhasil diperbarui!";
        } else { $error_msg = "Gagal menyimpan file logo 2."; }
    } else { $error_msg = "Format file tidak valid. Gunakan JPG, PNG, GIF, WEBP, atau SVG."; }
}

// Handle Fonnte Token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_token'])) {
    $token = $_POST['fonnte_token'];
    $stmt_check = $db->query("SELECT count(*) FROM settings WHERE setting_key='fonnte_token'");
    if($stmt_check->fetchColumn() > 0) {
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'fonnte_token'");
        $stmt->execute([$token]);
    } else {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('fonnte_token', ?)");
        $stmt->execute([$token]);
    }
    $success_msg = "Token WhatsApp berhasil disimpan!";
}

// Handle Instansi Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_instansi'])) {
    $fields = ['instansi_nama','instansi_program','instansi_alamat','instansi_kota','petugas_jabatan','disclaimer_text'];
    foreach($fields as $f) {
        $val = $_POST[$f] ?? '';
        $chk = $db->prepare("SELECT count(*) FROM settings WHERE setting_key=?");
        $chk->execute([$f]);
        if($chk->fetchColumn() > 0) {
            $up = $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
            $up->execute([$val, $f]);
        } else {
            $ins = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?)");
            $ins->execute([$f, $val]);
        }
    }
    $success_msg = "Data instansi berhasil diperbarui!";
}

// Handle Ganti Kredensial Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_credentials'])) {
    $old_pass    = $_POST['old_password']     ?? '';
    $new_user    = trim($_POST['new_username']   ?? '');
    $new_pass    = $_POST['new_password']     ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    $db_user = $db->query("SELECT setting_value FROM settings WHERE setting_key='admin_username'")->fetchColumn();
    $db_pass = $db->query("SELECT setting_value FROM settings WHERE setting_key='admin_password'")->fetchColumn();

    $pass_ok = password_verify($old_pass, $db_pass) ?: ($db_pass === 'admin123' && $old_pass === 'admin123');

    if (!$pass_ok) {
        $error_msg = "Password lama tidak sesuai.";
    } elseif (empty($new_user)) {
        $error_msg = "Username baru tidak boleh kosong.";
    } elseif (!empty($new_pass) && $new_pass !== $confirm) {
        $error_msg = "Konfirmasi password baru tidak cocok.";
    } else {
        // Update username
        $chk = $db->query("SELECT count(*) FROM settings WHERE setting_key='admin_username'")->fetchColumn();
        if ($chk > 0) {
            $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='admin_username'")->execute([$new_user]);
        } else {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('admin_username',?)")->execute([$new_user]);
        }
        // Update password (hanya jika diisi)
        if (!empty($new_pass)) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $chkp = $db->query("SELECT count(*) FROM settings WHERE setting_key='admin_password'")->fetchColumn();
            if ($chkp > 0) {
                $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='admin_password'")->execute([$hashed]);
            } else {
                $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('admin_password',?)")->execute([$hashed]);
            }
        }
        $success_msg = "Kredensial admin berhasil diperbarui!";
    }
}

try {
    // Semua data (untuk riwayat & chart)
    $all_data_stmt = $db->query("SELECT * FROM skrining ORDER BY created_at DESC");
    $all_data = $all_data_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hitung kunjungan per NIK
    $visit_count = [];
    foreach($all_data as $r) {
        $visit_count[$r['nik']] = ($visit_count[$r['nik']] ?? 0) + 1;
    }

    // Data terbaru per NIK (default tampilan admin)
    $latest_stmt = $db->query("
        SELECT s.* FROM skrining s
        INNER JOIN (SELECT nik, MAX(id) as max_id FROM skrining GROUP BY nik) latest
        ON s.id = latest.max_id
        ORDER BY s.created_at DESC
    ");
    $latest_data = $latest_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Default: tampilkan terbaru per NIK
    $data = $latest_data;
    $total_unik = count($latest_data);
    $total_semua = count($all_data);
    $pasien_berulang = count(array_filter($visit_count, fn($v) => $v > 1));

    $stmt_logo = $db->query("SELECT setting_value FROM settings WHERE setting_key='logo_path'");
    $logo_path = $stmt_logo->fetchColumn() ?: 'logo.png';

    $stmt_logo2 = $db->query("SELECT setting_value FROM settings WHERE setting_key='logo2_path'");
    $logo2_path = $stmt_logo2->fetchColumn() ?: '';

    $stmt_token = $db->query("SELECT setting_value FROM settings WHERE setting_key='fonnte_token'");
    $fonnte_token = $stmt_token->fetchColumn() ?: '';

    // Load instansi settings
    $all_settings_stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    $all_settings = [];
    foreach($all_settings_stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $all_settings[$s['setting_key']] = $s['setting_value'];
    }
    $instansi_nama    = $all_settings['instansi_nama']    ?? 'DINAS KESEHATAN PROVINSI LAMPUNG';
    $instansi_program = $all_settings['instansi_program'] ?? 'Program Pengendalian Tuberkulosis (TB)';
    $instansi_alamat  = $all_settings['instansi_alamat']  ?? 'Jl. Dr. Susilo No.46, Bandar Lampung';
    $instansi_kota    = $all_settings['instansi_kota']    ?? 'Bandar Lampung';
    $petugas_jabatan  = $all_settings['petugas_jabatan']  ?? 'Petugas / Sistem Skrining TB';
    $disclaimer_text  = $all_settings['disclaimer_text']  ?? '';
    $current_admin_user = $all_settings['admin_username'] ?? 'admin';
} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Data Aggregation for Charts
$usia_data = ['<20'=>0,'20-30'=>0,'31-40'=>0,'41-50'=>0,'>50'=>0];
$jk_data = ['Laki-laki'=>0,'Perempuan'=>0];
$keperluan_data = [];
$trend_data = [];
$berisiko = 0;
$gejala_data = ['Batuk >2Mgg'=>0,'BB Turun'=>0,'Keringat Malam'=>0,'HIV-AIDS'=>0,'Demam >2Mgg'=>0,'Kontak TB'=>0,'Diabetes'=>0,'Klg Serumah'=>0];

foreach($data as $row) {
    $u = (int)$row['usia'];
    if($u < 20) $usia_data['<20']++;
    elseif($u <= 30) $usia_data['20-30']++;
    elseif($u <= 40) $usia_data['31-40']++;
    elseif($u <= 50) $usia_data['41-50']++;
    else $usia_data['>50']++;
    if(isset($jk_data[$row['jenis_kelamin']])) $jk_data[$row['jenis_kelamin']]++;
    $kp = $row['keperluan'] ?: 'Lainnya';
    $keperluan_data[$kp] = ($keperluan_data[$kp] ?? 0) + 1;
    $day = date('d/m', strtotime($row['created_at']));
    $trend_data[$day] = ($trend_data[$day] ?? 0) + 1;
    if($row['batuk_2_minggu']=='Ya') $gejala_data['Batuk >2Mgg']++;
    if($row['penurunan_bb']=='Ya') $gejala_data['BB Turun']++;
    if($row['keringat_malam']=='Ya') $gejala_data['Keringat Malam']++;
    if($row['hiv_aids']=='Ya') $gejala_data['HIV-AIDS']++;
    if($row['demam_2_minggu']=='Ya') $gejala_data['Demam >2Mgg']++;
    if($row['kontak_tb']=='Ya') $gejala_data['Kontak TB']++;
    if($row['diabetes']=='Ya') $gejala_data['Diabetes']++;
    if(($row['anggota_keluarga_sakit'] ?? '') =='Ya') $gejala_data['Klg Serumah']++;
    if(strpos($row['hasil'],'Rekomendasi')!==false) $berisiko++;
}
$aman = count($data) - $berisiko;
$trend_data = array_reverse($trend_data);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Skrining TB</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --black: #000000;
            --white: #ffffff;
            --glass-dark: rgba(0,0,0,0.04);
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--white);
            color: var(--black);
            letter-spacing: -0.02em;
        }
        .font-mono-tag {
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .border-black-2 { border: 2px solid var(--black); }
        .border-bottom-black { border-bottom: 2px solid var(--black); }

        .btn-pill {
            background: var(--white); color: var(--black); border: 2px solid var(--black);
            border-radius: 50px; padding: 8px 20px; font-weight: 500; transition: all 0.2s; cursor: pointer; display: inline-block;
        }
        .btn-pill:hover { background: var(--glass-dark); }
        .btn-pill-solid {
            background: var(--black); color: var(--white); border-radius: 50px;
            padding: 8px 20px; font-weight: 500; cursor: pointer; display: inline-block;
            transition: transform 0.1s;
        }
        .btn-pill-solid:active { transform: scale(0.95); }
        .btn-pill:focus, .btn-pill-solid:focus, input[type="file"]:focus, input[type="text"]:focus {
            outline: 2px dashed var(--black); outline-offset: 4px;
        }

        .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--black); border-radius: 0; padding: 4px 8px; outline: none;
            background: var(--white); font-family: 'JetBrains Mono', monospace; font-size: 0.875rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus { border-style: dashed; }
        table.dataTable.display tbody tr.odd { background-color: var(--white); }
        table.dataTable.display tbody tr.even { background-color: var(--glass-dark); }
        table.dataTable.display tbody tr:hover { background-color: rgba(0,0,0,0.08); }
        table.dataTable thead th {
            border-bottom: 2px solid var(--black) !important; font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;
        }
        table.dataTable.no-footer { border-bottom: 2px solid var(--black); }
        .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 50px !important; border: 1px solid transparent !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--black) !important; color: var(--white) !important; border: 1px solid var(--black) !important;
        }

        .status-risk {
            background: var(--black); color: var(--white); padding: 2px 8px; border-radius: 50px;
            font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; letter-spacing: 0.05em;
        }
        .status-safe {
            background: var(--white); color: var(--black); border: 1px dashed var(--black); padding: 2px 8px; border-radius: 50px;
            font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; letter-spacing: 0.05em;
        }
        
        .chart-container { position: relative; width: 100%; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border:2px solid #000; border-radius:20px; padding:40px; width:90%; max-width:600px; max-height:85vh; overflow-y:auto; position:relative; animation: fadeUp 0.3s ease; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .detail-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px dashed #e5e7eb; font-size:.9rem; }
        .detail-row:last-child { border-bottom:none; }
        .toast { position:fixed; bottom:24px; right:24px; z-index:9999; padding:12px 20px; border-radius:50px; font-family:'JetBrains Mono',monospace; font-size:.75rem; letter-spacing:.05em; display:none; animation:fadeUp .3s ease; }
        .toast.success { background:#000; color:#fff; }
        .toast.error { background:#fff; color:#000; border:2px dashed #000; }
        .btn-action { border:1px solid #000; border-radius:50px; padding:3px 10px; font-size:.7rem; cursor:pointer; font-family:'JetBrains Mono',monospace; letter-spacing:.05em; transition:all .2s; }
        .btn-action:hover { background:#000; color:#fff; }
        .btn-action.danger:hover { background:#ef4444; border-color:#ef4444; color:#fff; }
    </style>
</head>
<body class="min-h-screen">

    <nav class="bg-white border-bottom-black">
        <div class="max-w-[1920px] mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <div class="w-8 h-8 bg-black text-white rounded-full flex items-center justify-center font-bold font-mono-tag">A</div>
                <span class="font-bold text-xl tracking-tight">System Admin</span>
            </div>
            <div>
                <a href="admin.php?logout=true" class="btn-pill font-mono-tag text-xs">Sign Out</a>
            </div>
        </div>
    </nav>

    <div class="max-w-[1920px] mx-auto px-6 py-10 grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <div class="lg:col-span-1 space-y-8">
            
            <!-- Global Feedback -->
            <?php if($success_msg): ?>
                <div class="font-mono-tag text-xs p-3 border-2 border-black bg-black text-white shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            <?php if($error_msg): ?>
                <div class="font-mono-tag text-xs p-3 border-2 border-black border-dashed">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <!-- Setting Logo 1 -->
            <div class="border-black-2 p-6 rounded-xl bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                <h2 class="font-bold text-2xl mb-1 tracking-tight">Logo 1</h2>
                <p class="text-xs text-gray-500 font-mono-tag mb-4">Logo utama instansi (tampil di kiri)</p>
                <div class="mb-4 flex justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                    <?php if(!empty($logo_path) && file_exists($logo_path)): ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo 1" class="h-20 object-contain">
                    <?php else: ?>
                        <span class="text-gray-400 font-mono-tag text-xs">BELUM ADA LOGO 1</span>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                    <input type="file" name="logo" accept="image/*" required class="block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-2 file:border-black file:text-sm file:font-semibold
                    file:bg-white file:text-black hover:file:bg-gray-100 cursor-pointer">
                    <button type="submit" class="btn-pill-solid w-full text-sm font-mono-tag">Upload Logo 1</button>
                </form>
            </div>

            <!-- Setting Logo 2 -->
            <div class="border-black-2 p-6 rounded-xl bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                <h2 class="font-bold text-2xl mb-1 tracking-tight">Logo 2</h2>
                <p class="text-xs text-gray-500 font-mono-tag mb-4">Logo tambahan (tampil di kanan, opsional)</p>
                <div class="mb-4 flex justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                    <?php if(!empty($logo2_path) && file_exists($logo2_path)): ?>
                        <img src="<?php echo htmlspecialchars($logo2_path); ?>" alt="Logo 2" class="h-20 object-contain">
                    <?php else: ?>
                        <span class="text-gray-400 font-mono-tag text-xs">BELUM ADA LOGO 2</span>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                    <input type="file" name="logo2" accept="image/*" required class="block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-2 file:border-black file:text-sm file:font-semibold
                    file:bg-white file:text-black hover:file:bg-gray-100 cursor-pointer">
                    <button type="submit" class="btn-pill-solid w-full text-sm font-mono-tag">Upload Logo 2</button>
                </form>
            </div>

            <!-- Setting API Fonnte -->
            <div class="border-black-2 p-6 rounded-xl bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                <h2 class="font-bold text-2xl mb-2 tracking-tight">API Fonnte</h2>
                <p class="text-sm text-gray-600 mb-6 font-light">Token untuk pengiriman otomatis hasil skrining via WhatsApp.</p>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-2">Token API</label>
                        <input type="text" name="fonnte_token" value="<?php echo htmlspecialchars($fonnte_token); ?>" placeholder="Paste token Fonnte di sini..." class="w-full border-2 border-black p-3 rounded-lg font-mono-tag text-sm focus:outline-none focus:border-dashed">
                    </div>
                    <button type="submit" name="save_token" class="btn-pill-solid w-full text-sm font-mono-tag">Simpan API Key</button>
                </form>
            </div>

            <!-- Keamanan Akun -->
            <div class="border-black-2 p-6 rounded-xl bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                <h2 class="font-bold text-2xl mb-1 tracking-tight">Keamanan Akun</h2>
                <p class="text-xs text-gray-500 font-mono-tag mb-5">Ganti username & password login admin.</p>

                <!-- Current user info -->
                <div class="flex items-center gap-3 mb-5 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="w-9 h-9 bg-black rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-mono-tag">AKTIF SEBAGAI</p>
                        <p class="font-bold text-sm"><?php echo htmlspecialchars($current_admin_user); ?></p>
                    </div>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Username Baru</label>
                        <input type="text" name="new_username"
                               value="<?php echo htmlspecialchars($current_admin_user); ?>"
                               class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none" required>
                    </div>
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Password Lama <span class="text-red-500">*</span></label>
                        <input type="password" name="old_password" placeholder="Wajib diisi"
                               class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none" required>
                    </div>
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Password Baru <span class="text-gray-400">(kosongkan jika tidak ganti)</span></label>
                        <input type="password" name="new_password" placeholder="Min. 6 karakter"
                               class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none">
                    </div>
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" placeholder="Ulangi password baru"
                               class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none">
                    </div>
                    <button type="submit" name="save_credentials" class="btn-pill-solid w-full text-sm font-mono-tag">
                        <i class="fas fa-lock mr-2"></i> Simpan Perubahan
                    </button>
                </form>
            </div>

            <!-- Pengaturan Instansi -->
            <div class="border-black-2 p-6 rounded-xl bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                <h2 class="font-bold text-2xl mb-2 tracking-tight">Identitas Instansi</h2>
                <p class="text-sm text-gray-600 mb-6 font-light">Data ini akan tampil di Kop Surat Keterangan PDF.</p>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Nama Instansi</label>
                        <input type="text" name="instansi_nama" value="<?php echo htmlspecialchars($instansi_nama); ?>" class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none">
                    </div>
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Nama Program</label>
                        <input type="text" name="instansi_program" value="<?php echo htmlspecialchars($instansi_program); ?>" class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none">
                    </div>
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Alamat &amp; Telp</label>
                        <input type="text" name="instansi_alamat" value="<?php echo htmlspecialchars($instansi_alamat); ?>" class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none">
                    </div>
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Kota (untuk TTD)</label>
                        <input type="text" name="instansi_kota" value="<?php echo htmlspecialchars($instansi_kota); ?>" class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none">
                    </div>
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Jabatan Petugas (TTD kanan)</label>
                        <input type="text" name="petugas_jabatan" value="<?php echo htmlspecialchars($petugas_jabatan); ?>" class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none">
                    </div>
                    <div>
                        <label class="font-mono-tag text-xs text-gray-500 block mb-1">Disclaimer / Catatan Kaki</label>
                        <textarea name="disclaimer_text" rows="3" class="w-full border-2 border-black p-2 rounded-lg text-sm focus:outline-none resize-none"><?php echo htmlspecialchars($disclaimer_text); ?></textarea>
                    </div>
                    <button type="submit" name="save_instansi" class="btn-pill-solid w-full text-sm font-mono-tag">Simpan Identitas</button>
                </form>
            </div>

        </div>

        <div class="lg:col-span-3">
            <div class="mb-8 flex justify-between items-end flex-wrap gap-4">
                <div>
                    <h1 class="text-4xl md:text-5xl font-bold tracking-tight mb-2">Data Skrining.</h1>
                    <p class="text-xl font-light text-gray-600">Overview &amp; Management
                        <?php if($pasien_berulang > 0): ?>
                        <span class="font-mono-tag text-xs ml-2 px-2 py-1 bg-black text-white rounded-full"><?php echo $pasien_berulang; ?> berkunjung ulang</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex gap-3 flex-wrap">
                    <button id="toggle-view-btn" onclick="toggleView()" class="btn-pill flex items-center font-mono-tag text-sm border-2 border-black px-4 py-2 rounded-full hover:bg-black hover:text-white transition-colors">
                        <i class="fas fa-history mr-2"></i> <span id="toggle-label">Lihat Semua Riwayat</span>
                    </button>
                    <!-- Export Dropdown -->
                    <div class="relative" id="export-wrapper">
                        <button onclick="toggleExportMenu()" class="btn-pill-solid flex items-center font-mono-tag text-sm gap-2">
                            <i class="fas fa-download"></i> Export Data <i class="fas fa-chevron-down text-xs ml-1"></i>
                        </button>
                        <div id="export-menu" class="hidden absolute right-0 mt-2 w-52 bg-white border-2 border-black rounded-xl overflow-hidden shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] z-50">
                            <button onclick="exportCSV('latest')" class="w-full text-left px-4 py-3 font-mono-tag text-xs hover:bg-black hover:text-white transition-colors flex items-center gap-2 border-b border-gray-100">
                                <i class="fas fa-user"></i> Terbaru per NIK
                            </button>
                            <button onclick="exportCSV('all')" class="w-full text-left px-4 py-3 font-mono-tag text-xs hover:bg-black hover:text-white transition-colors flex items-center gap-2">
                                <i class="fas fa-history"></i> Semua Riwayat
                            </button>
                        </div>
                    </div>

                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="border-black-2 rounded-xl p-6 bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                    <span class="font-mono-tag text-xs text-gray-500 mb-4 block">Pasien Unik (per NIK)</span>
                    <p class="text-5xl font-bold"><?php echo $total_unik; ?></p>
                    <p class="text-xs text-gray-400 mt-2 font-mono-tag"><?php echo $total_semua; ?> total entri riwayat</p>
                </div>
                <div class="border-black-2 border-dashed rounded-xl p-6 bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                    <span class="font-mono-tag text-xs text-gray-500 mb-4 block">High Risk</span>
                    <p class="text-5xl font-bold"><?php echo $berisiko; ?></p>
                </div>
                <div class="border-black-2 rounded-xl p-6 bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                    <span class="font-mono-tag text-xs text-gray-500 mb-4 block">Low Risk</span>
                    <p class="text-5xl font-bold"><?php echo $aman; ?></p>
                </div>
            </div>

            <!-- Charts Section 1 (2 Columns) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="border-black-2 rounded-xl p-6 bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                    <h3 class="font-mono-tag text-xs text-gray-500 mb-4">Demografi Usia</h3>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="chartUsia"></canvas>
                    </div>
                </div>
                <div class="border-black-2 rounded-xl p-6 bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                    <h3 class="font-mono-tag text-xs text-gray-500 mb-4">Proporsi Jenis Kelamin</h3>
                    <div class="chart-container flex justify-center" style="height: 250px;">
                        <canvas id="chartJK"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts: Gejala + Trend -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="border-black-2 rounded-xl p-6 bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                    <h3 class="font-mono-tag text-xs text-gray-500 mb-4">Frekuensi Gejala (Ya)</h3>
                    <div class="chart-container" style="height:250px"><canvas id="chartGejala"></canvas></div>
                </div>
                <div class="border-black-2 rounded-xl p-6 bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow">
                    <h3 class="font-mono-tag text-xs text-gray-500 mb-4">Trend Pendaftaran Harian</h3>
                    <div class="chart-container" style="height:250px"><canvas id="chartTrend"></canvas></div>
                </div>
            </div>
            <!-- Chart Keperluan -->
            <div class="border-black-2 rounded-xl p-6 bg-white hover:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-shadow mb-8">
                <h3 class="font-mono-tag text-xs text-gray-500 mb-4">Distribusi Keperluan Kunjungan</h3>
                <div class="chart-container" style="height:220px"><canvas id="chartKeperluan"></canvas></div>
            </div>

            <div class="border-black-2 p-1 overflow-hidden" style="border-radius: 12px;">
                <div class="p-4 overflow-x-auto">
                    <table id="tabel-skrining" class="display nowrap w-full text-sm text-left" style="width:100%">
                        <thead>
                            <tr>
                                <th>Waktu</th><th>Nama</th><th>NIK</th><th>Usia</th><th>WhatsApp</th>
                                <th>Batuk>2mgg</th><th>BB Turun</th><th>Krgnt Mlm</th><th>HIV</th><th>Demam</th><th>Kontak</th><th>DM</th><th>Klg Serumah</th>
                                <th>Status</th><th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data as $row): ?>
                            <tr>
                                <td class="font-mono-tag text-xs text-gray-500"><?php echo date('d/m/y H:i', strtotime($row['created_at'])); ?></td>
                                <td class="font-medium">
                                    <?php echo htmlspecialchars($row['nama']); ?>
                                    <?php $vc = $visit_count[$row['nik']] ?? 1; if($vc > 1): ?>
                                    <span class="ml-1 font-mono-tag text-xs px-1.5 py-0.5 rounded-full bg-black text-white" title="Kunjungan ke-<?php echo $vc; ?>"><?php echo $vc; ?>x</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-mono-tag text-xs"><?php echo htmlspecialchars($row['nik']); ?></td>
                                <td><?php echo $row['usia']; ?>/<?php echo $row['jenis_kelamin'] == 'Laki-laki' ? 'L' : 'P'; ?></td>
                                <td class="font-mono-tag text-xs"><a href="https://wa.me/<?php echo $row['no_whatsapp']; ?>" target="_blank" class="hover:underline"><?php echo htmlspecialchars($row['no_whatsapp']); ?></a></td>
                                
                                <td class="font-bold <?php echo $row['batuk_2_minggu']=='Ya'?'':'text-gray-300 font-normal'; ?>"><?php echo $row['batuk_2_minggu']; ?></td>
                                <td class="font-bold <?php echo $row['penurunan_bb']=='Ya'?'':'text-gray-300 font-normal'; ?>"><?php echo $row['penurunan_bb']; ?></td>
                                <td class="font-bold <?php echo $row['keringat_malam']=='Ya'?'':'text-gray-300 font-normal'; ?>"><?php echo $row['keringat_malam']; ?></td>
                                <td class="font-bold <?php echo $row['hiv_aids']=='Ya'?'':'text-gray-300 font-normal'; ?>"><?php echo $row['hiv_aids']; ?></td>
                                <td class="font-bold <?php echo $row['demam_2_minggu']=='Ya'?'':'text-gray-300 font-normal'; ?>"><?php echo $row['demam_2_minggu']; ?></td>
                                <td class="font-bold <?php echo $row['kontak_tb']=='Ya'?'':'text-gray-300 font-normal'; ?>"><?php echo $row['kontak_tb']; ?></td>
                                <td class="font-bold <?php echo $row['diabetes']=='Ya'?'':'text-gray-300 font-normal'; ?>"><?php echo $row['diabetes']; ?></td>
                                <td class="font-bold <?php $aks = $row['anggota_keluarga_sakit'] ?? 'Tidak'; echo $aks=='Ya'?'text-black':'text-gray-300 font-normal'; ?>"><?php echo $aks; ?></td>
                                
                                <td><?php echo strpos($row['hasil'],'Rekomendasi')!==false ? '<span class="status-risk">RISK</span>' : '<span class="status-safe">SAFE</span>'; ?></td>
                                <td class="whitespace-nowrap">
                                    <button class="btn-action mr-1" onclick="showDetail(<?php echo $row['id']; ?>)">Detail</button>
                                    <button class="btn-action mr-1" onclick="resendWA(<?php echo $row['id']; ?>, this)">WA</button>
                                    <button class="btn-action danger" onclick="deleteRow(<?php echo $row['id']; ?>, this)">Hapus</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal-overlay" id="modal-detail">
        <div class="modal-box">
            <button onclick="document.getElementById('modal-detail').classList.remove('active')" style="position:absolute;top:16px;right:20px;background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>
            <span class="font-mono-tag text-xs text-gray-500">Detail Pasien</span>
            <h2 class="text-3xl font-bold mt-2 mb-6" id="modal-nama">—</h2>
            <div id="modal-body"></div>
        </div>
    </div>
    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

    <script>
        $(document).ready(function() {
            var table = $('#tabel-skrining').DataTable({
                scrollX: true,
                dom: 'lrtip',
                language: { search: "Filter:", lengthMenu: "_MENU_ / page" }
            });

            new $.fn.dataTable.Buttons(table, {
                buttons: [ { extend: 'excelHtml5', title: 'Data_Skrining' } ]
            });

            // Close export menu when clicking outside
            document.addEventListener('click', function(e) {
                const wrapper = document.getElementById('export-wrapper');
                if (wrapper && !wrapper.contains(e.target)) {
                    document.getElementById('export-menu').classList.add('hidden');
                }
            });

            Chart.defaults.font.family = "'JetBrains Mono', monospace";
            Chart.defaults.color = '#000000';

            const ctxUsia = document.getElementById('chartUsia').getContext('2d');
            new Chart(ctxUsia, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_keys($usia_data)); ?>,
                    datasets: [{
                        label: 'Jumlah',
                        data: <?php echo json_encode(array_values($usia_data)); ?>,
                        backgroundColor: '#000000',
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#e5e7eb' }, ticks: { stepSize: 1 } }, x: { grid: { display: false } } }
                }
            });

            const ctxJK = document.getElementById('chartJK').getContext('2d');
            new Chart(ctxJK, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_keys($jk_data)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($jk_data)); ?>,
                        backgroundColor: ['#000000', '#dddddd'], hoverOffset: 4, borderWidth: 2, borderColor: '#ffffff'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } }, cutout: '70%' }
            });

            const barOpts = { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{borderDash:[4,4],color:'#e5e7eb'},ticks:{stepSize:1}},x:{grid:{display:false}}} };

            new Chart(document.getElementById('chartGejala').getContext('2d'), {
                type:'bar', data:{ labels:<?php echo json_encode(array_keys($gejala_data)); ?>, datasets:[{label:'Ya', data:<?php echo json_encode(array_values($gejala_data)); ?>, backgroundColor:'#000', borderRadius:4, borderWidth:0}] }, options:barOpts
            });

            new Chart(document.getElementById('chartTrend').getContext('2d'), {
                type:'line', data:{ labels:<?php echo json_encode(array_keys($trend_data)); ?>, datasets:[{label:'Pendaftar', data:<?php echo json_encode(array_values($trend_data)); ?>, borderColor:'#000', backgroundColor:'rgba(0,0,0,0.05)', tension:0.4, pointBackgroundColor:'#000', fill:true}] },
                options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{borderDash:[4,4],color:'#e5e7eb'},ticks:{stepSize:1}},x:{grid:{display:false}}} }
            });

            new Chart(document.getElementById('chartKeperluan').getContext('2d'), {
                type:'bar', data:{ labels:<?php echo json_encode(array_keys($keperluan_data)); ?>, datasets:[{label:'Jumlah', data:<?php echo json_encode(array_values($keperluan_data)); ?>, backgroundColor:['#000','#333','#555','#777','#999'], borderRadius:4, borderWidth:0}] },
                options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,grid:{borderDash:[4,4],color:'#e5e7eb'}},y:{grid:{display:false}}} }
            });
        });

        function showToast(msg, type='success') {
            const t = document.getElementById('toast');
            t.textContent = msg; t.className = 'toast ' + type; t.style.display = 'block';
            setTimeout(() => t.style.display = 'none', 3000);
        }

        function toggleExportMenu() {
            document.getElementById('export-menu').classList.toggle('hidden');
        }

        function exportCSV(mode) {
            document.getElementById('export-menu').classList.add('hidden');
            const source   = mode === 'all' ? window._allData : window._latestData;
            const filename = mode === 'all' ? 'skrining_semua_riwayat.csv' : 'skrining_terbaru_per_nik.csv';

            const headers = [
                'ID','Waktu','Nama','NIK','Kewarganegaraan','Jenis Kelamin','Usia','WhatsApp',
                'Tujuan Lampung','Keperluan','Lama Tinggal',
                'Batuk >2 Minggu','Penurunan BB','Keringat Malam','HIV-AIDS',
                'Demam >2 Minggu','Kontak TB','Diabetes','Keluarga Serumah Bergejala','Hasil'
            ];
            const rows = source.map(r => [
                r.id, r.created_at, r.nama, r.nik, r.kewarganegaraan,
                r.jenis_kelamin, r.usia, r.no_whatsapp, r.tujuan_lampung,
                r.keperluan, r.lama_tinggal, r.batuk_2_minggu, r.penurunan_bb,
                r.keringat_malam, r.hiv_aids, r.demam_2_minggu, r.kontak_tb,
                r.diabetes, r.anggota_keluarga_sakit || 'Tidak', r.hasil
            ].map(v => `"${String(v||'').replace(/"/g,'""')}"`).join(','));

            const csv  = [headers.join(','), ...rows].join('\n');
            const bom  = '\uFEFF';
            const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8;' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = filename; a.click();
            URL.revokeObjectURL(url);
            showToast('✅ Exported ' + source.length + ' records — ' + (mode === 'all' ? 'Semua Riwayat' : 'Terbaru per NIK'), 'success');
        }

        // Data semua riwayat (embed dari PHP)
        const allData = <?php echo json_encode($all_data); ?>;
        const latestData = <?php echo json_encode($latest_data); ?>;
        window._allData    = allData;
        window._latestData = latestData;
        let showingAll = false;

        function toggleView() {
            showingAll = !showingAll;
            const table = $('#tabel-skrining').DataTable();
            table.destroy();
            const tbody = document.querySelector('#tabel-skrining tbody');
            tbody.innerHTML = '';

            const source = showingAll ? allData : latestData;
            source.forEach(row => {
                const vc = <?php echo json_encode($visit_count); ?>[row.nik] || 1;
                const badge = vc > 1 ? `<span class="ml-1 font-mono-tag text-xs px-1.5 py-0.5 rounded-full bg-black text-white">${vc}x</span>` : '';
                const isRisk = row.hasil && row.hasil.includes('Rekomendasi');
                const status = isRisk
                    ? '<span class="status-risk">RISK</span>'
                    : '<span class="status-safe">SAFE</span>';
                const aks = row.anggota_keluarga_sakit || 'Tidak';
                const d = new Date(row.created_at).toLocaleString('id-ID');
                tbody.innerHTML += `<tr>
                    <td class="font-mono-tag text-xs text-gray-500">${d}</td>
                    <td class="font-medium">${row.nama}${badge}</td>
                    <td class="font-mono-tag text-xs">${row.nik}</td>
                    <td>${row.usia}/${row.jenis_kelamin === 'Laki-laki' ? 'L' : 'P'}</td>
                    <td class="font-mono-tag text-xs"><a href="https://wa.me/${row.no_whatsapp}" target="_blank" class="hover:underline">${row.no_whatsapp}</a></td>
                    <td class="${row.batuk_2_minggu==='Ya'?'font-bold':'text-gray-300'}">${row.batuk_2_minggu||'-'}</td>
                    <td class="${row.penurunan_bb==='Ya'?'font-bold':'text-gray-300'}">${row.penurunan_bb||'-'}</td>
                    <td class="${row.keringat_malam==='Ya'?'font-bold':'text-gray-300'}">${row.keringat_malam||'-'}</td>
                    <td class="${row.hiv_aids==='Ya'?'font-bold':'text-gray-300'}">${row.hiv_aids||'-'}</td>
                    <td class="${row.demam_2_minggu==='Ya'?'font-bold':'text-gray-300'}">${row.demam_2_minggu||'-'}</td>
                    <td class="${row.kontak_tb==='Ya'?'font-bold':'text-gray-300'}">${row.kontak_tb||'-'}</td>
                    <td class="${row.diabetes==='Ya'?'font-bold':'text-gray-300'}">${row.diabetes||'-'}</td>
                    <td class="${aks==='Ya'?'font-bold':'text-gray-300'}">${aks}</td>
                    <td>${status}</td>
                    <td class="whitespace-nowrap">
                        <button class="btn-action mr-1" onclick="showDetail(${row.id})">Detail</button>
                        <button class="btn-action mr-1" onclick="resendWA(${row.id}, this)">WA</button>
                        <button class="btn-action danger" onclick="deleteRow(${row.id}, this)">Hapus</button>
                    </td>
                </tr>`;
            });

            $('#tabel-skrining').DataTable({ responsive: true, pageLength: 25, language: { url: '' } });

            const label = document.getElementById('toggle-label');
            const btn = document.getElementById('toggle-view-btn');
            if(showingAll) {
                label.textContent = 'Terbaru per NIK';
                btn.innerHTML = '<i class="fas fa-user mr-2"></i><span id="toggle-label">Terbaru per NIK</span>';
            } else {
                label.textContent = 'Lihat Semua Riwayat';
                btn.innerHTML = '<i class="fas fa-history mr-2"></i><span id="toggle-label">Lihat Semua Riwayat</span>';
            }
        }

        function showDetail(id) {
            fetch('admin_action.php?action=detail&id=' + id)
                .then(r => r.json()).then(res => {
                    if(res.status !== 'success') { showToast(res.message,'error'); return; }
                    const d = res.data;
                    document.getElementById('modal-nama').textContent = d.nama;
                    const fields = [
                        ['NIK', d.nik], ['Usia', d.usia + ' tahun'], ['Jenis Kelamin', d.jenis_kelamin],
                        ['Kewarganegaraan', d.kewarganegaraan], ['WhatsApp', d.no_whatsapp],
                        ['Tujuan', d.tujuan_lampung], ['Keperluan', d.keperluan], ['Lama Tinggal', d.lama_tinggal + ' hari'],
                        ['Batuk >2 Minggu', d.batuk_2_minggu], ['Penurunan BB', d.penurunan_bb],
                        ['Keringat Malam', d.keringat_malam], ['HIV-AIDS', d.hiv_aids],
                        ['Demam >2 Minggu', d.demam_2_minggu], ['Kontak TB', d.kontak_tb],
                        ['Diabetes', d.diabetes],
                        ['⭐ Keluarga Serumah Bergejala', d.anggota_keluarga_sakit || 'Tidak'],
                        ['Hasil Skrining', d.hasil], ['Tanggal', d.created_at]
                    ];
                    document.getElementById('modal-body').innerHTML = fields.map(([k,v]) =>
                        `<div class="detail-row"><span class="font-mono-tag text-xs text-gray-500">${k}</span><span class="font-medium">${v||'-'}</span></div>`
                    ).join('');
                    document.getElementById('modal-detail').classList.add('active');
                });
        }

        function deleteRow(id, btn) {
            if(!confirm('Yakin ingin menghapus data ini?')) return;
            const formData = new FormData();
            formData.append('action','delete'); formData.append('id', id);
            fetch('admin_action.php', {method:'POST', body:formData})
                .then(r => r.json()).then(res => {
                    if(res.status === 'success') {
                        btn.closest('tr').style.opacity='0'; btn.closest('tr').style.transition='opacity .4s';
                        setTimeout(() => { btn.closest('tr').remove(); showToast('Data berhasil dihapus'); }, 400);
                    } else showToast(res.message,'error');
                });
        }

        function resendWA(id, btn) {
            btn.textContent = '...'; btn.disabled = true;
            const formData = new FormData();
            formData.append('action','resend_wa'); formData.append('id', id);
            fetch('admin_action.php', {method:'POST', body:formData})
                .then(r => r.json()).then(res => {
                    btn.textContent = 'WA'; btn.disabled = false;
                    showToast(res.message, res.status === 'success' ? 'success' : 'error');
                });
        }

        document.getElementById('modal-detail').addEventListener('click', function(e) {
            if(e.target === this) this.classList.remove('active');
        });
    </script>

    <!-- Footer Credit -->
    <footer style="border-top: 1px solid #e5e7eb; padding: 32px 24px; text-align: center; margin-top: 48px;">
        <p style="font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af;">
            Crafted with precision &amp; care by
            <a href="https://zorostudio.id" target="_blank" rel="noopener"
               style="color: #111; font-weight: 700; text-decoration: none; border-bottom: 1px solid #111; padding-bottom: 1px; transition: opacity 0.2s;"
               onmouseover="this.style.opacity='0.5'" onmouseout="this.style.opacity='1'">
                Zoro Studio
            </a>
            &nbsp;&mdash;&nbsp; Building digital solutions that matter.
        </p>
        <p style="font-family: 'JetBrains Mono', monospace; font-size: 10px; color: #d1d5db; margin-top: 6px; letter-spacing: 0.05em;">
            &copy; <?php echo date('Y'); ?> &nbsp;&#183;&nbsp; zorostudio.id
        </p>
    </footer>

</body>
</html>
