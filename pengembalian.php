<?php
require_once 'config/database.php';
checkLogin();

$message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_peminjaman = $_POST['kode_peminjaman'];
    $tanggal_kembali = $_POST['tanggal_kembali'];
    
    // Cek peminjaman
    $stmt = $pdo->prepare("
        SELECT p.*, b.id_buku 
        FROM peminjaman p
        JOIN buku b ON p.id_buku = b.id_buku
        WHERE p.kode_peminjaman = ? AND p.status IN ('dipinjam', 'terlambat')
    ");
    $stmt->execute([$kode_peminjaman]);
    $peminjaman = $stmt->fetch();
    
    if(!$peminjaman) {
        $message = '<div class="alert alert-danger">Kode peminjaman tidak ditemukan atau sudah dikembalikan!</div>';
    } else {
        // Hitung denda jika terlambat
        $tanggal_kembali_rencana = strtotime($peminjaman['tanggal_kembali_rencana']);
        $tanggal_kembali_aktual = strtotime($tanggal_kembali);
        $denda = 0;
        
        if($tanggal_kembali_aktual > $tanggal_kembali_rencana) {
            $hari_terlambat = floor(($tanggal_kembali_aktual - $tanggal_kembali_rencana) / 86400);
            $denda = $hari_terlambat * 1000; // Denda Rp 1000/hari
        }
        
        try {
            $pdo->beginTransaction();
            
            // Update peminjaman
            $stmt = $pdo->prepare("UPDATE peminjaman SET tanggal_kembali_aktual = ?, status = 'dikembalikan', denda = ? WHERE id_peminjaman = ?");
            $stmt->execute([$tanggal_kembali, $denda, $peminjaman['id_peminjaman']]);
            
            // Update stok buku
            $stmt = $pdo->prepare("UPDATE buku SET tersedia = tersedia + 1 WHERE id_buku = ?");
            $stmt->execute([$peminjaman['id_buku']]);
            
            $pdo->commit();
            $message = '<div class="alert alert-success">Pengembalian berhasil!' . ($denda > 0 ? ' Denda: Rp ' . number_format($denda, 0, ',', '.') : '') . '</div>';
        } catch(Exception $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">Gagal memproses pengembalian: ' . $e->getMessage() . '</div>';
        }
    }
}

// Update status terlambat
$pdo->exec("UPDATE peminjaman SET status = 'terlambat' WHERE tanggal_kembali_rencana < CURDATE() AND status = 'dipinjam'");

// Data peminjaman yang perlu dikembalikan
$peminjaman_belum_kembali = $pdo->query("
    SELECT p.*, a.nama as nama_anggota, a.nim, b.judul, b.kode_buku,
    DATEDIFF(CURDATE(), p.tanggal_kembali_rencana) as hari_terlambat
    FROM peminjaman p
    JOIN anggota a ON p.id_anggota = a.id_anggota
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.status IN ('dipinjam', 'terlambat')
    ORDER BY p.tanggal_kembali_rencana ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian Buku - Perpustakaan Kampus</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <h1>Pengembalian Buku</h1>
            
            <?= $message ?>
            
            <div class="card">
                <h2>Form Pengembalian</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kode Peminjaman *</label>
                            <div class="input-group">
                                <input type="text" name="kode_peminjaman" id="kode_peminjaman" required>
                                <button type="button" class="btn btn-secondary" onclick="scanBarcode('kode_peminjaman')">📷 Scan</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Tanggal Kembali *</label>
                            <input type="date" name="tanggal_kembali" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Proses Pengembalian</button>
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
            <h2>Daftar Buku Belum Dikembalikan</h2>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Peminjaman</th>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Buku</th>
                        <th>Tgl Pinjam</th>
                        <th>Tgl Hrs Kembali</th>
                        <th>Keterlambatan</th>
                        <th>Denda</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach($peminjaman_belum_kembali as $p): 
                        $denda = $p['hari_terlambat'] > 0 ? $p['hari_terlambat'] * 1000 : 0;
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= $p['kode_peminjaman'] ?></td>
                        <td><?= $p['nim'] ?></td>
                        <td><?= $p['nama_anggota'] ?></td>
                        <td><?= $p['judul'] ?></td>
                        <td><?= date('d/m/Y', strtotime($p['tanggal_pinjam'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($p['tanggal_kembali_rencana'])) ?></td>
                        <td><?= $p['hari_terlambat'] > 0 ? $p['hari_terlambat'] . ' hari' : '-' ?></td>
                        <td><?= $denda > 0 ? 'Rp ' . number_format($denda, 0, ',', '.') : '-' ?></td>
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