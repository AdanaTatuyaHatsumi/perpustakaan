<?php
require_once 'config/database.php';
checkLogin();

$message = '';

// Proses absensi masuk
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'masuk') {
    $nim = $_POST['nim'];
    $keperluan = $_POST['keperluan'];
    
    // Cek anggota
    $stmt = $pdo->prepare("SELECT * FROM anggota WHERE nim = ? AND status = 'aktif'");
    $stmt->execute([$nim]);
    $anggota = $stmt->fetch();
    
    if(!$anggota) {
        $message = '<div class="alert alert-danger">Anggota tidak ditemukan atau tidak aktif!</div>';
    } else {
        // Cek apakah sudah absen masuk hari ini
        $stmt = $pdo->prepare("SELECT * FROM kunjungan WHERE id_anggota = ? AND tanggal_kunjungan = CURDATE() AND waktu_keluar IS NULL");
        $stmt->execute([$anggota['id_anggota']]);
        
        if($stmt->fetch()) {
            $message = '<div class="alert alert-warning">Anda sudah melakukan absensi masuk hari ini!</div>';
        } else {
            // Insert absensi masuk
            $stmt = $pdo->prepare("INSERT INTO kunjungan (id_anggota, tanggal_kunjungan, waktu_masuk, keperluan) VALUES (?, CURDATE(), CURTIME(), ?)");
            $stmt->execute([$anggota['id_anggota'], $keperluan]);
            $message = '<div class="alert alert-success">Selamat datang, ' . $anggota['nama'] . '! Absensi masuk berhasil.</div>';
        }
    }
}

// Proses absensi keluar
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'keluar') {
    $nim = $_POST['nim'];
    
    // Cek anggota
    $stmt = $pdo->prepare("SELECT * FROM anggota WHERE nim = ?");
    $stmt->execute([$nim]);
    $anggota = $stmt->fetch();
    
    if(!$anggota) {
        $message = '<div class="alert alert-danger">Anggota tidak ditemukan!</div>';
    } else {
        // Update waktu keluar
        $stmt = $pdo->prepare("UPDATE kunjungan SET waktu_keluar = CURTIME() WHERE id_anggota = ? AND tanggal_kunjungan = CURDATE() AND waktu_keluar IS NULL");
        $stmt->execute([$anggota['id_anggota']]);
        
        if($stmt->rowCount() > 0) {
            $message = '<div class="alert alert-success">Terima kasih, ' . $anggota['nama'] . '! Absensi keluar berhasil.</div>';
        } else {
            $message = '<div class="alert alert-danger">Tidak ada absensi masuk hari ini atau sudah absen keluar!</div>';
        }
    }
}

// Data kunjungan hari ini
$kunjungan_hari_ini = $pdo->query("
    SELECT k.*, a.nim, a.nama, a.jurusan
    FROM kunjungan k
    JOIN anggota a ON k.id_anggota = a.id_anggota
    WHERE k.tanggal_kunjungan = CURDATE()
    ORDER BY k.waktu_masuk DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Kunjungan - Perpustakaan Kampus</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <h1>📋 Absensi Kunjungan Perpustakaan</h1>
            
            <?= $message ?>
            
            <div class="form-row">
                <!-- Form Absen Masuk -->
                <div class="card" style="flex: 1;">
                    <h2>✅ Absen Masuk</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="masuk">
                        
                        <div class="form-group">
                            <label>NIM (Scan Kartu Anggota) *</label>
                            <div class="input-group">
                                <input type="text" name="nim" id="nim_masuk" required autofocus>
                                <button type="button" class="btn btn-secondary" onclick="scanBarcode('nim_masuk')">📷 Scan</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Keperluan</label>
                            <select name="keperluan">
                                <option value="Membaca">Membaca</option>
                                <option value="Meminjam Buku">Meminjam Buku</option>
                                <option value="Mengembalikan Buku">Mengembalikan Buku</option>
                                <option value="Mengerjakan Tugas">Mengerjakan Tugas</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success">✅ Absen Masuk</button>
                    </form>
                </div>
                
                <!-- Form Absen Keluar -->
                <div class="card" style="flex: 1;">
                    <h2>🚪 Absen Keluar</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="keluar">
                        
                        <div class="form-group">
                            <label>NIM (Scan Kartu Anggota) *</label>
                            <div class="input-group">
                                <input type="text" name="nim" id="nim_keluar" required>
                                <button type="button" class="btn btn-secondary" onclick="scanBarcode('nim_keluar')">📷 Scan</button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-danger">🚪 Absen Keluar</button>
                    </form>
                </div>
            </div>
            
            <!-- Area Scanner -->
            <div id="scanner-container" style="display:none;">
                <div class="scanner-overlay">
                    <div class="scanner-box">
                        <h3>Scan Barcode Kartu Anggota</h3>
                        <div id="scanner"></div>
                        <button type="button" class="btn btn-danger" onclick="stopScanner()">Tutup Scanner</button>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2>Daftar Kunjungan Hari Ini (<?= date('d/m/Y') ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Jurusan</th>
                            <th>Waktu Masuk</th>
                            <th>Waktu Keluar</th>
                            <th>Keperluan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($kunjungan_hari_ini as $k): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= $k['nim'] ?></td>
                            <td><?= $k['nama'] ?></td>
                            <td><?= $k['jurusan'] ?></td>
                            <td><?= $k['waktu_masuk'] ?></td>
                            <td><?= $k['waktu_keluar'] ?? '-' ?></td>
                            <td><?= $k['keperluan'] ?></td>
                            <td>
                                <span class="badge badge-<?= $k['waktu_keluar'] ? 'dikembalikan' : 'dipinjam' ?>">
                                    <?= $k['waktu_keluar'] ? 'Selesai' : 'Di Dalam' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        let currentField = '';
        
        function scanBarcode(fieldId) {
            currentField = fieldId;
            document.getElementById('scanner-container').style.display = 'block';
            
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: document.querySelector('#scanner'),
                    constraints: {
                        facingMode: "environment"
                    }
                },
                decoder: {
                    readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader"]
                }
            }, function(err) {
                if (err) {
                    console.log(err);
                    alert('Error: Tidak dapat mengakses kamera!');
                    return;
                }
                Quagga.start();
            });
            
            Quagga.onDetected(function(result) {
                let code = result.codeResult.code;
                document.getElementById(currentField).value = code;
                stopScanner();
            });
        }
        
        function stopScanner() {
            Quagga.stop();
            document.getElementById('scanner-container').style.display = 'none';
        }
    </script>
</body>
</html>