<?php
require_once 'db.php';
try {
    $all_s = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
    $cfg = []; foreach($all_s as $s) $cfg[$s['setting_key']] = $s['setting_value'];
    $logo_path       = $cfg['logo_path']       ?? 'logo.png';
    $logo2_path      = $cfg['logo2_path']      ?? '';
    $instansi_nama   = $cfg['instansi_nama']   ?? 'DINAS KESEHATAN PROVINSI LAMPUNG';
    $instansi_program= $cfg['instansi_program']?? 'Program Pengendalian Tuberkulosis (TB)';
    $instansi_alamat = $cfg['instansi_alamat'] ?? 'Jl. Dr. Susilo No.46, Bandar Lampung';
    $instansi_kota   = $cfg['instansi_kota']   ?? 'Bandar Lampung';
    $petugas_jabatan = $cfg['petugas_jabatan'] ?? 'Petugas / Sistem Skrining TB';
    $disclaimer_text = $cfg['disclaimer_text'] ?? 'Surat keterangan ini bukan merupakan diagnosis medis.';
} catch(Exception $e) {
    $logo_path='logo.png'; $logo2_path='';
    $instansi_nama='DINAS KESEHATAN PROVINSI LAMPUNG';
    $instansi_program='Program Pengendalian Tuberkulosis (TB)';
    $instansi_alamat='Jl. Dr. Susilo No.46, Bandar Lampung';
    $instansi_kota='Bandar Lampung';
    $petugas_jabatan='Petugas / Sistem Skrining TB';
    $disclaimer_text='Surat keterangan ini bukan merupakan diagnosis medis.';
}

