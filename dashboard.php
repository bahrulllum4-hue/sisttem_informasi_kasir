<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/saw.php';
require_login();

$hariIni = date('Y-m-d 00:00:00');
$besok   = date('Y-m-d 00:00:00', strtotime('+1 day'));
$kemarin = date('Y-m-d 00:00:00', strtotime('-1 day'));
$awalBln = date('Y-m-01 00:00:00');
$tujuh   = date('Y-m-d 00:00:00', strtotime('-6 days'));
$tiga0   = date('Y-m-d H:i:s', strtotime('-30 days'));

function ringkas(PDO $pdo, $a, $b) {
    $s = $pdo->prepare('SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM transaksi WHERE tanggal>=? AND tanggal<?');
    $s->execute([$a, $b]); return $s->fetch();
}
$h = ringkas($pdo, $hariIni, $besok);
$k = ringkas($pdo, $kemarin, $hariIni);
$delta = $k['t'] > 0 ? round(($h['t'] - $k['t']) / $k['t'] * 100) : null;

$s = $pdo->prepare('SELECT COALESCE(SUM(d.qty*(d.harga-d.harga_beli)),0) l FROM detail_transaksi d JOIN transaksi t ON t.id=d.transaksi_id WHERE t.tanggal>=?');
$s->execute([$awalBln]); $labaBln = $s->fetch()['l'];

$menipis = $pdo->query('SELECT nama, stok, stok_min FROM produk WHERE aktif=1 AND stok<=stok_min ORDER BY stok, nama')->fetchAll();
$jmlProduk = $pdo->query('SELECT COUNT(*) c FROM produk WHERE aktif=1')->fetch()['c'];

$s = $pdo->prepare('SELECT DATE(tanggal) d, SUM(total) t FROM transaksi WHERE tanggal>=? AND tanggal<? GROUP BY DATE(tanggal)');
$s->execute([$tujuh, $besok]); $per = array_column($s->fetchAll(), 't', 'd');
$hr = ['Min','Sen','Sel','Rab','Kam','Jum','Sab']; $lab = []; $dat = [];
for ($i = 6; $i >= 0; $i--) { $t = strtotime("-$i days"); $lab[] = $hr[date('w', $t)] . ' ' . date('j/n', $t); $dat[] = (int)($per[date('Y-m-d', $t)] ?? 0); }

$s = $pdo->prepare('SELECT p.nama, SUM(d.qty) q FROM detail_transaksi d JOIN produk p ON p.id=d.produk_id JOIN transaksi t ON t.id=d.transaksi_id
    WHERE t.tanggal>=? GROUP BY p.id, p.nama ORDER BY q DESC LIMIT 5');
$s->execute([$tiga0]); $top = $s->fetchAll(); $maxQ = $top ? max(array_column($top, 'q')) : 1;

$terbaru = $pdo->query('SELECT t.*, u.nama kasir FROM transaksi t JOIN users u ON u.id=t.user_id ORDER BY t.tanggal DESC, t.id DESC LIMIT 6')->fetchAll();

$prioritas = [];
if (is_admin()) {
    $kr = $pdo->query('SELECT * FROM kriteria ORDER BY kode')->fetchAll();
    $prioritas = array_slice(saw_hitung(saw_data($pdo, 30), $kr), 0, 3);
}
$default = false;
if (is_admin()) { $s = $pdo->prepare('SELECT password FROM users WHERE id=?'); $s->execute([user()['id']]); $default = password_verify('admin123', $s->fetch()['password']); }

$jam = (int)date('G'); $salam = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));
$judul = 'Dashboard';
$aksi = '<a href="kasir.php" class="btn btn-gold">' . icon('plus') . ' Transaksi baru</a>';
include __DIR__ . '/includes/header.php';
?>
<?php if ($default): ?><div class="alert warn"><?= icon('shield') ?><span>Anda masih memakai password bawaan. <a href="pengguna.php"><b>Ganti sekarang</b></a> demi keamanan.</span></div><?php endif; ?>
<p class="muted" style="margin:-.3rem 0 1.3rem"><?= $salam ?>, <b style="color:var(--ink)"><?= e(user()['nama']) ?></b>. Berikut ringkasan performa toko Anda.</p>

