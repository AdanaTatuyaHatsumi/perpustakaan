<?php
require_once 'config/database.php';
checkLogin();

$jenis_laporan = $_GET['jenis'] ?? 'peminjaman';
$tanggal_dari = $_GET['tanggal_dari'] ?? date('Y-m-01');
$tanggal_sampai = $_GET['tanggal_sampai'] ?? date('Y-m-d');

// Laporan Peminjaman
if($jenis_laporan == 'peminjaman') {
    $query = "
        SELECT p.*, a.nim, a.nama as nama_anggota, a.jurusan, 
               b.kode_buku, b.judul, b.pengarang,
               DATEDIFF(IFNULL(p.tanggal_kembali_aktual, CURDATE()), p.tanggal_kembali_rencana) as hari_terlambat
        FROM peminjaman p
        JOIN anggota a ON p.id_anggota = a.id_anggota
        JOIN buku b ON p.id_buku = b.id_buku
        WHERE p.tanggal_pinjam BETWEEN ? AND ?
        ORDER BY p.tanggal_pinjam DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tanggal_dari, $tanggal_sampai]);
    $data = $stmt->fetchAll();
}

// Laporan Kunjungan
elseif($jenis_laporan == 'kunjungan') {
    $query = "
        SELECT k.*, a.nim, a.nama, a.jurusan,
               TIMEDIFF(k.waktu_keluar, k.waktu_masuk) as durasi
        FROM kunjungan k
        JOIN anggota a ON k.id_anggota = a.id_anggota
        WHERE k.tanggal_kunjungan BETWEEN ? AND ?
        ORDER BY k.tanggal_kunjungan DESC, k.waktu_masuk DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tanggal_dari, $tanggal_sampai]);
    $data = $stmt->fetchAll();
}

// Laporan Denda
elseif($jenis_laporan == 'denda') {
    $query = "
        SELECT p.*, a.nim, a.nama as nama_anggota, a.telepon,
               b.judul, p.denda,
               DATEDIFF(p.tanggal_kembali_aktual, p.tanggal_kembali_rencana) as hari_terlambat
        FROM peminjaman p
        JOIN anggota a ON p.id_anggota = a.id_anggota
        JOIN buku b ON p.id_buku = b.id_buku
        WHERE p.denda > 0 
        AND p.tanggal_kembali_aktual BETWEEN ? AND ?
        ORDER BY p.denda DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tanggal_dari, $tanggal_sampai]);
    $data = $stmt->fetchAll();
}

// Laporan Buku Populer
elseif($jenis_laporan == 'buku_populer') {
    $query = "
        SELECT b.kode_buku, b.judul, b.pengarang, b.kategori,
               COUNT(p.id_peminjaman) as jumlah_dipinjam
        FROM buku b
        LEFT JOIN peminjaman p ON b.id_buku = p.id_buku 
        AND p.tanggal_pinjam BETWEEN ? AND ?
        GROUP BY b.id_buku
        ORDER BY jumlah_dipinjam DESC
        LIMIT 20
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tanggal_dari, $tanggal_sampai]);
    $data = $stmt->fetchAll();
}

// Laporan Anggota Aktif
elseif($jenis_laporan == 'anggota_aktif') {
    $query = "
        SELECT a.nim, a.nama, a.jurusan, a.telepon,
               COUNT(DISTINCT p.id_peminjaman) as total_pinjam,
               COUNT(DISTINCT k.id_kunjungan) as total_kunjungan
        FROM anggota a
        LEFT JOIN peminjaman p ON a.id_anggota = p.id_anggota 
        AND p.tanggal_pinjam BETWEEN ? AND ?
        LEFT JOIN kunjungan k ON a.id_anggota = k.id_anggota 
        AND k.tanggal_kunjungan BETWEEN ? AND ?
        WHERE a.status = 'aktif'
        GROUP BY a.id_anggota
        HAVING total_pinjam > 0 OR total_kunjungan > 0
        ORDER BY total_pinjam DESC, total_kunjungan DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tanggal_dari, $tanggal_sampai, $tanggal_dari, $tanggal_sampai]);
    $data = $stmt->fetchAll();
}

