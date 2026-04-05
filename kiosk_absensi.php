<?php
require_once 'config/database.php';

// Tidak perlu login untuk kiosk
$message = '';
$show_welcome = false;
$anggota_data = null;

// Proses absensi masuk
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'masuk') {
    $nim = $_POST['nim'];
    $keperluan = $_POST['keperluan'];
    
    // Cek anggota
    $stmt = $pdo->prepare("SELECT * FROM anggota WHERE nim = ? AND status = 'aktif'");
    $stmt->execute([$nim]);
    $anggota = $stmt->fetch();
    
    if(!$anggota) {
        $message = '<div class="kiosk-alert kiosk-alert-danger">❌ Anggota tidak ditemukan atau tidak aktif!</div>';
    } else {
        // Cek apakah sudah absen masuk hari ini
        $stmt = $pdo->prepare("SELECT * FROM kunjungan WHERE id_anggota = ? AND tanggal_kunjungan = CURDATE() AND waktu_keluar IS NULL");
        $stmt->execute([$anggota['id_anggota']]);
        
        if($stmt->fetch()) {
            $message = '<div class="kiosk-alert kiosk-alert-warning">⚠️ Anda sudah melakukan absensi masuk hari ini!</div>';
        } else {
            // Insert absensi masuk
            $stmt = $pdo->prepare("INSERT INTO kunjungan (id_anggota, tanggal_kunjungan, waktu_masuk, keperluan) VALUES (?, CURDATE(), CURTIME(), ?)");
            $stmt->execute([$anggota['id_anggota'], $keperluan]);
            
            $show_welcome = true;
            $anggota_data = $anggota;
            $anggota_data['waktu_masuk'] = date('H:i:s');
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
        $message = '<div class="kiosk-alert kiosk-alert-danger">❌ Anggota tidak ditemukan!</div>';
    } else {
        // Update waktu keluar
        $stmt = $pdo->prepare("UPDATE kunjungan SET waktu_keluar = CURTIME() WHERE id_anggota = ? AND tanggal_kunjungan = CURDATE() AND waktu_keluar IS NULL");
        $stmt->execute([$anggota['id_anggota']]);
        
        if($stmt->rowCount() > 0) {
            $show_welcome = true;
            $anggota_data = $anggota;
            $anggota_data['waktu_keluar'] = date('H:i:s');
            $anggota_data['action'] = 'keluar';
        } else {
            $message = '<div class="kiosk-alert kiosk-alert-danger">❌ Tidak ada absensi masuk hari ini atau sudah absen keluar!</div>';
        }
    }
}

// Statistik hari ini
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_kunjungan,
        COUNT(CASE WHEN waktu_keluar IS NULL THEN 1 END) as sedang_didalam
    FROM kunjungan 
    WHERE tanggal_kunjungan = CURDATE()
")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Absensi - Perpustakaan Kampus</title>
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .kiosk-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 1200px;
            width: 100%;
            overflow: hidden;
        }
        
        .kiosk-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .kiosk-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .kiosk-header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .datetime-display {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 1.1em;
        }
        
        .kiosk-stats {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            justify-content: center;
        }
        
        .stat-box {
            background: white;
            padding: 20px 40px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .kiosk-content {
            padding: 40px;
        }
        
        .kiosk-alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 1.2em;
            text-align: center;
            animation: slideDown 0.5s ease;
        }
        
        .kiosk-alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .kiosk-alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .kiosk-alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }
        
        .welcome-screen {
            text-align: center;
            padding: 40px;
            animation: fadeIn 0.5s ease;
        }
        
        .welcome-screen h2 {
            font-size: 2.5em;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .welcome-icon {
            font-size: 5em;
            margin-bottom: 20px;
        }
        
        .welcome-info {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            font-size: 1.3em;
        }
        
        .welcome-info p {
            margin: 10px 0;
        }
        
        .welcome-info strong {
            color: #667eea;
        }
        
        .back-btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 40px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 1.2em;
            margin-top: 20px;
            cursor: pointer;
            border: none;
        }
        
        .back-btn:hover {
            background: #764ba2;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        .kiosk-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            border: 3px solid #e9ecef;
        }
        
        .kiosk-form h3 {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 1.1em;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1.1em;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
            width: 100%;
            padding: 18px;
            font-size: 1.2em;
        }
        
        .btn-primary:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            width: 100%;
            padding: 18px;
            font-size: 1.2em;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        
        #scanner-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .scanner-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
        }
        
        .scanner-box h3 {
            margin-bottom: 20px;
            text-align: center;
        }
        
        #scanner {
            width: 100%;
            max-width: 500px;
            height: 300px;
            margin: 0 auto;
            border: 3px solid #667eea;
            border-radius: 10px;
            overflow: hidden;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .admin-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.9em;
            backdrop-filter: blur(10px);
        }
        
        .admin-link:hover {
            background: rgba(255,255,255,0.3);
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .kiosk-header h1 {
                font-size: 1.8em;
            }
            
            .kiosk-stats {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="kiosk-container">
        <div class="kiosk-header">
            <h1>📚 PERPUSTAKAAN KAMPUS</h1>
            <p>Sistem Absensi Kunjungan Mandiri</p>
            <div class="datetime-display" id="datetime"></div>
        </div>
        
        <div class="kiosk-stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_kunjungan'] ?></div>
                <div class="stat-label">Total Kunjungan Hari Ini</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['sedang_didalam'] ?></div>
                <div class="stat-label">Pengunjung Di Dalam</div>
            </div>
        </div>
        
        <div class="kiosk-content">
            <?php if($message): ?>
                <?= $message ?>
            <?php endif; ?>
            
            <?php if($show_welcome): ?>
                <div class="welcome-screen">
                    <?php if(isset($anggota_data['action']) && $anggota_data['action'] == 'keluar'): ?>
                        <div class="welcome-icon">👋</div>
                        <h2>Terima Kasih!</h2>
                        <div class="welcome-info">
                            <p><strong>Nama:</strong> <?= $anggota_data['nama'] ?></p>
                            <p><strong>NIM:</strong> <?= $anggota_data['nim'] ?></p>
                            <p><strong>Jurusan:</strong> <?= $anggota_data['jurusan'] ?></p>
                            <p><strong>Waktu Keluar:</strong> <?= date('H:i:s', strtotime($anggota_data['waktu_keluar'])) ?></p>
                        </div>
                        <p style="font-size: 1.2em; color: #666;">Sampai jumpa lagi! 😊</p>
                    <?php else: ?>
                        <div class="welcome-icon">👋</div>
                        <h2>Selamat Datang!</h2>
                        <div class="welcome-info">
                            <p><strong>Nama:</strong> <?= $anggota_data['nama'] ?></p>
                            <p><strong>NIM:</strong> <?= $anggota_data['nim'] ?></p>
                            <p><strong>Jurusan:</strong> <?= $anggota_data['jurusan'] ?></p>
                            <p><strong>Waktu Masuk:</strong> <?= date('H:i:s', strtotime($anggota_data['waktu_masuk'])) ?></p>
                        </div>
                        <p style="font-size: 1.2em; color: #666;">Selamat berkunjung! 📖</p>
                    <?php endif; ?>
                    
                    <button onclick="window.location.reload()" class="back-btn">
                        ✨ Kembali ke Halaman Utama
                    </button>
                </div>
            <?php else: ?>
                <div class="form-grid">
                    <!-- Form Absen Masuk -->
                    <div class="kiosk-form">
                        <h3>✅ Absen Masuk</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="masuk">
                            
                            <div class="form-group">
                                <label>Scan Kartu Anggota atau Ketik NIM</label>
                                <div class="input-group">
                                    <input type="text" name="nim" id="nim_masuk" required autofocus placeholder="Scan atau ketik NIM">
                                    <button type="button" class="btn btn-secondary" onclick="scanBarcode('nim_masuk')">📷</button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Keperluan</label>
                                <select name="keperluan" required>
                                    <option value="Membaca">📖 Membaca</option>
                                    <option value="Meminjam Buku">📚 Meminjam Buku</option>
                                    <option value="Mengembalikan Buku">📥 Mengembalikan Buku</option>
                                    <option value="Mengerjakan Tugas">✍️ Mengerjakan Tugas</option>
                                    <option value="Lainnya">📝 Lainnya</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">✅ Absen Masuk</button>
                        </form>
                    </div>
                    
                    <!-- Form Absen Keluar -->
                    <div class="kiosk-form">
                        <h3>🚪 Absen Keluar</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="keluar">
                            
                            <div class="form-group">
                                <label>Scan Kartu Anggota atau Ketik NIM</label>
                                <div class="input-group">
                                    <input type="text" name="nim" id="nim_keluar" required placeholder="Scan atau ketik NIM">
                                    <button type="button" class="btn btn-secondary" onclick="scanBarcode('nim_keluar')">📷</button>
                                </div>
                            </div>
                            
                            <div style="height: 80px;"></div>
                            
                            <button type="submit" class="btn btn-danger">🚪 Absen Keluar</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Area Scanner -->
    <div id="scanner-container" style="display:none;">
        <div class="scanner-box">
            <h3>📷 Scan Barcode Kartu Anggota</h3>
            <div id="scanner"></div>
            <button type="button" class="btn btn-danger" onclick="stopScanner()" style="margin-top: 20px; width: 100%;">❌ Tutup Scanner</button>
        </div>
    </div>
    
    <a href="index.php" class="admin-link">🔐 Login Admin</a>
    
    <script>
        // Update datetime
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('datetime').textContent = now.toLocaleDateString('id-ID', options);
        }
        
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Auto refresh page setiap 5 menit untuk update statistik
        <?php if(!$show_welcome): ?>
        setTimeout(() => {
            window.location.reload();
        }, 300000); // 5 menit
        <?php endif; ?>
        
        // Auto redirect setelah 10 detik kalau welcome screen
        <?php if($show_welcome): ?>
        setTimeout(() => {
            window.location.reload();
        }, 10000); // 10 detik
        <?php endif; ?>
        
        // Barcode Scanner
        let currentField = '';
        
        function scanBarcode(fieldId) {
            currentField = fieldId;
            document.getElementById('scanner-container').style.display = 'flex';
            
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
                    readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader", "upc_reader"]
                }
            }, function(err) {
                if (err) {
                    console.log(err);
                    alert('Error: Tidak dapat mengakses kamera!');
                    stopScanner();
                    return;
                }
                Quagga.start();
            });
            
            Quagga.onDetected(function(result) {
                let code = result.codeResult.code;
                document.getElementById(currentField).value = code;
                stopScanner();
                
                // Auto submit form setelah scan
                setTimeout(() => {
                    document.getElementById(currentField).closest('form').submit();
                }, 500);
            });
        }
        
        function stopScanner() {
            Quagga.stop();
            document.getElementById('scanner-container').style.display = 'none';
        }
        
        // Clear input on focus
        document.querySelectorAll('input[name="nim"]').forEach(input => {
            input.addEventListener('focus', function() {
                this.value = '';
            });
        });
    </script>
</body>
</html>