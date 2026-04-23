<?php
session_start();
header('Content-Type: application/json');
if(!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
}
require_once 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// DELETE Record
if($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if($id > 0) {
        $stmt = $db->prepare("DELETE FROM skrining WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status'=>'success','message'=>'Data berhasil dihapus']);
    } else {
        echo json_encode(['status'=>'error','message'=>'ID tidak valid']);
    }
    exit;
}

// RESEND WA
if($action === 'resend_wa') {
    $id = (int)($_POST['id'] ?? 0);
    if($id > 0) {
        $stmt = $db->prepare("SELECT * FROM skrining WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$row) { echo json_encode(['status'=>'error','message'=>'Data tidak ditemukan']); exit; }

        $stmt_token = $db->query("SELECT setting_value FROM settings WHERE setting_key='fonnte_token'");
        $fonnte_token = $stmt_token->fetchColumn() ?: '';
        if(empty($fonnte_token)) { echo json_encode(['status'=>'error','message'=>'Token Fonnte belum diatur']); exit; }

        $is_risk = strpos($row['hasil'], 'Rekomendasi') !== false;
        $nama = $row['nama']; $nik = $row['nik']; $usia = $row['usia'];
        $jenis_kelamin = $row['jenis_kelamin']; $tujuan = $row['tujuan_lampung'];
        $lama = $row['lama_tinggal']; $hasil = $row['hasil'];

        $pesan = "Halo *$nama*,\n\nTerima kasih telah mengisi Kuesioner Skrining Tuberkulosis Provinsi Lampung.\n\n";
        $pesan .= "📋 *REKAP DATA ANDA*\nNIK: $nik\nUsia/JK: $usia thn / $jenis_kelamin\nTujuan: $tujuan ($lama hari)\n\n";
        $pesan .= "*HASIL SKRINING:*\n";
        $pesan .= $is_risk ? "⚠️ *$hasil*\n\nSilakan kunjungi RSUD/Puskesmas terdekat." : "✅ *$hasil*\n\nTetap jaga kesehatan!";
        $pesan .= "\n\n_Pesan otomatis dari Sistem Skrining Dinas Kesehatan._";

        $no_wa = $row['no_whatsapp'];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => ['target'=>$no_wa,'message'=>$pesan,'countryCode'=>'62'],
            CURLOPT_HTTPHEADER => ["Authorization: $fonnte_token"],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response);
        
        if($result && isset($result->status) && $result->status) {
            echo json_encode(['status'=>'success','message'=>'Pesan WA berhasil dikirim ulang']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Gagal kirim. Cek token Fonnte.']);
        }
    } else {
        echo json_encode(['status'=>'error','message'=>'ID tidak valid']);
    }
    exit;
}

// GET Detail
if($action === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    if($id > 0) {
        $stmt = $db->prepare("SELECT * FROM skrining WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) echo json_encode(['status'=>'success','data'=>$row]);
        else echo json_encode(['status'=>'error','message'=>'Data tidak ditemukan']);
    } else {
        echo json_encode(['status'=>'error','message'=>'ID tidak valid']);
    }
    exit;
}

echo json_encode(['status'=>'error','message'=>'Action tidak dikenal']);
