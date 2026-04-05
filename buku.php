<?php
require_once 'config/database.php';
checkLogin();

$message = '';

// Proses Tambah/Edit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['action'])) {
        if($_POST['action'] == 'add') {
            $kode_buku = $_POST['kode_buku'];
            $judul = $_POST['judul'];
            $pengarang = $_POST['pengarang'];
            $penerbit = $_POST['penerbit'];
            $tahun_terbit = $_POST['tahun_terbit'];
            $kategori = $_POST['kategori'];
            $stok = $_POST['stok'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO buku (kode_buku, judul, pengarang, penerbit, tahun_terbit, kategori, stok, tersedia) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$kode_buku, $judul, $pengarang, $penerbit, $tahun_terbit, $kategori, $stok, $stok]);
                $message = '<div class="alert alert-success">Data buku berhasil ditambahkan!</div>';
            } catch(PDOException $e) {
                $message = '<div class="alert alert-danger">Gagal menambahkan data: ' . $e->getMessage() . '</div>';
            }
        }
        elseif($_POST['action'] == 'edit') {
            $id_buku = $_POST['id_buku'];
            $kode_buku = $_POST['kode_buku'];
            $judul = $_POST['judul'];
            $pengarang = $_POST['pengarang'];
            $penerbit = $_POST['penerbit'];
            $tahun_terbit = $_POST['tahun_terbit'];
            $kategori = $_POST['kategori'];
            $stok = $_POST['stok'];
            
            try {
                // Hitung selisih stok
                $stmt = $pdo->prepare("SELECT stok, tersedia FROM buku WHERE id_buku = ?");
                $stmt->execute([$id_buku]);
                $buku_lama = $stmt->fetch();
                
                $selisih = $stok - $buku_lama['stok'];
                $tersedia_baru = $buku_lama['tersedia'] + $selisih;
                
                $stmt = $pdo->prepare("UPDATE buku SET kode_buku=?, judul=?, pengarang=?, penerbit=?, tahun_terbit=?, kategori=?, stok=?, tersedia=? WHERE id_buku=?");
                $stmt->execute([$kode_buku, $judul, $pengarang, $penerbit, $tahun_terbit, $kategori, $stok, $tersedia_baru, $id_buku]);
                $message = '<div class="alert alert-success">Data buku berhasil diupdate!</div>';
            } catch(PDOException $e) {
                $message = '<div class="alert alert-danger">Gagal mengupdate data: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Proses Hapus
if(isset($_GET['delete'])) {
    $id_buku = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM buku WHERE id_buku = ?");
        $stmt->execute([$id_buku]);
        $message = '<div class="alert alert-success">Data buku berhasil dihapus!</div>';
    } catch(PDOException $e) {
        $message = '<div class="alert alert-danger">Gagal menghapus data: ' . $e->getMessage() . '</div>';
    }
}

// Ambil data untuk edit
$edit_data = null;
if(isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM buku WHERE id_buku = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_data = $stmt->fetch();
}

// Ambil semua data buku
$buku_list = $pdo->query("SELECT * FROM buku ORDER BY id_buku DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Buku - Perpustakaan Kampus</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <h1>Data Buku</h1>
            
            <?= $message ?>
            
            <div class="card">
                <h2><?= $edit_data ? 'Edit Buku' : 'Tambah Buku Baru' ?></h2>
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $edit_data ? 'edit' : 'add' ?>">
                    <?php if($edit_data): ?>
                        <input type="hidden" name="id_buku" value="<?= $edit_data['id_buku'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kode Buku *</label>
                            <input type="text" name="kode_buku" value="<?= $edit_data['kode_buku'] ?? '' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Judul Buku *</label>
                            <input type="text" name="judul" value="<?= $edit_data['judul'] ?? '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pengarang *</label>
                            <input type="text" name="pengarang" value="<?= $edit_data['pengarang'] ?? '' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Penerbit</label>
                            <input type="text" name="penerbit" value="<?= $edit_data['penerbit'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tahun Terbit</label>
                            <input type="number" name="tahun_terbit" value="<?= $edit_data['tahun_terbit'] ?? '' ?>" min="1900" max="<?= date('Y') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="kategori">
                                <option value="">- Pilih Kategori -</option>
                                <option value="Teknologi" <?= ($edit_data['kategori'] ?? '') == 'Teknologi' ? 'selected' : '' ?>>Teknologi</option>
                                <option value="Komputer" <?= ($edit_data['kategori'] ?? '') == 'Komputer' ? 'selected' : '' ?>>Komputer</option>
                                <option value="Sastra" <?= ($edit_data['kategori'] ?? '') == 'Sastra' ? 'selected' : '' ?>>Sastra</option>
                                <option value="Ekonomi" <?= ($edit_data['kategori'] ?? '') == 'Ekonomi' ? 'selected' : '' ?>>Ekonomi</option>
                                <option value="Hukum" <?= ($edit_data['kategori'] ?? '') == 'Hukum' ? 'selected' : '' ?>>Hukum</option>
                                <option value="Kesehatan" <?= ($edit_data['kategori'] ?? '') == 'Kesehatan' ? 'selected' : '' ?>>Kesehatan</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Stok *</label>
                            <input type="number" name="stok" value="<?= $edit_data['stok'] ?? '' ?>" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?= $edit_data ? '💾 Update Data' : '➕ Tambah Data' ?>
                        </button>
                        <?php if($edit_data): ?>
                            <a href="buku.php" class="btn btn-secondary">❌ Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h2>Daftar Buku</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Buku</th>
                                <th>Judul</th>
                                <th>Pengarang</th>
                                <th>Penerbit</th>
                                <th>Tahun</th>
                                <th>Kategori</th>
                                <th>Stok</th>
                                <th>Tersedia</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($buku_list as $buku): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= $buku['kode_buku'] ?></strong></td>
                                <td><?= $buku['judul'] ?></td>
                                <td><?= $buku['pengarang'] ?></td>
                                <td><?= $buku['penerbit'] ?></td>
                                <td><?= $buku['tahun_terbit'] ?></td>
                                <td><?= $buku['kategori'] ?></td>
                                <td><?= $buku['stok'] ?></td>
                                <td><span class="badge <?= $buku['tersedia'] > 0 ? 'badge-dikembalikan' : 'badge-terlambat' ?>"><?= $buku['tersedia'] ?></span></td>
                                <td>
                                    <a href="?edit=<?= $buku['id_buku'] ?>" class="btn btn-sm btn-secondary">✏️ Edit</a>
                                    <a href="?delete=<?= $buku['id_buku'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus buku ini?')">🗑️ Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>