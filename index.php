<?php
require_once 'config/database.php';
checkLogin();

// Statistik
$total_buku = $pdo->query("SELECT COUNT(*) FROM buku")->fetchColumn();
$total_anggota = $pdo->query("SELECT COUNT(*) FROM anggota WHERE status='aktif'")->fetchColumn();
$total_dipinjam = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='dipinjam'")->fetchColumn();
$total_terlambat = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='terlambat'")->fetchColumn();

// Peminjaman terbaru
$peminjaman_terbaru = $pdo->query("
    SELECT p.*, a.nama as nama_anggota, a.nim, b.judul, b.kode_buku
    FROM peminjaman p
    JOIN anggota a ON p.id_anggota = a.id_anggota
    JOIN buku b ON p.id_buku = b.id_buku
    ORDER BY p.created_at DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Perpustakaan Kampus</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <h1>Dashboard</h1>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-info">
                        <h3><?= $total_buku ?></h3>
                        <p>Total Buku</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3><?= $total_anggota ?></h3>
                        <p>Anggota Aktif</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📤</div>
                    <div class="stat-info">
                        <h3><?= $total_dipinjam ?></h3>
                        <p>Sedang Dipinjam</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-info">
                        <h3><?= $total_terlambat ?></h3>
                        <p>Terlambat</p>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2>Peminjaman Terbaru</h2>
                <table>
                    <thead>
                        <tr>
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
                        <?php foreach($peminjaman_terbaru as $p): ?>
                        <tr>
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
</body>
</html>