$result = null;
$error  = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nik'])) {
    $nik = trim($_POST['nik']);
    try {
        $stmt = $db->prepare("SELECT * FROM skrining WHERE nik = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$nik]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) $error = "Data tidak ditemukan. Pastikan NIK yang Anda masukkan sudah benar.";
    } catch(Exception $e) { $error = "Terjadi kesalahan sistem."; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Hasil Saya — Skrining TB Lampung</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #fafafa; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .input-nik {
            width: 100%; border: none; border-bottom: 2px solid #000;
            font-size: 2rem; font-weight: 600; padding: 12px 0;
            background: transparent; outline: none; letter-spacing: 0.08em;
            font-family: 'JetBrains Mono', monospace;
        }
        .input-nik::placeholder { color: #ccc; font-weight: 300; }
        .btn-search {
            background: #000; color: #fff; border: none; border-radius: 50px;
            padding: 14px 36px; font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: opacity 0.2s; width: 100%; margin-top: 24px;
        }
        .btn-search:hover { opacity: 0.75; }

        /* Result Card */
        .result-card {
            border-radius: 32px; padding: 40px; text-align: center;
            color: #fff; position: relative; overflow: hidden;
        }
        .result-card.risk {
            background: linear-gradient(135deg, #7F1D1D 0%, #991B1B 100%);
            box-shadow: 0 20px 50px rgba(127,29,29,0.4);
        }
        .result-card.safe {
            background: linear-gradient(135deg, #14532D 0%, #166534 100%);
            box-shadow: 0 20px 50px rgba(20,83,45,0.4);
        }
        .result-card::before {
            content: '';
            position: absolute; inset: 14px;
            border: 2px dashed rgba(255,255,255,0.3);
            border-radius: 20px; pointer-events: none;
        }
        .icon-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.4);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .status-badge {
            display: inline-block; font-family: 'JetBrains Mono', monospace;
            font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase;
            padding: 4px 14px; border-radius: 50px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.4);
            margin-bottom: 16px;
        }
        .btn-print {
            background: rgba(255,255,255,0.15); color: #fff;
            border: 2px solid rgba(255,255,255,0.5);
            border-radius: 50px; padding: 12px 28px;
            font-size: 0.95rem; font-weight: 500; cursor: pointer;
            width: 100%; margin-top: 20px; transition: background 0.2s;
        }
        .btn-print:hover { background: rgba(255,255,255,0.25); }

        /* Data table in result */
        .data-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem; }
        .data-row:last-child { border-bottom: none; }
        .data-label { opacity: 0.7; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .data-value { font-weight: 600; text-align: right; }

        /* Visit count badge */
        .visit-badge { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); border-radius: 50px; padding: 2px 10px; font-size: 11px; font-family: 'JetBrains Mono', monospace; }

        /* PRINT STYLES */
        @media print {
            @page { size: A4; margin: 20mm; }
            body * { visibility: hidden; }
            #print-cert { display: block !important; visibility: visible !important; position: fixed; left: 0; top: 0; width: 100%; }
            #print-cert * { visibility: visible !important; }
        }
        #print-cert { display: none; }
        .cert-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #000; padding-bottom: 12px; margin-bottom: 16px; }
        .cert-logos { display: flex; align-items: center; gap: 8px; }
        .cert-logo { width: 70px; height: 70px; object-fit: contain; }
        .cert-logo-placeholder { width: 70px; height: 70px; border: 1px solid #999; display: flex; align-items: center; justify-content: center; font-size: 9px; text-align: center; }
        .cert-title-block { text-align: center; flex: 1; }
        .cert-title-block h1 { font-size: 15px; font-weight: bold; margin: 0; }
        .cert-title-block h2 { font-size: 12px; font-weight: normal; margin: 2px 0; }
        .cert-title-block p { font-size: 10px; color: #555; margin: 0; }
        .cert-doc-title { text-align: center; margin: 12px 0; }
        .cert-doc-title h3 { font-size: 14px; font-weight: bold; text-decoration: underline; text-transform: uppercase; margin: 0; }
        .cert-doc-title p { font-size: 10px; color: #777; margin: 4px 0 0; }
        .cert-table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 12px 0; }
        .cert-table td { padding: 4px 8px; vertical-align: top; }
        .cert-table td:first-child { width: 42%; }
        .cert-table td:nth-child(2) { width: 4%; }
        .cert-result-box { border: 2px solid #000; padding: 10px 14px; margin: 12px 0; border-radius: 4px; }
        .cert-result-box.risk { background: #fff3f3; border-color: #7F1D1D; }
        .cert-result-box h4 { font-size: 11px; font-weight: bold; margin: 0 0 4px; }
        .cert-result-box p { font-size: 10px; margin: 0; }
        .cert-note { font-size: 9px; color: #555; font-style: italic; margin: 10px 0; }
        .cert-sign-area { display: flex; justify-content: space-between; margin-top: 30px; }
        .cert-sign-block { text-align: center; width: 45%; }
        .cert-sign-line { border-top: 1px solid #000; margin-top: 60px; padding-top: 4px; font-size: 11px; }
        .cert-id { text-align: center; font-size: 9px; color: #666; margin-top: 16px; border-top: 1px dashed #ccc; padding-top: 8px; font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
    <!-- Top Nav -->
    <nav style="background:#fff; border-bottom:1px solid #f0f0f0; padding:16px 24px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:50;">
        <a href="index.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:#000;">
            <?php if(!empty($logo_path) && file_exists($logo_path)): ?>
            <img src="<?php echo htmlspecialchars($logo_path); ?>" class="w-8 h-8 rounded-full object-contain" alt="Logo">
            <?php endif; ?>
            <span style="font-weight:700;font-size:1rem;">Skrining TB</span>
        </a>
        <a href="index.php" class="mono" style="font-size:11px;color:#666;text-decoration:none;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-arrow-left"></i> Skrining Baru
        </a>
    </nav>

    <!-- Main -->
    <main style="max-width:560px; margin:0 auto; padding:48px 24px;">

        <!-- Header -->
        <div style="margin-bottom:40px;">
            <p class="mono" style="font-size:11px;color:#999;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:8px;">Lacak Riwayat</p>
            <h1 style="font-size:2.2rem;font-weight:700;letter-spacing:-0.03em;margin:0 0 8px;">Cek Hasil Saya.</h1>
            <p style="color:#666;font-size:0.95rem;font-weight:300;">Masukkan NIK KTP Anda untuk melihat hasil skrining terakhir.</p>
        </div>

        <!-- Search Form -->
        <form method="POST" style="margin-bottom:40px;">
            <label class="mono" style="font-size:11px;color:#999;letter-spacing:0.08em;text-transform:uppercase;display:block;margin-bottom:8px;">Nomor Induk Kependudukan (NIK)</label>
            <input type="number" name="nik" class="input-nik"
                   placeholder="1871xxxxxxxx"
                   value="<?php echo htmlspecialchars($_POST['nik'] ?? ''); ?>"
                   maxlength="16" required autofocus>
            <button type="submit" class="btn-search">
                <i class="fas fa-search mr-2"></i> Cari Data Saya
            </button>
        </form>

        <?php if($error): ?>
        <div style="background:#fff3f3;border:2px solid #7F1D1D;border-radius:16px;padding:20px 24px;display:flex;align-items:center;gap:14px;margin-bottom:32px;">
            <i class="fas fa-exclamation-circle" style="color:#7F1D1D;font-size:1.4rem;"></i>
            <div>
                <p style="font-weight:600;margin:0 0 4px;color:#7F1D1D;">Data Tidak Ditemukan</p>
                <p style="font-size:0.85rem;color:#666;margin:0;"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if($result):
            $is_risk = strpos($result['hasil'],'Rekomendasi') !== false;
            $cardClass = $is_risk ? 'risk' : 'safe';
            $icon = $is_risk ? 'fa-exclamation-triangle' : 'fa-check';
            $tagText = $is_risk ? '⚠ Rekomendasi Pemeriksaan' : '✓ Bukan TB';
            $titleText = $is_risk ? 'Periksakan Diri Anda.' : 'Tidak Berisiko TB.';
            $descText = $is_risk ? 'Rekomendasi periksakan diri ke <b>RSUD / Puskesmas terdekat</b>.' : 'Tidak ditemukan gejala khas Tuberkulosis pada skrining Anda.';

            // Visit count
            $vc_stmt = $db->prepare("SELECT COUNT(*) FROM skrining WHERE nik=?");
            $vc_stmt->execute([$result['nik']]);
            $visit_count = (int)$vc_stmt->fetchColumn();

            $tgl = date('d F Y', strtotime($result['created_at']));
            $jam = date('H:i', strtotime($result['created_at']));
            $cert_id = 'SKR-TB-' . date('Ymd', strtotime($result['created_at'])) . '-' . strtoupper(substr(md5($result['id']), 0, 8));
        ?>

        <!-- Result Card -->
        <div class="result-card <?php echo $cardClass; ?>" style="margin-bottom:24px;">
            <div class="icon-circle">
                <i class="fas <?php echo $icon; ?>" style="font-size:1.8rem;color:#fff;"></i>
            </div>
            <div class="status-badge"><?php echo $tagText; ?></div>
            <h2 style="font-size:1.8rem;font-weight:700;margin:0 0 8px;letter-spacing:-0.03em;"><?php echo $titleText; ?></h2>
            <p style="opacity:0.85;font-size:0.9rem;font-weight:300;margin:0 0 24px;"><?php echo $descText; ?></p>

            <!-- Quick Data -->
            <div style="background:rgba(255,255,255,0.1);border-radius:16px;padding:16px 20px;text-align:left;margin-bottom:20px;">
                <div class="data-row">
                    <span class="data-label">Nama</span>
                    <span class="data-value"><?php echo htmlspecialchars($result['nama']); ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">NIK</span>
                    <span class="data-value mono" style="font-size:0.8rem;"><?php echo htmlspecialchars($result['nik']); ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Tanggal Skrining</span>
                    <span class="data-value"><?php echo $tgl; ?>, <?php echo $jam; ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Kunjungan</span>
                    <span class="data-value">
                        ke-<?php echo $visit_count; ?>
                        <?php if($visit_count > 1): ?>
                        <span class="visit-badge"><?php echo $visit_count; ?>x terdaftar</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">No. Dokumen</span>
                    <span class="data-value mono" style="font-size:0.75rem;"><?php echo $cert_id; ?></span>
                </div>
            </div>

            <button onclick="doPrint()" class="btn-print">
                <i class="fas fa-print mr-2"></i> Cetak / Unduh PDF
            </button>
            <?php if($is_risk): ?>
            <a href="#" onclick="openMaps(event)" class="btn-print" style="display:block;text-decoration:none;margin-top:10px;">
                <i class="fas fa-map-marker-alt mr-2" style="color:#fca5a5;"></i> Cari Puskesmas Terdekat
            </a>
            <?php endif; ?>
        </div>

        <!-- Info Tip -->
        <p class="mono" style="font-size:10px;color:#aaa;text-align:center;letter-spacing:0.05em;">
            DATA DITAMPILKAN BERDASARKAN KUNJUNGAN TERAKHIR &nbsp;·&nbsp; <?php echo $tgl; ?>
        </p>

        <!-- PRINT CERTIFICATE -->
        <div id="print-cert">
            <div class="cert-header">
                <div class="cert-logos">
                    <?php if(!empty($logo_path) && file_exists($logo_path)): ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" class="cert-logo">
                    <?php else: ?>
                    <div class="cert-logo-placeholder">LOGO</div>
                    <?php endif; ?>
                    <?php if(!empty($logo2_path) && file_exists($logo2_path)): ?>
                    <img src="<?php echo htmlspecialchars($logo2_path); ?>" alt="Logo2" class="cert-logo">
                    <?php endif; ?>
                </div>
                <div class="cert-title-block">
                    <h1><?php echo htmlspecialchars($instansi_nama); ?></h1>
                    <h2><?php echo htmlspecialchars($instansi_program); ?></h2>
                    <p><?php echo $instansi_alamat; ?></p>
                </div>
                <div style="width:80px;"></div>
            </div>

            <div class="cert-doc-title">
                <h3>Surat Keterangan Hasil Skrining Tuberkulosis</h3>
                <p>Diterbitkan otomatis oleh Sistem Skrining TB Digital</p>
            </div>

            <p style="font-size:12px;margin-bottom:10px;">Yang bertanda tangan di bawah ini menerangkan bahwa:</p>

            <table class="cert-table">
                <tr><td>Nama Lengkap</td><td>:</td><td><strong><?php echo htmlspecialchars($result['nama']); ?></strong></td></tr>
                <tr><td>NIK</td><td>:</td><td><?php echo htmlspecialchars($result['nik']); ?></td></tr>
                <tr><td>Usia / Jenis Kelamin</td><td>:</td><td><?php echo $result['usia']; ?> tahun / <?php echo htmlspecialchars($result['jenis_kelamin']); ?></td></tr>
                <tr><td>Kewarganegaraan</td><td>:</td><td><?php echo htmlspecialchars($result['kewarganegaraan']); ?></td></tr>
                <tr><td>No. WhatsApp</td><td>:</td><td><?php echo htmlspecialchars($result['no_whatsapp']); ?></td></tr>
                <tr><td colspan="3" style="padding-top:8px;font-weight:bold;border-top:1px solid #ddd;">Data Perjalanan</td></tr>
                <tr><td>Kota Tujuan di Lampung</td><td>:</td><td><?php echo htmlspecialchars($result['tujuan_lampung']); ?></td></tr>
                <tr><td>Keperluan</td><td>:</td><td><?php echo htmlspecialchars($result['keperluan']); ?></td></tr>
                <tr><td>Lama Tinggal</td><td>:</td><td><?php echo $result['lama_tinggal']; ?> hari</td></tr>
                <tr><td colspan="3" style="padding-top:8px;font-weight:bold;border-top:1px solid #ddd;">Keluhan yang Dilaporkan</td></tr>
                <tr><td>Batuk &gt; 2 Minggu</td><td>:</td><td><?php echo $result['batuk_2_minggu']; ?></td></tr>
                <tr><td>Penurunan Berat Badan</td><td>:</td><td><?php echo $result['penurunan_bb']; ?></td></tr>
                <tr><td>Keringat Malam</td><td>:</td><td><?php echo $result['keringat_malam']; ?></td></tr>
                <tr><td>HIV-AIDS</td><td>:</td><td><?php echo $result['hiv_aids']; ?></td></tr>
                <tr><td>Demam &gt; 2 Minggu</td><td>:</td><td><?php echo $result['demam_2_minggu']; ?></td></tr>
                <tr><td>Kontak Erat Penderita TB</td><td>:</td><td><?php echo $result['kontak_tb']; ?></td></tr>
                <tr><td>Riwayat Diabetes</td><td>:</td><td><?php echo $result['diabetes']; ?></td></tr>
                <tr style="background:#fffbe6;"><td><strong>Keluarga Serumah Bergejala</strong></td><td>:</td><td><strong><?php echo $result['anggota_keluarga_sakit'] ?? 'Tidak'; ?></strong></td></tr>
            </table>

            <div class="cert-result-box <?php echo $is_risk ? 'risk' : ''; ?>">
                <h4><?php echo $is_risk ? '⚠ REKOMENDASI: PERIKSAKAN DIRI KE RSUD/PUSKESMAS TERDEKAT' : '✓ HASIL: TIDAK DITEMUKAN GEJALA KHAS TUBERKULOSIS'; ?></h4>
                <p><?php echo $is_risk
                    ? 'Berdasarkan hasil skrining, ditemukan indikator gejala yang berpotensi Tuberkulosis. Peserta diwajibkan untuk segera memeriksakan diri ke Fasilitas Layanan Kesehatan terdekat.'
                    : 'Berdasarkan hasil skrining mandiri, peserta tidak menunjukkan gejala khas Tuberkulosis. Tetap jaga kesehatan dan lakukan pemeriksaan berkala.';
                ?></p>
            </div>

            <p class="cert-note">* <?php echo htmlspecialchars($disclaimer_text); ?></p>

            <div class="cert-sign-area">
                <div class="cert-sign-block">
                    <p style="font-size:11px;">Pemegang Dokumen,</p>
                    <div class="cert-sign-line"><?php echo htmlspecialchars($result['nama']); ?><br>Peserta Skrining</div>
                </div>
                <div class="cert-sign-block">
                    <p style="font-size:11px;"><?php echo htmlspecialchars($instansi_kota); ?>, <?php echo date('d F Y', strtotime($result['created_at'])); ?></p>
                    <div class="cert-sign-line"><?php echo htmlspecialchars($petugas_jabatan); ?><br><?php echo htmlspecialchars($instansi_nama); ?></div>
                </div>
            </div>

            <div class="cert-id">No. Dokumen: <?php echo $cert_id; ?> &nbsp;|&nbsp; Diterbitkan: <?php echo date('d/m/Y H:i', strtotime($result['created_at'])); ?> &nbsp;|&nbsp; Kunjungan ke-<?php echo $visit_count; ?></div>
        </div>

        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer style="border-top:1px solid #e5e7eb;padding:24px;text-align:center;margin-top:48px;">
        <p class="mono" style="font-size:10px;color:#bbb;letter-spacing:0.06em;text-transform:uppercase;">
            Crafted by <a href="https://zorostudio.id" target="_blank" style="color:#111;font-weight:700;text-decoration:none;border-bottom:1px solid #111;">Zoro Studio</a>
            &nbsp;&mdash;&nbsp; Building digital solutions that matter.
        </p>
    </footer>

    <script>
        function doPrint() { window.print(); }
        function openMaps(e) {
            e.preventDefault();
            const q = encodeURIComponent('Puskesmas terdekat');
            if(navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => window.open(`https://www.google.com/maps/search/${q}/@${pos.coords.latitude},${pos.coords.longitude},14z`,'_blank'),
                    ()  => window.open(`https://www.google.com/maps/search/${q}`,'_blank')
                );
            } else {
                window.open(`https://www.google.com/maps/search/${q}`,'_blank');
            }
        }
        // Auto-format NIK input (numbers only, max 16)
        document.querySelector('input[name="nik"]').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g,'').slice(0,16);
        });
    </script>
</body>
</html>
