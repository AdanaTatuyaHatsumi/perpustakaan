<?php
require_once 'config/database.php';
checkLogin();

$message = '';
$show_barcode = false;
$new_anggota_data = null;

// Proses Tambah/Edit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['action'])) {
        if($_POST['action'] == 'add') {
            $nim = $_POST['nim'];
            $nama = $_POST['nama'];
            $email = $_POST['email'];
            $telepon = $_POST['telepon'];
            $jurusan = $_POST['jurusan'];
            $alamat = $_POST['alamat'];
            $status = $_POST['status'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO anggota (nim, nama, email, telepon, jurusan, alamat, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nim, $nama, $email, $telepon, $jurusan, $alamat, $status]);
                
                // Ambil data anggota yang baru dibuat untuk generate barcode
                $new_anggota_data = [
                    'nim' => $nim,
                    'nama' => $nama,
                    'jurusan' => $jurusan
                ];
                
                $message = '<div class="alert alert-success">Data anggota berhasil ditambahkan! Barcode kartu anggota siap dicetak.</div>';
                $show_barcode = true;
            } catch(PDOException $e) {
                $message = '<div class="alert alert-danger">Gagal menambahkan data: ' . $e->getMessage() . '</div>';
            }
        }
        elseif($_POST['action'] == 'edit') {
            $id_anggota = $_POST['id_anggota'];
            $nim = $_POST['nim'];
            $nama = $_POST['nama'];
            $email = $_POST['email'];
            $telepon = $_POST['telepon'];
            $jurusan = $_POST['jurusan'];
            $alamat = $_POST['alamat'];
            $status = $_POST['status'];
            
            try {
                $stmt = $pdo->prepare("UPDATE anggota SET nim=?, nama=?, email=?, telepon=?, jurusan=?, alamat=?, status=? WHERE id_anggota=?");
                $stmt->execute([$nim, $nama, $email, $telepon, $jurusan, $alamat, $status, $id_anggota]);
                $message = '<div class="alert alert-success">Data anggota berhasil diupdate!</div>';
            } catch(PDOException $e) {
                $message = '<div class="alert alert-danger">Gagal mengupdate data: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Proses Hapus
if(isset($_GET['delete'])) {
    $id_anggota = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM anggota WHERE id_anggota = ?");
        $stmt->execute([$id_anggota]);
        $message = '<div class="alert alert-success">Data anggota berhasil dihapus!</div>';
    } catch(PDOException $e) {
        $message = '<div class="alert alert-danger">Gagal menghapus data: ' . $e->getMessage() . '</div>';
    }
}

// Ambil data untuk edit
$edit_data = null;
if(isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM anggota WHERE id_anggota = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_data = $stmt->fetch();
}

// Cetak ulang kartu anggota
if(isset($_GET['print'])) {
    $stmt = $pdo->prepare("SELECT * FROM anggota WHERE id_anggota = ?");
    $stmt->execute([$_GET['print']]);
    $new_anggota_data = $stmt->fetch();
    $show_barcode = true;
}

// Ambil semua data anggota
$anggota_list = $pdo->query("SELECT * FROM anggota ORDER BY id_anggota DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Anggota - Perpustakaan Kampus</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        .member-card {
            width: 350px;
            margin: 20px auto;
            border: 2px solid #667eea;
            border-radius: 10px;
            overflow: hidden;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .card-header-member {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: center;
        }
        .card-header-member h3 {
            margin: 0;
            font-size: 18px;
        }
        .card-header-member p {
            margin: 5px 0 0 0;
            font-size: 12px;
        }
        .card-body-member {
            padding: 20px;
        }
        .member-info {
            margin-bottom: 15px;
        }
        .member-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        .member-info strong {
            display: inline-block;
            width: 80px;
        }
        .barcode-container {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }
        .barcode-container svg {
            max-width: 100%;
        }
        .print-buttons {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .member-card, .member-card * {
                visibility: visible;
            }
            .member-card {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
            }
            .print-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <h1>Data Anggota</h1>
            
            <?= $message ?>
            
            <?php if($show_barcode && $new_anggota_data): ?>
            <div class="card">
                <h2>🎫 Kartu Anggota Perpustakaan</h2>
                
                <div class="member-card" id="memberCard">
                    <div class="card-header-member">
                        <h3>📚 PERPUSTAKAAN KAMPUS</h3>
                        <p>Kartu Anggota Perpustakaan</p>
                    </div>
                    <div class="card-body-member">
                        <div class="member-info">
                            <p><strong>NIM</strong>: <?= $new_anggota_data['nim'] ?></p>
                            <p><strong>Nama</strong>: <?= $new_anggota_data['nama'] ?></p>
                            <p><strong>Jurusan</strong>: <?= $new_anggota_data['jurusan'] ?></p>
                        </div>
                        <div class="barcode-container">
                            <svg id="barcode"></svg>
                            <p style="margin-top: 5px; font-size: 12px; color: #666;">Scan barcode untuk absensi kunjungan</p>
                        </div>
                    </div>
                </div>
                
                <div class="print-buttons">
                    <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak Kartu</button>
                    <button onclick="downloadCard()" class="btn btn-success">💾 Download PNG</button>
                    <a href="anggota.php" class="btn btn-secondary">← Kembali ke Daftar</a>
                </div>
            </div>
            
            <script>
                // Generate barcode
                JsBarcode("#barcode", "<?= $new_anggota_data['nim'] ?>", {
                    format: "CODE128",
                    width: 2,
                    height: 60,
                    displayValue: true,
                    fontSize: 14,
                    margin: 10
                });
                
                // Download sebagai gambar
                function downloadCard() {
                    html2canvas(document.querySelector("#memberCard")).then(canvas => {
                        const link = document.createElement('a');
                        link.download = 'kartu_anggota_<?= $new_anggota_data['nim'] ?>.png';
                        link.href = canvas.toDataURL();
                        link.click();
                    });
                }
            </script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
            <?php endif; ?>
            
            <?php if(!$show_barcode): ?>
            <div class="card">
                <h2><?= $edit_data ? 'Edit Anggota' : 'Tambah Anggota Baru' ?></h2>
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $edit_data ? 'edit' : 'add' ?>">
                    <?php if($edit_data): ?>
                        <input type="hidden" name="id_anggota" value="<?= $edit_data['id_anggota'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>NIM *</label>
                            <div class="input-group">
                                <input type="text" name="nim" id="nim" value="<?= $edit_data['nim'] ?? '' ?>" required>
                                <?php if(!$edit_data): ?>
                                    <button type="button" class="btn btn-secondary" onclick="scanBarcode('nim')">📷 Scan</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Lengkap *</label>
                            <input type="text" name="nama" value="<?= $edit_data['nama'] ?? '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= $edit_data['email'] ?? '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Telepon</label>
                            <input type="text" name="telepon" value="<?= $edit_data['telepon'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jurusan *</label>
                            <select name="jurusan" required>
                                <option value="">- Pilih Jurusan -</option>
                                <option value="Teknik Informatika" <?= ($edit_data['jurusan'] ?? '') == 'Teknik Informatika' ? 'selected' : '' ?>>Teknik Informatika</option>
                                <option value="Sistem Informasi" <?= ($edit_data['jurusan'] ?? '') == 'Sistem Informasi' ? 'selected' : '' ?>>Sistem Informasi</option>
                                <option value="Teknik Elektro" <?= ($edit_data['jurusan'] ?? '') == 'Teknik Elektro' ? 'selected' : '' ?>>Teknik Elektro</option>
                                <option value="Teknik Mesin" <?= ($edit_data['jurusan'] ?? '') == 'Teknik Mesin' ? 'selected' : '' ?>>Teknik Mesin</option>
                                <option value="Manajemen" <?= ($edit_data['jurusan'] ?? '') == 'Manajemen' ? 'selected' : '' ?>>Manajemen</option>
                                <option value="Akuntansi" <?= ($edit_data['jurusan'] ?? '') == 'Akuntansi' ? 'selected' : '' ?>>Akuntansi</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Status *</label>
                            <select name="status" required>
                                <option value="aktif" <?= ($edit_data['status'] ?? 'aktif') == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= ($edit_data['status'] ?? '') == 'nonaktif' ? 'selected' : '' ?>>Non-Aktif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="alamat" rows="3"><?= $edit_data['alamat'] ?? '' ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?= $edit_data ? '💾 Update Data' : '➕ Tambah Data' ?>
                        </button>
                        <?php if($edit_data): ?>
                            <a href="anggota.php" class="btn btn-secondary">❌ Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Area Scanner -->
            <div id="scanner-container" style="display:none;">
                <div class="scanner-overlay">
                    <div class="scanner-box">
                        <h3>Scan Barcode NIM</h3>
                        <div id="scanner"></div>
                        <button type="button" class="btn btn-danger" onclick="stopScanner()">Tutup Scanner</button>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2>Daftar Anggota</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIM</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Telepon</th>
                                <th>Jurusan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($anggota_list as $anggota): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= $anggota['nim'] ?></strong></td>
                                <td><?= $anggota['nama'] ?></td>
                                <td><?= $anggota['email'] ?></td>
                                <td><?= $anggota['telepon'] ?></td>
                                <td><?= $anggota['jurusan'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $anggota['status'] == 'aktif' ? 'dikembalikan' : 'terlambat' ?>">
                                        <?= ucfirst($anggota['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?print=<?= $anggota['id_anggota'] ?>" class="btn btn-sm btn-success">🎫 Cetak Kartu</a>
                                    <a href="?edit=<?= $anggota['id_anggota'] ?>" class="btn btn-sm btn-secondary">✏️ Edit</a>
                                    <a href="?delete=<?= $anggota['id_anggota'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus anggota ini?')">🗑️ Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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