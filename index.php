<?php
require_once 'db.php';
try {
    $all_s = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
    $cfg = [];
    foreach($all_s as $s) $cfg[$s['setting_key']] = $s['setting_value'];
    $logo_path       = $cfg['logo_path']       ?? 'logo.png';
    $logo2_path      = $cfg['logo2_path']      ?? '';
    $instansi_nama   = $cfg['instansi_nama']   ?? 'DINAS KESEHATAN PROVINSI LAMPUNG';
    $instansi_program= $cfg['instansi_program']?? 'Program Pengendalian Tuberkulosis (TB)';
    $instansi_alamat = $cfg['instansi_alamat'] ?? 'Jl. Dr. Susilo No.46, Bandar Lampung &bull; Telp. (0721) 123456';
    $instansi_kota   = $cfg['instansi_kota']   ?? 'Bandar Lampung';
    $petugas_jabatan = $cfg['petugas_jabatan'] ?? 'Petugas / Sistem Skrining TB';
    $disclaimer_text = $cfg['disclaimer_text'] ?? 'Surat keterangan ini bukan merupakan diagnosis medis.';

    // Counter unik per NIK untuk splash screen
    $total_skrining = (int) $db->query("SELECT COUNT(DISTINCT nik) FROM skrining")->fetchColumn();
} catch (Exception $e) {
    $logo_path = 'logo.png';
    $logo2_path = '';
    $instansi_nama = 'DINAS KESEHATAN PROVINSI LAMPUNG';
    $instansi_program = 'Program Pengendalian Tuberkulosis (TB)';
    $instansi_alamat = 'Jl. Dr. Susilo No.46, Bandar Lampung';
    $instansi_kota = 'Bandar Lampung';
    $petugas_jabatan = 'Petugas / Sistem Skrining TB';
    $disclaimer_text = 'Surat keterangan ini bukan merupakan diagnosis medis.';
    $total_skrining = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skrining Tuberkulosis Lampung</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --black: #000000;
            --white: #ffffff;
            --glass-dark: rgba(0, 0, 0, 0.08);
            --glass-light: rgba(255, 255, 255, 0.16);
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--white);
            color: var(--black);
            letter-spacing: -0.02em; 
            font-feature-settings: "kern" 1;
        }

        .font-mono-tag {
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .fw-light { font-weight: 300; }
        .fw-regular { font-weight: 400; }
        .fw-medium { font-weight: 500; }
        .fw-bold { font-weight: 700; }

        .hero-gradient {
            background: linear-gradient(120deg, #10B981, #FBBF24, #EC4899, #8B5CF6);
            background-size: 200% 200%;
            animation: gradientShift 5s ease infinite;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        input:focus, select:focus, button:focus, label:focus-within {
            outline: 2px dashed var(--black);
            outline-offset: 4px;
        }

        .input-minimal {
            border: none;
            border-bottom: 2px solid var(--black);
            border-radius: 0;
            background: transparent;
            padding: 12px 0;
            font-size: 1.5rem;
            font-weight: 300;
            transition: all 0.2s;
            width: 100%;
        }
        .input-minimal:focus {
            outline: none;
            border-bottom: 2px dashed var(--black);
        }

        .btn-pill-black {
            background: var(--black);
            color: var(--white);
            border-radius: 50px;
            padding: 12px 32px;
            font-weight: 500;
            transition: transform 0.1s;
        }
        .btn-pill-black:active { transform: scale(0.98); }
        .btn-pill-black:disabled { opacity: 0.3; cursor: not-allowed; }

        .radio-pill-label {
            display: block;
            border: 2px solid var(--black);
            border-radius: 50px;
            padding: 16px 24px;
            text-align: center;
            font-size: 1.25rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--white);
            color: var(--black);
        }
        input[type="radio"]:checked + .radio-pill-label {
            background: var(--black);
            color: var(--white);
        }
        input[type="radio"]:focus + .radio-pill-label {
            outline: 2px dashed var(--black);
            outline-offset: 4px;
        }

        #progress-container {
            height: 4px;
            background: var(--glass-dark);
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 40;
        }
        #progress-bar {
            height: 100%;
            background: var(--black);
            width: 0%;
            transition: width 0.3s ease;
        }

        .step { display: none; }
        .step.active { 
            display: block; 
            animation: fadeIn 0.4s ease forwards; 
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-zoom {
            animation: zoomInOut 1.5s ease-in-out infinite;
        }
        @keyframes zoomInOut {
            0%   { transform: scale(0.9); }
            50%  { transform: scale(1.05); }
            100% { transform: scale(0.9); }
        }
        .btn-pulse {
            animation: zoomInOut 1.2s ease-in-out infinite;
            box-shadow: 0 0 0 0 rgba(22,163,74,0.5);
        }

        @media print {
            @page { size: A4; margin: 20mm; }
            body * { visibility: hidden; }
            #print-certificate { display: block !important; visibility: visible !important; position: fixed; left: 0; top: 0; width: 100%; }
            #print-certificate * { visibility: visible !important; }
        }

        /* Print Certificate Styles */
        #print-certificate {
            display: none;
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            background: #fff;
        }
        .cert-header { border-bottom: 4px double #000; padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 16px; }
        .cert-logo { width: 80px; height: 80px; object-fit: contain; }
        .cert-logo-placeholder { width: 80px; height: 80px; border: 2px solid #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; text-align: center; }
        .cert-title-block { flex: 1; text-align: center; }
        .cert-title-block h1 { font-size: 15px; font-weight: bold; margin: 0 0 2px 0; text-transform: uppercase; letter-spacing: 1px; }
        .cert-title-block h2 { font-size: 13px; font-weight: bold; margin: 0 0 2px 0; }
        .cert-title-block p { font-size: 11px; margin: 0; }
        .cert-doc-title { text-align: center; margin: 20px 0 16px; }
        .cert-doc-title h3 { font-size: 14px; font-weight: bold; text-transform: uppercase; text-decoration: underline; letter-spacing: 2px; }
        .cert-doc-title p { font-size: 11px; margin-top: 4px; }
        .cert-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
        .cert-table td { padding: 5px 8px; vertical-align: top; }
        .cert-table td:first-child { width: 40%; font-weight: normal; }
        .cert-table td:nth-child(2) { width: 4%; }
        .cert-result-box { border: 2px solid #000; padding: 16px 20px; margin: 16px 0; }
        .cert-result-box.risk { background: #000; color: #fff; }
        .cert-result-box h4 { font-size: 13px; font-weight: bold; margin: 0 0 6px 0; text-transform: uppercase; }
        .cert-result-box p { font-size: 11px; margin: 0; }
        .cert-note { font-size: 10px; border-top: 1px solid #ccc; padding-top: 10px; margin-top: 10px; font-style: italic; }
        .cert-sign-area { display: flex; justify-content: space-between; margin-top: 24px; font-size: 11px; }
        .cert-sign-block { text-align: center; width: 45%; }
        .cert-sign-line { border-top: 1px solid #000; margin-top: 60px; padding-top: 4px; }
        .cert-id { text-align: center; font-size: 9px; color: #666; margin-top: 16px; border-top: 1px dashed #ccc; padding-top: 8px; font-family: 'Courier New', monospace; }

        /* Risk Meter */
        #risk-meter-wrap { position: fixed; bottom: 80px; right: 16px; z-index: 50; display: none; flex-direction: column; align-items: center; gap: 4px; }
        #risk-meter-bar { width: 8px; height: 80px; background: #e5e7eb; border-radius: 50px; overflow: hidden; border: 1px solid #000; }
        #risk-meter-fill { width: 100%; height: 0%; background: #000; border-radius: 50px; transition: height 0.5s ease, background 0.3s; margin-top: auto; }
        #risk-meter-label { font-family: 'JetBrains Mono', monospace; font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; writing-mode: vertical-rl; color: #666; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- SPLASH SCREEN -->
    <div id="splash-screen" class="fixed inset-0 hero-gradient z-50 flex flex-col items-center justify-center transition-opacity duration-700">
        <?php if(!empty($logo_path) && file_exists($logo_path)): ?>
        <div class="flex items-center justify-center gap-4 mb-8">
            <div class="bg-white p-3 rounded-full shadow-2xl">
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo 1" class="w-24 h-24 animate-zoom object-contain rounded-full">
            </div>
            <?php if(!empty($logo2_path) && file_exists($logo2_path)): ?>
            <div class="w-px h-16 bg-white opacity-30"></div>
            <div class="bg-white p-3 rounded-full shadow-2xl">
                <img src="<?php echo htmlspecialchars($logo2_path); ?>" alt="Logo 2" class="w-24 h-24 animate-zoom object-contain rounded-full" style="animation-delay:0.15s">
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <h1 class="text-white text-5xl md:text-7xl font-bold tracking-tight text-center px-4" style="letter-spacing: -0.05em;">
            Skrining TB.
        </h1>
        <p class="text-white text-xl mt-4 fw-light opacity-90">Provinsi Lampung</p>
        <div class="mt-6 bg-white bg-opacity-20 border border-white border-opacity-40 rounded-full px-5 py-2 font-mono-tag text-white text-xs">
            <span id="splash-counter"><?php echo $total_skrining; ?></span> orang telah diperiksa
        </div>
    </div>

    <!-- Progress Bar -->
    <div id="progress-container">
        <div id="progress-bar"></div>
    </div>

    <nav class="w-full px-4 md:px-6 py-3 md:py-5 flex justify-between items-center fixed top-0 z-30 bg-white border-b border-gray-100" style="display: none;" id="top-nav">
        <!-- Logo Area -->
        <div class="flex items-center gap-2">
            <?php if(!empty($logo_path) && file_exists($logo_path)): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" class="w-9 h-9 md:w-12 md:h-12 rounded-full object-contain">
            <?php else: ?>
                <div class="w-9 h-9 md:w-12 md:h-12 bg-black rounded-full"></div>
            <?php endif; ?>
            <?php if(!empty($logo2_path) && file_exists($logo2_path)): ?>
                <div class="w-px h-7 bg-gray-200"></div>
                <img src="<?php echo htmlspecialchars($logo2_path); ?>" alt="Logo 2" class="w-9 h-9 md:w-12 md:h-12 rounded-full object-contain">
            <?php endif; ?>
            <span class="font-bold text-base tracking-tight ml-1 hidden md:inline">Skrining TB</span>
        </div>
        <!-- Right Side -->
        <div class="flex items-center gap-2 md:gap-4">
            <!-- Mobile: icon only | Desktop: full text -->
            <a href="cek-hasil.php" class="font-mono-tag border-2 border-black text-black hover:bg-black hover:text-white transition-colors rounded-full flex items-center gap-1 px-3 py-1.5 md:px-4 md:py-2" style="font-size:10px;">
                <i class="fas fa-search" style="font-size:9px;"></i> Cek Hasil Saya
            </a>
            <div class="font-mono-tag text-xs text-gray-400 whitespace-nowrap" id="step-counter">01 / 17</div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="flex-grow flex flex-col justify-center max-w-3xl mx-auto w-full px-6 pt-24 pb-32">
        <form id="skrining-form" class="w-full max-w-2xl mx-auto">

            <!-- STEP 1 -->
            <div class="step active" id="step-1">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Identitas Diri</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Siapa nama lengkap Anda?</label>
                <input type="text" name="nama" required placeholder="Ketik nama Anda..." class="input-minimal">
                <p class="text-xs text-gray-400 mt-4 font-mono-tag">Data Anda dilindungi & hanya digunakan untuk keperluan skrining kesehatan.</p>
            </div>

            <!-- STEP 2 -->
            <div class="step" id="step-2">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Identitas Diri</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Berapa NIK KTP Anda?</label>
                <div style="position:relative;">
                    <input type="text" name="nik" id="input-nik" required
                           placeholder="16 digit angka KTP"
                           maxlength="16"
                           inputmode="numeric"
                           pattern="[0-9]{16}"
                           class="input-minimal"
                           style="letter-spacing:0.08em;">
                    <span id="nik-counter" class="font-mono-tag" style="position:absolute;right:0;bottom:14px;font-size:10px;color:#bbb;">0 / 16</span>
                </div>
                <p class="font-mono-tag text-xs text-gray-400 mt-3">Harus tepat 16 digit sesuai KTP.</p>
            </div>

            <!-- STEP 3 -->
            <div class="step" id="step-3">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Identitas Diri</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Berapa usia Anda saat ini?</label>
                <input type="number" name="usia" required placeholder="Misal: 25" class="input-minimal">
            </div>

            <!-- STEP 4 -->
            <div class="step" id="step-4">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Identitas Diri</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Pilih jenis kelamin Anda</label>
                <div class="space-y-4">
                    <div class="relative">
                        <input type="radio" name="jenis_kelamin" id="jk_l" value="Laki-laki" class="sr-only" required>
                        <label for="jk_l" class="radio-pill-label">Laki-laki</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="jenis_kelamin" id="jk_p" value="Perempuan" class="sr-only">
                        <label for="jk_p" class="radio-pill-label">Perempuan</label>
                    </div>
                </div>
            </div>

            <!-- STEP 5 -->
            <div class="step" id="step-5">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Identitas Diri</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apa kewarganegaraan Anda?</label>
                <div class="space-y-4">
                    <div class="relative">
                        <input type="radio" name="kewarganegaraan" id="kw_wni" value="WNI" class="sr-only" required>
                        <label for="kw_wni" class="radio-pill-label">WNI</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="kewarganegaraan" id="kw_wna" value="WNA" class="sr-only">
                        <label for="kw_wna" class="radio-pill-label">WNA</label>
                    </div>
                </div>
            </div>

            <!-- STEP 6 -->
            <div class="step" id="step-6">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Kontak</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-4 leading-tight">Nomor WhatsApp Anda?</label>
                <p class="text-gray-500 mb-8 fw-light">Hasil skrining otomatis dikirim ke nomor ini via WhatsApp.</p>
                <div style="display:flex;align-items:flex-end;gap:8px;">
                    <span class="font-mono-tag" style="font-size:1.4rem;font-weight:600;padding-bottom:14px;color:#555;">+62</span>
                    <input type="tel" name="no_whatsapp" id="input-wa" required
                           placeholder="8xxxxxxxxxx"
                           inputmode="numeric"
                           class="input-minimal" style="flex:1;">
                </div>
                <p class="font-mono-tag text-xs text-gray-400 mt-3">Contoh: 812 3456 7890 &nbsp;(tanpa angka 0 di depan)</p>
            </div>

            <!-- STEP 7 -->
            <div class="step" id="step-7">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Perjalanan</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apa kota tujuan Anda di Lampung?</label>
                <input type="text" name="tujuan_lampung" id="input-kota" required
                       placeholder="Pilih atau ketik kota tujuan"
                       list="list-kota-lampung"
                       class="input-minimal" autocomplete="off">
                <datalist id="list-kota-lampung">
                    <option value="Bandar Lampung">
                    <option value="Metro">
                    <option value="Lampung Selatan">
                    <option value="Lampung Utara">
                    <option value="Lampung Tengah">
                    <option value="Lampung Barat">
                    <option value="Lampung Timur">
                    <option value="Pesawaran">
                    <option value="Pringsewu">
                    <option value="Tanggamus">
                    <option value="Tulang Bawang">
                    <option value="Tulang Bawang Barat">
                    <option value="Mesuji">
                    <option value="Way Kanan">
                    <option value="Pesisir Barat">
                </datalist>
                <p class="font-mono-tag text-xs text-gray-400 mt-3">Pilih dari daftar atau ketik nama kota lain.</p>
            </div>

            <!-- STEP 8 -->
            <div class="step" id="step-8">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Perjalanan</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apa keperluan Anda?</label>
                <select name="keperluan" required class="input-minimal">
                    <option value="" disabled selected>Pilih keperluan...</option>
                    <option value="Pendidikan">Pendidikan</option>
                    <option value="Pekerjaan">Pekerjaan</option>
                    <option value="Berlibur">Berlibur</option>
                    <option value="Tempat tinggal di Lampung">Tempat tinggal di Lampung</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>

            <!-- STEP 9 -->
            <div class="step" id="step-9">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Perjalanan</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Berapa hari Anda di Lampung?</label>
                <input type="number" name="lama_tinggal" required placeholder="Jumlah hari" class="input-minimal">
            </div>

            <!-- STEP 10 -->
            <div class="step" id="step-10">
                <span class="font-mono-tag text-sm mb-6 block px-3 py-1 border-2 border-black rounded-full inline-block">SKRINING UTAMA</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apakah Anda mengalami batuk lebih dari 2 minggu?</label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <input type="radio" name="batuk_2_minggu" id="b_ya" value="Ya" class="sr-only" required>
                        <label for="b_ya" class="radio-pill-label">Ya</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="batuk_2_minggu" id="b_tidak" value="Tidak" class="sr-only">
                        <label for="b_tidak" class="radio-pill-label">Tidak</label>
                    </div>
                </div>
            </div>

            <!-- STEP 11 -->
            <div class="step" id="step-11">
                <span class="font-mono-tag text-sm mb-6 block px-3 py-1 border-2 border-black rounded-full inline-block">SKRINING UTAMA</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apakah berat badan Anda menurun drastis tanpa sebab?</label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <input type="radio" name="penurunan_bb" id="bb_ya" value="Ya" class="sr-only" required>
                        <label for="bb_ya" class="radio-pill-label">Ya</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="penurunan_bb" id="bb_tidak" value="Tidak" class="sr-only">
                        <label for="bb_tidak" class="radio-pill-label">Tidak</label>
                    </div>
                </div>
            </div>

            <!-- STEP 12 -->
            <div class="step" id="step-12">
                <span class="font-mono-tag text-sm mb-6 block px-3 py-1 border-2 border-black rounded-full inline-block">SKRINING UTAMA</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apakah Anda sering berkeringat di malam hari tanpa sebab?</label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <input type="radio" name="keringat_malam" id="km_ya" value="Ya" class="sr-only" required>
                        <label for="km_ya" class="radio-pill-label">Ya</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="keringat_malam" id="km_tidak" value="Tidak" class="sr-only">
                        <label for="km_tidak" class="radio-pill-label">Tidak</label>
                    </div>
                </div>
            </div>

            <!-- STEP 13 -->
            <div class="step" id="step-13">
                <span class="font-mono-tag text-sm mb-6 block px-3 py-1 border-2 border-black rounded-full inline-block">SKRINING UTAMA</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apakah Anda memiliki riwayat HIV-AIDS?</label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <input type="radio" name="hiv_aids" id="hiv_ya" value="Ya" class="sr-only" required>
                        <label for="hiv_ya" class="radio-pill-label">Ya</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="hiv_aids" id="hiv_tidak" value="Tidak" class="sr-only">
                        <label for="hiv_tidak" class="radio-pill-label">Tidak</label>
                    </div>
                </div>
            </div>

            <!-- STEP 14 -->
            <div class="step" id="step-14">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Gejala Tambahan</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apakah Anda mengalami demam lebih dari 2 minggu?</label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <input type="radio" name="demam_2_minggu" id="dm_ya" value="Ya" class="sr-only" required>
                        <label for="dm_ya" class="radio-pill-label">Ya</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="demam_2_minggu" id="dm_tidak" value="Tidak" class="sr-only">
                        <label for="dm_tidak" class="radio-pill-label">Tidak</label>
                    </div>
                </div>
            </div>

            <!-- STEP 15 -->
            <div class="step" id="step-15">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Gejala Tambahan</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Pernah kontak erat dengan penderita TB?</label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <input type="radio" name="kontak_tb" id="kt_ya" value="Ya" class="sr-only" required>
                        <label for="kt_ya" class="radio-pill-label">Ya</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="kontak_tb" id="kt_tidak" value="Tidak" class="sr-only">
                        <label for="kt_tidak" class="radio-pill-label">Tidak</label>
                    </div>
                </div>
            </div>

            <!-- STEP 16 -->
            <div class="step" id="step-16">
                <span class="font-mono-tag text-sm text-gray-500 mb-6 block">Gejala Tambahan</span>
                <label class="block text-3xl md:text-4xl fw-medium mb-8 leading-tight">Apakah Anda memiliki riwayat Diabetes?</label>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <input type="radio" name="diabetes" id="diab_ya" value="Ya" class="sr-only" required>
                        <label for="diab_ya" class="radio-pill-label">Ya</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="diabetes" id="diab_tidak" value="Tidak" class="sr-only">
                        <label for="diab_tidak" class="radio-pill-label">Tidak</label>
                    </div>
                </div>
            </div>

            <!-- STEP 17 - PERTANYAAN EMAS BARU -->
            <div class="step" id="step-17">
                <label class="block text-3xl md:text-4xl fw-medium mb-4 leading-tight">Apakah ada anggota keluarga serumah yang menderita gejala serupa?</label>
                <p class="text-sm text-gray-500 mb-8 font-light leading-relaxed">Seperti: batuk &gt; 2 minggu, penurunan berat badan drastis, keringat malam, atau mengidap HIV/AIDS.</p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <input type="radio" name="anggota_keluarga_sakit" id="aks_ya" value="Ya" class="sr-only" required>
                        <label for="aks_ya" class="radio-pill-label">Ya</label>
                    </div>
                    <div class="relative">
                        <input type="radio" name="anggota_keluarga_sakit" id="aks_tidak" value="Tidak" class="sr-only">
                        <label for="aks_tidak" class="radio-pill-label">Tidak</label>
                    </div>
                </div>
            </div>

        </form>

        <!-- RESULT STEP (Out of form constraints to allow massive card design) -->
        <div class="step w-full" id="step-result">
            <div id="loading" class="hidden flex-col items-center justify-center py-20 max-w-lg mx-auto w-full">
                <div class="w-12 h-12 border-4 border-black border-t-transparent rounded-full animate-spin mb-8"></div>
                <div class="w-full bg-gray-50 border-2 border-black rounded-xl p-6 text-left shadow-[4px_4px_0px_0px_rgba(0,0,0,1)]">
                    <p class="text-sm font-mono-tag mb-4 text-gray-500">SYSTEM.ANALYSIS_INITIATED</p>
                    <div id="loading-steps" class="font-mono-tag text-xs space-y-3">
                        <div class="step-item opacity-0 flex items-center"><i class="fas fa-check-circle mr-3"></i> Memvalidasi Identitas & NIK...</div>
                        <div class="step-item opacity-0 flex items-center"><i class="fas fa-check-circle mr-3"></i> Menganalisis Riwayat Perjalanan...</div>
                        <div class="step-item opacity-0 flex items-center"><i class="fas fa-check-circle mr-3"></i> Mengevaluasi Indikator Klinis...</div>
                        <div class="step-item opacity-0 flex items-center"><i class="fas fa-check-circle mr-3"></i> Menghitung Kalkulasi Risiko...</div>
                        <div class="step-item opacity-0 flex items-center"><i class="fas fa-check-circle mr-3"></i> Menyusun Kesimpulan Akhir...</div>
                    </div>
                </div>
            </div>
            
            <div id="result-content" class="hidden w-full transition-all duration-500">
                <div id="result-card" class="rounded-[40px] p-10 md:p-16 text-center mx-auto w-full max-w-2xl relative overflow-hidden">
                    <div id="result-icon-container" class="w-24 h-24 mx-auto rounded-full flex items-center justify-center mb-8 shadow-sm">
                        <i id="result-icon" class="text-4xl"></i>
                    </div>
                    <span id="result-status-tag" class="font-mono-tag text-xs px-3 py-1 rounded-full mb-6 inline-block"></span>
                    <h2 id="result-title" class="text-4xl md:text-6xl fw-bold mb-6 tracking-tight leading-tight"></h2>
                    <p id="result-desc" class="text-xl md:text-2xl fw-light mb-12 opacity-90"></p>
                    <button type="button" onclick="location.reload()" id="btn-result" class="w-full transition-transform active:scale-[0.98]">
                        Kembali ke Awal
                    </button>
                    <a href="#" id="btn-maps" class="hidden justify-center items-center w-full mt-4 bg-white text-black border-2 border-white rounded-full px-8 py-4 font-medium text-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-map-marker-alt mr-2 text-red-500"></i> Cari Puskesmas Terdekat
                    </a>
                    <button type="button" onclick="doPrint()" id="btn-print" class="w-full mt-4 bg-transparent border-2 border-current rounded-full px-8 py-4 font-medium text-lg hover:opacity-50 transition-opacity">
                        <i class="fas fa-print mr-2"></i> Cetak / Simpan PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- PRINT CERTIFICATE (hidden, shown only on print) -->
        <!-- Risk Meter Widget -->
        <div id="risk-meter-wrap">
            <span id="risk-meter-label">Risiko</span>
            <div id="risk-meter-bar"><div id="risk-meter-fill"></div></div>
            <span class="font-mono-tag text-xs" id="risk-meter-pct">0%</span>
        </div>

        <div id="print-certificate">
            <div class="cert-header">
                <div style="display:flex;align-items:center;gap:10px;">
                <?php if(!empty($logo_path) && file_exists($logo_path)): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" class="cert-logo">
                <?php else: ?>
                <div class="cert-logo-placeholder">LOGO<br>INSTANSI</div>
                <?php endif; ?>
                <?php if(!empty($logo2_path) && file_exists($logo2_path)): ?>
                <img src="<?php echo htmlspecialchars($logo2_path); ?>" alt="Logo 2" class="cert-logo">
                <?php endif; ?>
                </div>
                <div class="cert-title-block">
                    <h1><?php echo htmlspecialchars($instansi_nama); ?></h1>
                    <h2><?php echo htmlspecialchars($instansi_program); ?></h2>
                    <p><?php echo $instansi_alamat; ?></p>
                </div>
                <div style="width:80px"></div>
            </div>

            <div class="cert-doc-title">
                <h3>Surat Keterangan Hasil Skrining Tuberkulosis</h3>
                <p>Dokumen ini diterbitkan secara otomatis oleh Sistem Skrining TB Digital</p>
            </div>

            <p style="font-size:12px;margin-bottom:10px;">Yang bertanda tangan di bawah ini menerangkan bahwa:</p>

            <table class="cert-table">
                <tr><td>Nama Lengkap</td><td>:</td><td id="p-nama" style="font-weight:bold"></td></tr>
                <tr><td>NIK</td><td>:</td><td id="p-nik"></td></tr>
                <tr><td>Usia / Jenis Kelamin</td><td>:</td><td id="p-usia-jk"></td></tr>
                <tr><td>Kewarganegaraan</td><td>:</td><td id="p-wn"></td></tr>
                <tr><td>No. WhatsApp</td><td>:</td><td id="p-wa"></td></tr>
                <tr><td colspan="3" style="padding-top:8px;font-weight:bold;border-top:1px solid #ddd;">Data Perjalanan</td></tr>
                <tr><td>Kota Tujuan di Lampung</td><td>:</td><td id="p-tujuan"></td></tr>
                <tr><td>Keperluan</td><td>:</td><td id="p-keperluan"></td></tr>
                <tr><td>Lama Tinggal</td><td>:</td><td id="p-lama"></td></tr>
                <tr><td colspan="3" style="padding-top:8px;font-weight:bold;border-top:1px solid #ddd;">Keluhan yang Dilaporkan</td></tr>
                <tr><td>Batuk &gt; 2 Minggu</td><td>:</td><td id="p-batuk"></td></tr>
                <tr><td>Penurunan Berat Badan</td><td>:</td><td id="p-bb"></td></tr>
                <tr><td>Keringat Malam</td><td>:</td><td id="p-keringat"></td></tr>
                <tr><td>HIV-AIDS</td><td>:</td><td id="p-hiv"></td></tr>
                <tr><td>Demam &gt; 2 Minggu</td><td>:</td><td id="p-demam"></td></tr>
                <tr><td>Kontak Erat Penderita TB</td><td>:</td><td id="p-kontak"></td></tr>
                <tr><td>Riwayat Diabetes</td><td>:</td><td id="p-dm"></td></tr>
                <tr style="background:#fffbe6"><td><strong>Keluarga Serumah Bergejala Serupa</strong></td><td>:</td><td id="p-aks" style="font-weight:bold"></td></tr>
            </table>

            <div id="cert-result-box" class="cert-result-box">
                <h4 id="p-hasil-title"></h4>
                <p id="p-hasil-desc"></p>
            </div>

            <p class="cert-note">* <?php echo htmlspecialchars($disclaimer_text); ?></p>

            <div class="cert-sign-area">
                <div class="cert-sign-block">
                    <p>Pemegang Dokumen,</p>
                    <div class="cert-sign-line"><span id="p-nama-ttd"></span><br>Peserta Skrining</div>
                </div>
                <div class="cert-sign-block">
                    <p id="p-tanggal-ttd"></p>
                    <div class="cert-sign-line"><?php echo htmlspecialchars($petugas_jabatan); ?><br><?php echo htmlspecialchars($instansi_nama); ?></div>
                </div>
            </div>


            <div class="cert-id" id="p-cert-id"></div>
        </div>

    </main>

    <!-- Bottom Navigation Bar -->
    <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-100 p-4" id="nav-buttons" style="display: none; z-index: 30;">
        <div class="max-w-2xl mx-auto flex justify-between items-center">
            <button type="button" id="btn-prev" class="invisible fw-medium text-gray-500 hover:text-black py-2 px-4 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i> Sebelumnya
            </button>
            <button type="button" id="btn-next" class="btn-pill-black flex items-center">
                Lanjut <i class="fas fa-arrow-right ml-2"></i>
            </button>
            <button type="submit" form="skrining-form" id="btn-submit" class="hidden btn-pill-black flex items-center bg-green-600 border-none hover:bg-green-700">
                Kirim Data <i class="fas fa-check ml-2"></i>
            </button>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            setTimeout(() => {
                const splash = document.getElementById('splash-screen');
                splash.classList.add('opacity-0');
                setTimeout(() => {
                    splash.style.display = 'none';
                    document.getElementById('nav-buttons').style.display = 'block';
                    document.getElementById('top-nav').style.display = 'flex';
                }, 700);
            }, 2000);
        });

        let currentStep = 1;
        const totalSteps = 17;
        
        const updateUI = () => {
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            document.getElementById(`step-${currentStep}`).classList.add('active');
            
            const progress = (currentStep / totalSteps) * 100;
            document.getElementById('progress-bar').style.width = `${progress}%`;
            document.getElementById('step-counter').innerText = `${String(currentStep).padStart(2, '0')} / ${totalSteps}`;

            const input = document.querySelector(`#step-${currentStep} input:not([type="radio"]), #step-${currentStep} select`);
            if(input) { setTimeout(() => input.focus(), 100); }

            const btnPrev = document.getElementById('btn-prev');
            const btnNext = document.getElementById('btn-next');
            const btnSubmit = document.getElementById('btn-submit');
            if (currentStep === totalSteps) {
                btnNext.classList.add('hidden');
                btnSubmit.classList.remove('hidden');
                // Start pulse animation to hint user to click
                setTimeout(() => btnSubmit.classList.add('btn-pulse'), 300);
            } else {
                btnSubmit.classList.remove('hidden');
                btnSubmit.classList.add('hidden');
                btnSubmit.classList.remove('btn-pulse');
            }

            if (currentStep === 1) btnPrev.classList.add('invisible');
            else btnPrev.classList.remove('invisible');

            if (currentStep === totalSteps) {
                btnNext.classList.add('hidden');
                btnSubmit.classList.remove('hidden');
            } else {
                btnNext.classList.remove('hidden');
                btnSubmit.classList.add('hidden');
            }
        };

        const validateStep = () => {
            const stepEl = document.getElementById(`step-${currentStep}`);
            const inputs = stepEl.querySelectorAll('input[required], select[required]');
            let isValid = true;

            const radios = stepEl.querySelectorAll('input[type="radio"]');
            if (radios.length > 0) {
                const radioName = radios[0].name;
                const checked = document.querySelector(`input[name="${radioName}"]:checked`);
                if (!checked) isValid = false;
            } else {
                inputs.forEach(input => {
                    if (!input.value) {
                        isValid = false;
                        input.style.borderBottomColor = 'red';
                    } else {
                        input.style.borderBottomColor = 'var(--black)';
                    }
                });
            }

            // Extra: NIK harus tepat 16 digit
            if (currentStep === 2) {
                const nikEl = document.getElementById('input-nik');
                if (nikEl && nikEl.value.replace(/\D/g,'').length !== 16) {
                    nikEl.style.borderBottomColor = 'red';
                    if (nikCounter) {
                        nikCounter.style.color = '#dc2626';
                        nikCounter.textContent = nikEl.value.length + ' / 16 — harus tepat 16 digit';
                    }
                    isValid = false;
                }
            }

            // Extra: WA minimal 9 digit
            if (currentStep === 6) {
                const waEl = document.getElementById('input-wa');
                if (waEl && waEl.value.replace(/\D/g,'').length < 9) {
                    waEl.style.borderBottomColor = 'red';
                    isValid = false;
                }
            }

            return isValid;
        };

        document.getElementById('btn-next').addEventListener('click', () => {
            if (validateStep()) {
                currentStep++;
                updateUI();
            }
        });

        document.getElementById('btn-prev').addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                updateUI();
            }
        });

        // Risk-scoring question names (the 4 golden + 3 additional)
        const riskFields = ['batuk_2_minggu','penurunan_bb','keringat_malam','hiv_aids','demam_2_minggu','kontak_tb','diabetes','anggota_keluarga_sakit'];
        const maxRisk = riskFields.length;

        function updateRiskMeter() {
            let score = 0;
            riskFields.forEach(name => {
                const checked = document.querySelector(`input[name="${name}"]:checked`);
                if (checked && checked.value === 'Ya') score++;
            });
            const pct = Math.round((score / maxRisk) * 100);
            const fill = document.getElementById('risk-meter-fill');
            fill.style.height = pct + '%';
            fill.style.background = score === 0 ? '#000' : score <= 2 ? '#555' : '#000';
            document.getElementById('risk-meter-pct').textContent = pct + '%';
        }

        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updateRiskMeter();
                if(validateStep()) {
                    setTimeout(() => {
                        if (currentStep < totalSteps) {
                            currentStep++;
                            updateUI();
                            // Show risk meter from screening questions onwards
                            if(currentStep >= 10) {
                                document.getElementById('risk-meter-wrap').style.display = 'flex';
                            }
                        }
                    }, 350);
                }
            });
        });

        document.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (document.getElementById('splash-screen').style.display !== 'none') return;
                
                if (currentStep < totalSteps) document.getElementById('btn-next').click();
                else if (currentStep === totalSteps) document.getElementById('btn-submit').click();
            }
        });

        document.getElementById('btn-submit').addEventListener('click', async (e) => {
            e.preventDefault();
            document.getElementById('btn-submit').classList.remove('btn-pulse');
            if (!validateStep()) return;

            document.getElementById('nav-buttons').style.display = 'none';
            document.getElementById('top-nav').style.display = 'none';
            document.getElementById('progress-bar').style.width = '100%';
            
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            document.getElementById('step-result').classList.add('active');
            
            document.getElementById('skrining-form').style.display = 'none';
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('loading').classList.add('flex');

            const steps = document.querySelectorAll('.step-item');
            steps.forEach(s => s.classList.remove('active'));
            for(let i=0; i<steps.length; i++) {
                setTimeout(() => {
                    steps[i].classList.remove('opacity-0');
                    steps[i].style.animation = 'fadeIn 0.5s ease forwards';
                }, i * 600);
            }

            const formData = new FormData(document.getElementById('skrining-form'));

            try {
                // Run fetch and animation timer in parallel
                const [response] = await Promise.all([
                    fetch('process.php', { method: 'POST', body: formData }),
                    new Promise(resolve => setTimeout(resolve, 3000)) // wait for animation
                ]);
                const result = await response.json();
                _formResult = result;

                document.getElementById('loading').classList.add('hidden');
                document.getElementById('loading').classList.remove('flex');
                document.getElementById('result-content').classList.remove('hidden');

                const card = document.getElementById('result-card');
                const iconContainer = document.getElementById('result-icon-container');
                const icon = document.getElementById('result-icon');
                const title = document.getElementById('result-title');
                const desc = document.getElementById('result-desc');
                const btn = document.getElementById('btn-result');
                const tag = document.getElementById('result-status-tag');

                if (result.status === 'success') {
                    if(result.is_risk) {
                        // RISIKO — Deep Crimson
                        card.className = "text-white rounded-[40px] p-10 md:p-16 text-center relative overflow-hidden mx-auto w-full max-w-2xl";
                        card.style.background = "linear-gradient(135deg, #7F1D1D 0%, #991B1B 50%, #7F1D1D 100%)";
                        card.style.outline = "2px dashed rgba(255,255,255,0.4)";
                        card.style.outlineOffset = "-14px";
                        card.style.boxShadow = "0 25px 60px rgba(127,29,29,0.5)";

                        iconContainer.className = "w-24 h-24 mx-auto rounded-full flex items-center justify-center mb-8 shadow-lg";
                        iconContainer.style.background = "rgba(255,255,255,0.15)";
                        iconContainer.style.border = "2px solid rgba(255,255,255,0.4)";
                        icon.className = "fas fa-exclamation-triangle text-4xl text-white";

                        tag.innerText = "⚠ REKOMENDASI PEMERIKSAAN";
                        tag.className = "font-mono-tag text-xs px-4 py-1.5 rounded-full mb-6 inline-block";
                        tag.style.background = "rgba(255,255,255,0.15)";
                        tag.style.border = "1px solid rgba(255,255,255,0.5)";
                        tag.style.color = "#fff";

                        title.innerText = "Periksakan Diri Anda.";
                        title.style.textShadow = "0 2px 8px rgba(0,0,0,0.3)";
                        desc.innerHTML = "Rekomendasi periksakan diri ke<br><b>RSUD / Puskesmas terdekat</b>.";

                        btn.className = "rounded-full px-8 py-4 font-medium text-lg w-full mt-6 transition-all";
                        btn.style.background = "rgba(255,255,255,0.15)";
                        btn.style.border = "2px solid rgba(255,255,255,0.6)";
                        btn.style.color = "#fff";
                        btn.onmouseover = () => { btn.style.background = "rgba(255,255,255,0.25)"; };
                        btn.onmouseout  = () => { btn.style.background = "rgba(255,255,255,0.15)"; };

                        document.getElementById('btn-maps').classList.remove('hidden');
                        document.getElementById('btn-maps').style.display = 'flex';
                        document.getElementById('btn-maps').style.background = "rgba(255,255,255,0.2)";
                        document.getElementById('btn-maps').style.border = "2px solid rgba(255,255,255,0.5)";
                        document.getElementById('btn-maps').style.color = "#fff";
                        document.getElementById('btn-maps').style.borderRadius = "50px";

                    } else {
                        // AMAN — Forest Green
                        card.className = "text-white rounded-[40px] p-10 md:p-16 text-center relative overflow-hidden mx-auto w-full max-w-2xl";
                        card.style.background = "linear-gradient(135deg, #14532D 0%, #166534 50%, #14532D 100%)";
                        card.style.outline = "2px dashed rgba(255,255,255,0.4)";
                        card.style.outlineOffset = "-14px";
                        card.style.boxShadow = "0 25px 60px rgba(20,83,45,0.5)";

                        iconContainer.className = "w-24 h-24 mx-auto rounded-full flex items-center justify-center mb-8 shadow-lg";
                        iconContainer.style.background = "rgba(255,255,255,0.15)";
                        iconContainer.style.border = "2px solid rgba(255,255,255,0.4)";
                        icon.className = "fas fa-check text-5xl text-white";

                        tag.innerText = "✓ BUKAN TB";
                        tag.className = "font-mono-tag text-xs px-4 py-1.5 rounded-full mb-6 inline-block";
                        tag.style.background = "rgba(255,255,255,0.15)";
                        tag.style.border = "1px solid rgba(255,255,255,0.5)";
                        tag.style.color = "#fff";

                        title.innerText = "Tidak Berisiko TB.";
                        title.style.textShadow = "0 2px 8px rgba(0,0,0,0.3)";
                        desc.innerHTML = "Tidak ditemukan gejala khas Tuberkulosis.<br>Hasil telah direkam otomatis.";

                        btn.className = "rounded-full px-8 py-4 font-medium text-lg w-full mt-6 transition-all";
                        btn.style.background = "rgba(255,255,255,0.15)";
                        btn.style.border = "2px solid rgba(255,255,255,0.6)";
                        btn.style.color = "#fff";
                        btn.onmouseover = () => { btn.style.background = "rgba(255,255,255,0.25)"; };
                        btn.onmouseout  = () => { btn.style.background = "rgba(255,255,255,0.15)"; };

                        document.getElementById('btn-maps').classList.add('hidden');
                        setTimeout(launchConfetti, 400);
                    }

                } else {
                    title.innerText = "Error.";
                    desc.innerText = result.message;
                }
            } catch (error) {
                console.error(error);
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('loading').classList.remove('flex');
                alert("Terjadi kesalahan koneksi sistem.");
            }
        });

        // --- PRINT CERTIFICATE LOGIC ---
        let _formResult = {};

        function doPrint() {
            const f = _formResult;
            const fd = document.getElementById('skrining-form');
            const get = (name) => {
                const el = fd.querySelector(`[name="${name}"]`);
                if (!el) { const checked = fd.querySelector(`[name="${name}"]:checked`); return checked ? checked.value : '-'; }
                if (el.tagName === 'SELECT') return el.options[el.selectedIndex]?.text || '-';
                return el.value || '-';
            };
            const getRadio = (name) => { const c = fd.querySelector(`[name="${name}"]:checked`); return c ? c.value : '-'; };

            document.getElementById('p-nama').textContent = get('nama');
            document.getElementById('p-nik').textContent = get('nik');
            document.getElementById('p-usia-jk').textContent = get('usia') + ' tahun / ' + getRadio('jenis_kelamin');
            document.getElementById('p-wn').textContent = getRadio('kewarganegaraan');
            document.getElementById('p-wa').textContent = get('no_whatsapp');
            document.getElementById('p-tujuan').textContent = get('tujuan_lampung');
            document.getElementById('p-keperluan').textContent = get('keperluan');
            document.getElementById('p-lama').textContent = get('lama_tinggal') + ' hari';
            document.getElementById('p-batuk').textContent = getRadio('batuk_2_minggu');
            document.getElementById('p-bb').textContent = getRadio('penurunan_bb');
            document.getElementById('p-keringat').textContent = getRadio('keringat_malam');
            document.getElementById('p-hiv').textContent = getRadio('hiv_aids');
            document.getElementById('p-demam').textContent = getRadio('demam_2_minggu');
            document.getElementById('p-kontak').textContent = getRadio('kontak_tb');
            document.getElementById('p-dm').textContent = getRadio('diabetes');
            document.getElementById('p-aks').textContent = getRadio('anggota_keluarga_sakit');
            document.getElementById('p-nama-ttd').textContent = get('nama');

            const now = new Date();
            const tgl = now.toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'});
            document.getElementById('p-tanggal-ttd').textContent = 'Bandar Lampung, ' + tgl;

            const certId = 'SKR-TB-' + now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0') + '-' + Math.random().toString(36).substr(2,8).toUpperCase();
            document.getElementById('p-cert-id').textContent = 'No. Dokumen: ' + certId + ' | Diterbitkan: ' + now.toLocaleString('id-ID');

            const isRisk = f.is_risk;
            const box = document.getElementById('cert-result-box');
            if (isRisk) {
                box.className = 'cert-result-box risk';
                document.getElementById('p-hasil-title').textContent = '⚠ REKOMENDASI: PERIKSAKAN DIRI KE RSUD/PUSKESMAS TERDEKAT';
                document.getElementById('p-hasil-desc').textContent = 'Berdasarkan hasil skrining, ditemukan indikator gejala yang berpotensi Tuberkulosis. Peserta diwajibkan untuk segera memeriksakan diri ke Fasilitas Layanan Kesehatan terdekat untuk pemeriksaan lebih lanjut.';
            } else {
                box.className = 'cert-result-box';
                document.getElementById('p-hasil-title').textContent = '✓ HASIL: TIDAK DITEMUKAN GEJALA KHAS TUBERKULOSIS';
                document.getElementById('p-hasil-desc').textContent = 'Berdasarkan hasil skrining mandiri, peserta tidak menunjukkan gejala khas Tuberkulosis. Tetap jaga kesehatan, pola makan bergizi, dan lakukan pemeriksaan berkala.';
            }

            window.print();
        }

        // --- GEOLOCATION MAPS ---
        document.getElementById('btn-maps').addEventListener('click', function(e) {
            e.preventDefault();
            const query = encodeURIComponent('Puskesmas terdekat');
            if(navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(pos => {
                    const lat = pos.coords.latitude, lng = pos.coords.longitude;
                    window.open(`https://www.google.com/maps/search/${query}/@${lat},${lng},14z`, '_blank');
                }, () => {
                    window.open(`https://www.google.com/maps/search/${query}`, '_blank');
                });
            } else {
                window.open(`https://www.google.com/maps/search/${query}`, '_blank');
            }
        });

        // --- CONFETTI (Safe Result) ---
        function launchConfetti() {
            const canvas = document.createElement('canvas');
            canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999;';
            document.body.appendChild(canvas);
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            const pieces = Array.from({length: 80}, () => ({
                x: Math.random() * canvas.width,
                y: Math.random() * -canvas.height,
                r: Math.random() * 6 + 4,
                d: Math.random() * 3 + 1,
                color: ['#000','#333','#666','#999','#ccc'][Math.floor(Math.random()*5)],
                tilt: Math.random() * 10 - 10,
                spin: (Math.random() - 0.5) * 0.2
            }));
            let frame = 0;
            const anim = setInterval(() => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                pieces.forEach(p => {
                    p.y += p.d; p.x += Math.sin(frame * 0.05) * 0.8; p.tilt += p.spin;
                    ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = p.color; ctx.fill();
                });
                frame++;
                if(frame > 120) { clearInterval(anim); document.body.removeChild(canvas); }
            }, 16);
        }

        // --- ANIMATED COUNTER on splash ---
        (function() {
            const el = document.getElementById('splash-counter');
            if(!el) return;
            const target = parseInt(el.textContent) || 0;
            let start = Math.max(0, target - 15), current = start;
            const timer = setInterval(() => {
                current++;
                el.textContent = current;
                if(current >= target) clearInterval(timer);
            }, 80);
        })();
        // =============================================
        // INPUT VALIDATION — NIK & WHATSAPP
        // =============================================

        // NIK: angka saja, maks 16 digit, live counter
        const nikInput = document.getElementById('input-nik');
        const nikCounter = document.getElementById('nik-counter');
        if (nikInput) {
            nikInput.addEventListener('input', function () {
                // Hanya angka
                this.value = this.value.replace(/\D/g, '').slice(0, 16);
                const len = this.value.length;
                nikCounter.textContent = len + ' / 16';
                // Warna counter: merah jika kurang, hijau jika pas
                if (len === 16) {
                    nikCounter.style.color = '#16a34a';
                } else if (len > 0) {
                    nikCounter.style.color = '#dc2626';
                } else {
                    nikCounter.style.color = '#bbb';
                }
            });
            nikInput.addEventListener('keypress', function (e) {
                if (!/[0-9]/.test(e.key)) e.preventDefault();
            });
        }

        // WhatsApp: angka saja, tampil +62, kirim format 628xxx ke server
        const waInput = document.getElementById('input-wa');
        if (waInput) {
            waInput.addEventListener('input', function () {
                // Hapus non-digit
                let val = this.value.replace(/\D/g, '');
                // Jika user ketik 0 di depan, hapus (karena +62 sudah prefix)
                if (val.startsWith('0')) val = val.slice(1);
                // Jika user ketik 62 di depan, hapus
                if (val.startsWith('62')) val = val.slice(2);
                this.value = val;
            });
            // Sebelum form submit, ubah value ke format 62xxx untuk Fonnte
            document.getElementById('skrining-form').addEventListener('submit', function () {
                if (waInput.value) {
                    let wa = waInput.value.replace(/\D/g, '');
                    if (wa.startsWith('0')) wa = '62' + wa.slice(1);
                    else if (!wa.startsWith('62')) wa = '62' + wa;
                    waInput.value = wa;
                }
            }, { capture: true });
        }

    </script>
</body>
</html>