// Export Excel
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Laporan_' . $jenis_laporan . '_' . date('YmdHis') . '.xls"');
    
    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<h2>LAPORAN ' . strtoupper(str_replace('_', ' ', $jenis_laporan)) . '</h2>';
    echo '<p>Periode: ' . date('d/m/Y', strtotime($tanggal_dari)) . ' s/d ' . date('d/m/Y', strtotime($tanggal_sampai)) . '</p>';
    echo '<table border="1">';
    
    if($jenis_laporan == 'peminjaman') {
        echo '<tr>
            <th>No</th>
            <th>Kode Peminjaman</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Jurusan</th>
            <th>Kode Buku</th>
            <th>Judul Buku</th>
            <th>Tgl Pinjam</th>
            <th>Tgl Kembali (Rencana)</th>
            <th>Tgl Kembali (Aktual)</th>
            <th>Status</th>
            <th>Denda</th>
        </tr>';
        $no = 1;
        foreach($data as $row) {
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . $row['kode_peminjaman'] . '</td>';
            echo '<td>' . $row['nim'] . '</td>';
            echo '<td>' . $row['nama_anggota'] . '</td>';
            echo '<td>' . $row['jurusan'] . '</td>';
            echo '<td>' . $row['kode_buku'] . '</td>';
            echo '<td>' . $row['judul'] . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($row['tanggal_pinjam'])) . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($row['tanggal_kembali_rencana'])) . '</td>';
            echo '<td>' . ($row['tanggal_kembali_aktual'] ? date('d/m/Y', strtotime($row['tanggal_kembali_aktual'])) : '-') . '</td>';
            echo '<td>' . ucfirst($row['status']) . '</td>';
            echo '<td>Rp ' . number_format($row['denda'], 0, ',', '.') . '</td>';
            echo '</tr>';
        }
    }
    elseif($jenis_laporan == 'kunjungan') {
        echo '<tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Jurusan</th>
            <th>Waktu Masuk</th>
            <th>Waktu Keluar</th>
            <th>Durasi</th>
            <th>Keperluan</th>
        </tr>';
        $no = 1;
        foreach($data as $row) {
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($row['tanggal_kunjungan'])) . '</td>';
            echo '<td>' . $row['nim'] . '</td>';
            echo '<td>' . $row['nama'] . '</td>';
            echo '<td>' . $row['jurusan'] . '</td>';
            echo '<td>' . $row['waktu_masuk'] . '</td>';
            echo '<td>' . ($row['waktu_keluar'] ?? '-') . '</td>';
            echo '<td>' . ($row['durasi'] ?? '-') . '</td>';
            echo '<td>' . $row['keperluan'] . '</td>';
            echo '</tr>';
        }
    }
    elseif($jenis_laporan == 'denda') {
        echo '<tr>
            <th>No</th>
            <th>Kode Peminjaman</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Telepon</th>
            <th>Judul Buku</th>
            <th>Tgl Kembali (Rencana)</th>
            <th>Tgl Kembali (Aktual)</th>
            <th>Hari Terlambat</th>
            <th>Denda</th>
        </tr>';
        $no = 1;
        $total_denda = 0;
        foreach($data as $row) {
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . $row['kode_peminjaman'] . '</td>';
            echo '<td>' . $row['nim'] . '</td>';
            echo '<td>' . $row['nama_anggota'] . '</td>';
            echo '<td>' . $row['telepon'] . '</td>';
            echo '<td>' . $row['judul'] . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($row['tanggal_kembali_rencana'])) . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($row['tanggal_kembali_aktual'])) . '</td>';
            echo '<td>' . $row['hari_terlambat'] . ' hari</td>';
            echo '<td>Rp ' . number_format($row['denda'], 0, ',', '.') . '</td>';
            echo '</tr>';
            $total_denda += $row['denda'];
        }
        echo '<tr><td colspan="9"><strong>TOTAL DENDA</strong></td><td><strong>Rp ' . number_format($total_denda, 0, ',', '.') . '</strong></td></tr>';
    }
    elseif($jenis_laporan == 'buku_populer') {
        echo '<tr>
            <th>No</th>
            <th>Kode Buku</th>
            <th>Judul</th>
            <th>Pengarang</th>
            <th>Kategori</th>
            <th>Jumlah Dipinjam</th>
        </tr>';
        $no = 1;
        foreach($data as $row) {
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . $row['kode_buku'] . '</td>';
            echo '<td>' . $row['judul'] . '</td>';
            echo '<td>' . $row['pengarang'] . '</td>';
            echo '<td>' . $row['kategori'] . '</td>';
            echo '<td>' . $row['jumlah_dipinjam'] . '</td>';
            echo '</tr>';
        }
    }
    elseif($jenis_laporan == 'anggota_aktif') {
        echo '<tr>
            <th>No</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Jurusan</th>
            <th>Telepon</th>
            <th>Total Peminjaman</th>
            <th>Total Kunjungan</th>
        </tr>';
        $no = 1;
        foreach($data as $row) {
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . $row['nim'] . '</td>';
            echo '<td>' . $row['nama'] . '</td>';
            echo '<td>' . $row['jurusan'] . '</td>';
            echo '<td>' . $row['telepon'] . '</td>';
            echo '<td>' . $row['total_pinjam'] . '</td>';
            echo '<td>' . $row['total_kunjungan'] . '</td>';
            echo '</tr>';
        }
    }
    
    echo '</table>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Perpustakaan Kampus</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <h1>📊 Laporan Perpustakaan</h1>
            
            <div class="card">
                <h2>Filter Laporan</h2>
                <form method="GET" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jenis Laporan</label>
                            <select name="jenis" onchange="this.form.submit()">
                                <option value="peminjaman" <?= $jenis_laporan == 'peminjaman' ? 'selected' : '' ?>>Laporan Peminjaman</option>
                                <option value="kunjungan" <?= $jenis_laporan == 'kunjungan' ? 'selected' : '' ?>>Laporan Kunjungan</option>
                                <option value="denda" <?= $jenis_laporan == 'denda' ? 'selected' : '' ?>>Laporan Denda</option>
                                <option value="buku_populer" <?= $jenis_laporan == 'buku_populer' ? 'selected' : '' ?>>Buku Terpopuler</option>
                                <option value="anggota_aktif" <?= $jenis_laporan == 'anggota_aktif' ? 'selected' : '' ?>>Anggota Paling Aktif</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Dari Tanggal</label>
                            <input type="date" name="tanggal_dari" value="<?= $tanggal_dari ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Sampai Tanggal</label>
                            <input type="date" name="tanggal_sampai" value="<?= $tanggal_sampai ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">🔍 Tampilkan</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>
                        <?php
                        $judul_laporan = [
                            'peminjaman' => '📤 Laporan Peminjaman Buku',
                            'kunjungan' => '📋 Laporan Kunjungan Perpustakaan',
                            'denda' => '💰 Laporan Denda Keterlambatan',
                            'buku_populer' => '🏆 Buku Terpopuler',
                            'anggota_aktif' => '👑 Anggota Paling Aktif'
                        ];
                        echo $judul_laporan[$jenis_laporan];
                        ?>
                    </h2>
                    <div>
                        <a href="?jenis=<?= $jenis_laporan ?>&tanggal_dari=<?= $tanggal_dari ?>&tanggal_sampai=<?= $tanggal_sampai ?>&export=excel" class="btn btn-success">📥 Export Excel</a>
                        <button onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
                    </div>
                </div>
                
                <p><strong>Periode:</strong> <?= date('d/m/Y', strtotime($tanggal_dari)) ?> s/d <?= date('d/m/Y', strtotime($tanggal_sampai)) ?></p>
                
                <div class="table-responsive">
                    <?php if($jenis_laporan == 'peminjaman'): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>NIM</th>
                                <th>Nama</th>
                                <th>Jurusan</th>
                                <th>Buku</th>
                                <th>Tgl Pinjam</th>
                                <th>Tgl Kembali<br>(Rencana)</th>
                                <th>Tgl Kembali<br>(Aktual)</th>
                                <th>Status</th>
                                <th>Denda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($data as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= $row['kode_peminjaman'] ?></td>
                                <td><?= $row['nim'] ?></td>
                                <td><?= $row['nama_anggota'] ?></td>
                                <td><?= $row['jurusan'] ?></td>
                                <td><?= $row['judul'] ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_pinjam'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_kembali_rencana'])) ?></td>
                                <td><?= $row['tanggal_kembali_aktual'] ? date('d/m/Y', strtotime($row['tanggal_kembali_aktual'])) : '-' ?></td>
                                <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td><?= $row['denda'] > 0 ? 'Rp ' . number_format($row['denda'], 0, ',', '.') : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php elseif($jenis_laporan == 'kunjungan'): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>NIM</th>
                                <th>Nama</th>
                                <th>Jurusan</th>
                                <th>Waktu Masuk</th>
                                <th>Waktu Keluar</th>
                                <th>Durasi</th>
                                <th>Keperluan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($data as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_kunjungan'])) ?></td>
                                <td><?= $row['nim'] ?></td>
                                <td><?= $row['nama'] ?></td>
                                <td><?= $row['jurusan'] ?></td>
                                <td><?= $row['waktu_masuk'] ?></td>
                                <td><?= $row['waktu_keluar'] ?? '-' ?></td>
                                <td><?= $row['durasi'] ?? '-' ?></td>
                                <td><?= $row['keperluan'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php elseif($jenis_laporan == 'denda'): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>NIM</th>
                                <th>Nama</th>
                                <th>Telepon</th>
                                <th>Judul Buku</th>
                                <th>Tgl Kembali<br>(Rencana)</th>
                                <th>Tgl Kembali<br>(Aktual)</th>
                                <th>Terlambat</th>
                                <th>Denda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; $total_denda = 0; foreach($data as $row): 
                                $total_denda += $row['denda'];
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= $row['kode_peminjaman'] ?></td>
                                <td><?= $row['nim'] ?></td>
                                <td><?= $row['nama_anggota'] ?></td>
                                <td><?= $row['telepon'] ?></td>
                                <td><?= $row['judul'] ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_kembali_rencana'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_kembali_aktual'])) ?></td>
                                <td><?= $row['hari_terlambat'] ?> hari</td>
                                <td>Rp <?= number_format($row['denda'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="9" style="text-align: right;">TOTAL DENDA:</td>
                                <td>Rp <?= number_format($total_denda, 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <?php elseif($jenis_laporan == 'buku_populer'): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Peringkat</th>
                                <th>Kode Buku</th>
                                <th>Judul</th>
                                <th>Pengarang</th>
                                <th>Kategori</th>
                                <th>Jumlah Dipinjam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($data as $row): ?>
                            <tr>
                                <td>
                                    <?php 
                                    if($no == 1) echo '🥇';
                                    elseif($no == 2) echo '🥈';
                                    elseif($no == 3) echo '🥉';
                                    else echo $no;
                                    ?>
                                </td>
                                <td><?= $row['kode_buku'] ?></td>
                                <td><?= $row['judul'] ?></td>
                                <td><?= $row['pengarang'] ?></td>
                                <td><?= $row['kategori'] ?></td>
                                <td><strong><?= $row['jumlah_dipinjam'] ?></strong> kali</td>
                            </tr>
                            <?php $no++; endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Grafik Buku Populer -->
                    <div style="margin-top: 30px;">
                        <canvas id="chartBukuPopuler" height="80"></canvas>
                    </div>
                    
                    <script>
                        const ctxBuku = document.getElementById('chartBukuPopuler').getContext('2d');
                        new Chart(ctxBuku, {
                            type: 'bar',
                            data: {
                                labels: [<?php foreach($data as $row) echo "'" . addslashes(substr($row['judul'], 0, 30)) . "...',"; ?>],
                                datasets: [{
                                    label: 'Jumlah Peminjaman',
                                    data: [<?php foreach($data as $row) echo $row['jumlah_dipinjam'] . ','; ?>],
                                    backgroundColor: 'rgba(102, 126, 234, 0.6)',
                                    borderColor: 'rgba(102, 126, 234, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    </script>
                    
                    <?php elseif($jenis_laporan == 'anggota_aktif'): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Peringkat</th>
                                <th>NIM</th>
                                <th>Nama</th>
                                <th>Jurusan</th>
                                <th>Telepon</th>
                                <th>Total Peminjaman</th>
                                <th>Total Kunjungan</th>
                                <th>Total Aktivitas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($data as $row): ?>
                            <tr>
                                <td>
                                    <?php 
                                    if($no == 1) echo '🥇';
                                    elseif($no == 2) echo '🥈';
                                    elseif($no == 3) echo '🥉';
                                    else echo $no;
                                    ?>
                                </td>
                                <td><?= $row['nim'] ?></td>
                                <td><?= $row['nama'] ?></td>
                                <td><?= $row['jurusan'] ?></td>
                                <td><?= $row['telepon'] ?></td>
                                <td><?= $row['total_pinjam'] ?></td>
                                <td><?= $row['total_kunjungan'] ?></td>
                                <td><strong><?= $row['total_pinjam'] + $row['total_kunjungan'] ?></strong></td>
                            </tr>
                            <?php $no++; endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Grafik Anggota Aktif -->
                    <div style="margin-top: 30px;">
                        <canvas id="chartAnggotaAktif" height="80"></canvas>
                    </div>
                    
                    <script>
                        const ctxAnggota = document.getElementById('chartAnggotaAktif').getContext('2d');
                        new Chart(ctxAnggota, {
                            type: 'bar',
                            data: {
                                labels: [<?php foreach($data as $row) echo "'" . addslashes($row['nama']) . "',"; ?>],
                                datasets: [
                                    {
                                        label: 'Peminjaman',
                                        data: [<?php foreach($data as $row) echo $row['total_pinjam'] . ','; ?>],
                                        backgroundColor: 'rgba(102, 126, 234, 0.6)',
                                    },
                                    {
                                        label: 'Kunjungan',
                                        data: [<?php foreach($data as $row) echo $row['total_kunjungan'] . ','; ?>],
                                        backgroundColor: 'rgba(118, 75, 162, 0.6)',
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    </script>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 20px; text-align: center; color: #666;">
                    <p>Total Data: <strong><?= count($data) ?></strong> records</p>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        @media print {
            .sidebar, .header, .btn, .filter-form {
                display: none !important;
            }
            .main-content {
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
    </style>
</body>
</html>