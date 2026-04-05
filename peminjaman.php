<?php
require_once 'config/database.php';
checkLogin();

$message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nim = $_POST['nim'];
    $kode_buku = $_POST['kode_buku'];
    $tanggal_pinjam = $_POST['tanggal_pinjam'];
    $lama_pinjam = $_POST['lama_pinjam'];
    
    // Cek anggota
    $stmt = $pdo->prepare("SELECT * FROM anggota WHERE nim = ? AND status = 'aktif'");
    $stmt->execute([$nim]);
    $anggota = $stmt->fetch();
    
    if(!$anggota) {
        $message = '<div class="alert alert-danger">Anggota tidak ditemukan atau tidak aktif!</div>';
    } else {
        // Cek buku
        $stmt = $pdo->prepare("SELECT * FROM buku WHERE kode_buku = ?");
        $stmt->execute([$kode_buku]);
        $buku = $stmt->fetch();
        
        if(!$buku) {
            $message = '<div class="alert alert-danger">Buku tidak ditemukan!</div>';
        } elseif($buku['tersedia'] <= 0) {
            $message = '<div class="alert alert-danger">Stok buku tidak tersedia!</div>';
        } else {
            // Proses peminjaman
            $kode_peminjaman = generateKode('PJM', 'peminjaman', 'kode_peminjaman');
            $tanggal_kembali = date('Y-m-d', strtotime($tanggal_pinjam . " +$lama_pinjam days"));
            
            try {
                $pdo->beginTransaction();
                
                // Insert peminjaman
                $stmt = $pdo->prepare("INSERT INTO peminjaman (kode_peminjaman, id_anggota, id_buku, tanggal_pinjam, tanggal_kembali_rencana, status) VALUES (?, ?, ?, ?, ?, 'dipinjam')");
                $stmt->execute([$kode_peminjaman, $anggota['id_anggota'], $buku['id_buku'], $tanggal_pinjam, $tanggal_kembali]);
                
                // Update stok buku
                $stmt = $pdo->prepare("UPDATE buku SET tersedia = tersedia - 1 WHERE id_buku = ?");
                $stmt->execute([$buku['id_buku']]);
                
                $pdo->commit();
                $message = '<div class="alert alert-success">Peminjaman berhasil! Kode: ' . $kode_peminjaman . '</div>';
            } catch(Exception $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">Gagal memproses peminjaman: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Data peminjaman aktif
$peminjaman_aktif = $pdo->query("
    SELECT p.*, a.nama as nama_anggota, a.nim, b.judul, b.kode_buku
    FROM peminjaman p
    JOIN anggota a ON p.id_anggota = a.id_anggota
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.status IN ('dipinjam', 'terlambat')
    ORDER BY p.tanggal_pinjam DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Buku - Perpustakaan Kampus</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <h1>Peminjaman Buku</h1>
            
            <?= $message ?>
            
            <div class="card">
                <h2>Form Peminjaman</h2>
                <form method="POST" id="formPeminjaman">
                    <div class="form-row">
                        <div class="form-group">
                            <label>NIM Anggota *</label>
                            <div class="input-group">
                                <input type="text" name="nim" id="nim" required>
                                <button type="button" class="btn btn-secondary" onclick="scanBarcode('nim')">📷 Scan</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Kode Buku *</label>
                            <div class="input-group">
                                <input type="text" name="kode_buku" id="kode_buku" required>
                                <button type="button" class="btn btn-secondary" onclick="scanBarcode('kode_buku')">📷 Scan</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal Pinjam *</label>
                            <input type="date" name="tanggal_pinjam" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Lama Pinjam (hari) *</label>
                            <input type="number" name="lama_pinjam" value="7" min="1" max="30" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Proses Peminjaman</button>
                </form>
            </div>
            
            <!-- Area Scanner -->
            <div id="scanner-container" style="display:none;">
                <div class="scanner-overlay">
                    <div class="scanner-box">
                        <h3>Scan Barcode</h3>
                        <div id="scanner"></div>
                        <button type="button" class="btn btn-danger" onclick="stopScanner()">Tutup Scanner</button>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2>Daftar Peminjaman Aktif</h2>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Tgl Kembali</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($peminjaman_aktif as $p): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= $p['kode_peminjaman'] ?></td>
                            <td><?= $p['nim'] ?></td>
                            <td><?= $p['nama_anggota'] ?></td>
                            <td><?= $p['judul'] ?></td>
                            <td><?= date('d/m/Y', strtotime($p['tanggal_pinjam'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($p['tanggal_kembali_rencana'])) ?></td>
                            <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
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