<div class="grid g-4 mb">
  <div class="card stat"><div class="stat-top"><span class="stat-label">Omzet hari ini</span><span class="stat-ic"><?= icon('wallet') ?></span></div>
    <div class="stat-val"><?= rp($h['t']) ?></div>
    <?php if ($delta !== null): ?><div class="stat-sub <?= $delta >= 0 ? 'up' : 'down' ?>"><?= icon($delta >= 0 ? 'up' : 'down') ?> <?= abs($delta) ?>% dari kemarin</div><?php else: ?><div class="stat-sub">Belum ada data kemarin</div><?php endif; ?></div>
  <div class="card stat"><div class="stat-top"><span class="stat-label">Transaksi hari ini</span><span class="stat-ic"><?= icon('receipt') ?></span></div>
    <div class="stat-val"><?= angka($h['n']) ?></div><div class="stat-sub">Kemarin: <?= angka($k['n']) ?> transaksi</div></div>
  <div class="card stat"><div class="stat-top"><span class="stat-label">Laba bulan ini</span><span class="stat-ic"><?= icon('chart') ?></span></div>
    <div class="stat-val"><?= rp($labaBln) ?></div><div class="stat-sub">Sejak tanggal 1 <?= date('M Y') ?></div></div>
  <div class="card stat <?= $menipis ? 'warn' : '' ?>"><div class="stat-top"><span class="stat-label">Stok menipis</span><span class="stat-ic"><?= icon('box') ?></span></div>
    <div class="stat-val"><?= count($menipis) ?> <small class="muted" style="font-size:.9rem;font-family:var(--sans);font-weight:500">/ <?= $jmlProduk ?> produk</small></div>
    <div class="stat-sub"><?= $menipis ? 'Perlu segera diisi ulang' : 'Semua stok aman' ?></div></div>
</div>

<div class="grid g-main mb">
  <div class="card"><div class="card-head"><div><h2>Tren omzet 7 hari</h2><div class="muted small">Total <?= rp(array_sum($dat)) ?></div></div></div><?= svg_area($lab, $dat) ?></div>
  <div class="card"><div class="card-head"><h2>Produk terlaris</h2><span class="muted small">30 hari</span></div>
    <?php if (!$top) echo '<p class="muted">Belum ada penjualan.</p>'; foreach ($top as $i => $t): ?>
      <div style="margin-bottom:1rem"><div class="list-row" style="padding:0 0 .35rem;border:0"><span style="font-weight:600"><?= $i + 1 ?>. <?= e($t['nama']) ?></span><span class="muted small num"><?= angka($t['q']) ?> terjual</span></div>
      <div class="meter"><i style="width:<?= round($t['q'] / $maxQ * 100) ?>%"></i></div></div>
    <?php endforeach; ?></div>
</div>

<div class="grid g-main">
  <div class="card flush"><div class="card-head"><h2>Transaksi terbaru</h2><a href="laporan.php" class="small"><b>Lihat laporan</b></a></div>
    <div class="table-wrap"><table class="tbl"><tbody>
    <?php foreach ($terbaru as $r): ?><tr class="click" onclick="location='struk.php?id=<?= $r['id'] ?>'">
      <td><b><?= no_trx($r['id']) ?></b><div class="prod-sub"><?= tgl_id($r['tanggal'], true) ?></div></td>
      <td class="muted small"><?= e($r['kasir']) ?></td><td class="num serif" style="font-weight:700"><?= rp($r['total']) ?></td></tr>
    <?php endforeach; if (!$terbaru) echo '<tr><td class="empty">Belum ada transaksi.</td></tr>'; ?></tbody></table></div></div>
  <div class="grid" style="align-content:start">
    <?php if ($prioritas): ?>
    <div class="card"><div class="card-head"><h2>Prioritas restok</h2><a href="saw.php" class="small"><b>Detail SAW</b></a></div>
      <?php foreach ($prioritas as $p): ?><div class="list-row"><span style="display:flex;gap:.7rem;align-items:center"><span class="rank-no <?= $p['rank'] === 1 ? 'top' : '' ?>"><?= $p['rank'] ?></span><span><span class="prod-name"><?= e($p['nama']) ?></span><div class="prod-sub">Sisa <?= $p['stok'] ?> · V = <?= number_format($p['v'], 3) ?></div></span></span>
        <span class="badge <?= ['tinggi' => 'b-bad', 'sedang' => 'b-warn', 'rendah' => 'b-ok'][$p['level']] ?>"><?= saw_label($p['level']) ?></span></div><?php endforeach; ?></div>
    <?php endif; ?>
    <div class="card"><div class="card-head"><h2>Stok hampir habis</h2></div>
      <?php if (!$menipis): ?><p class="muted" style="margin:0"><?= icon('check', 'up') ?> Semua stok aman.</p><?php endif; ?>
      <?php foreach (array_slice($menipis, 0, 6) as $m): ?><div class="list-row"><span class="prod-name"><?= e($m['nama']) ?></span><span class="badge <?= $m['stok'] <= $m['stok_min'] / 2 ? 'b-bad' : 'b-warn' ?>">sisa <?= $m['stok'] ?></span></div><?php endforeach; ?></div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php';
