<?php
require __DIR__ . '/includes/auth.php';
require_admin();

$dari   = tgl_valid($_GET['dari'] ?? '', date('Y-m-01'));
$sampai = tgl_valid($_GET['sampai'] ?? '', date('Y-m-d'));
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
if (strtotime($sampai) - strtotime($dari) > 365 * 86400) $dari = date('Y-m-d', strtotime($sampai . ' -365 days'));
$a = $dari . ' 00:00:00';
$b = date('Y-m-d 00:00:00', strtotime($sampai . ' +1 day'));

if (($_GET['export'] ?? '') === 'csv') {   // satu baris per item; pemisah titik koma agar rapi di Excel Indonesia
    $s = $pdo->prepare('SELECT t.id, t.tanggal, u.nama kasir, p.kode, p.nama produk, d.qty, d.harga, d.qty*d.harga subtotal, d.qty*(d.harga-d.harga_beli) laba
        FROM detail_transaksi d JOIN transaksi t ON t.id=d.transaksi_id JOIN users u ON u.id=t.user_id JOIN produk p ON p.id=d.produk_id
        WHERE t.tanggal>=? AND t.tanggal<? ORDER BY t.tanggal, t.id');
    $s->execute([$a, $b]);
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=laporan-$dari-sd-$sampai.csv");
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['No Transaksi', 'Tanggal', 'Kasir', 'Kode', 'Produk', 'Qty', 'Harga', 'Subtotal', 'Laba'], ';');
    foreach ($s as $r) { $r['id'] = no_trx($r['id']); fputcsv($o, $r, ';'); }
    exit;
}

$s = $pdo->prepare('SELECT t.*, u.nama kasir FROM transaksi t JOIN users u ON u.id=t.user_id WHERE t.tanggal>=? AND t.tanggal<? ORDER BY t.tanggal DESC, t.id DESC');
$s->execute([$a, $b]); $rows = $s->fetchAll();
$omzet = array_sum(array_column($rows, 'total'));
$jmlTrx = count($rows); $perHal = 20; $hal = max(1, (int)($_GET['hal'] ?? 1)); $totHal = max(1, (int)ceil($jmlTrx / $perHal)); $hal = min($hal, $totHal);
$semua = $rows; $rows = array_slice($rows, ($hal - 1) * $perHal, $perHal);

$items = [];
if ($semua) {
    $s = $pdo->prepare('SELECT d.transaksi_id, d.qty, p.nama FROM detail_transaksi d JOIN produk p ON p.id=d.produk_id JOIN transaksi t ON t.id=d.transaksi_id WHERE t.tanggal>=? AND t.tanggal<?');
    $s->execute([$a, $b]);
    foreach ($s as $r) $items[$r['transaksi_id']][] = $r['qty'] . '× ' . $r['nama'];
}
$s = $pdo->prepare('SELECT DATE(t.tanggal) d, SUM(d.qty*(d.harga-d.harga_beli)) l FROM detail_transaksi d JOIN transaksi t ON t.id=d.transaksi_id WHERE t.tanggal>=? AND t.tanggal<? GROUP BY DATE(t.tanggal)');
$s->execute([$a, $b]); $labaH = array_column($s->fetchAll(), 'l', 'd'); $laba = array_sum($labaH);
$omzetH = [];
foreach ($semua as $r) { $k = substr($r['tanggal'], 0, 10); $omzetH[$k] = ($omzetH[$k] ?? 0) + $r['total']; }
$lab = []; $dO = []; $dL = [];
for ($t = strtotime($dari); $t <= strtotime($sampai); $t += 86400) {
    $k = date('Y-m-d', $t); $lab[] = date('j/n', $t); $dO[] = (int)($omzetH[$k] ?? 0); $dL[] = (int)($labaH[$k] ?? 0);
}
$s = $pdo->prepare('SELECT p.nama, SUM(d.qty) q, SUM(d.qty*d.harga) om FROM detail_transaksi d JOIN produk p ON p.id=d.produk_id JOIN transaksi t ON t.id=d.transaksi_id
    WHERE t.tanggal>=? AND t.tanggal<? GROUP BY p.id, p.nama ORDER BY q DESC LIMIT 5');
$s->execute([$a, $b]); $top = $s->fetchAll();

$q = fn($x, $y) => '?dari=' . $x . '&sampai=' . $y;
$judul = 'Laporan Penjualan';
$aksi = '<a class="btn" href="' . $q($dari, $sampai) . '&export=csv">' . icon('download') . ' Ekspor CSV</a><button class="btn btn-dark" onclick="window.print()">' . icon('print') . ' Cetak</button>';
include __DIR__ . '/includes/header.php';
?>
<form class="card mb no-print"><div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
  <label class="f" style="margin:0">Dari<input type="date" class="in" name="dari" value="<?= e($dari) ?>"></label>
  <label class="f" style="margin:0">Sampai<input type="date" class="in" name="sampai" value="<?= e($sampai) ?>"></label>
  <button class="btn btn-gold"><?= icon('calendar') ?> Tampilkan</button>
  <div class="chips" style="margin-left:auto">
    <a class="chip" href="<?= $q(date('Y-m-d'), date('Y-m-d')) ?>">Hari ini</a><a class="chip" href="<?= $q(date('Y-m-d', strtotime('-6 days')), date('Y-m-d')) ?>">7 hari</a>
    <a class="chip" href="<?= $q(date('Y-m-d', strtotime('-29 days')), date('Y-m-d')) ?>">30 hari</a><a class="chip" href="<?= $q(date('Y-m-01'), date('Y-m-d')) ?>">Bulan ini</a></div>
</div></form>

<div class="muted small mb">Periode: <b style="color:var(--ink)"><?= tgl_id($dari) ?> – <?= tgl_id($sampai) ?></b></div>
<div class="grid g-4 mb">
  <div class="card stat"><div class="stat-top"><span class="stat-label">Omzet</span><span class="stat-ic"><?= icon('wallet') ?></span></div><div class="stat-val"><?= rp($omzet) ?></div></div>
  <div class="card stat"><div class="stat-top"><span class="stat-label">Laba kotor</span><span class="stat-ic"><?= icon('chart') ?></span></div><div class="stat-val"><?= rp($laba) ?></div><div class="stat-sub"><?= $omzet ? round($laba / $omzet * 100) : 0 ?>% dari omzet</div></div>
  <div class="card stat"><div class="stat-top"><span class="stat-label">Transaksi</span><span class="stat-ic"><?= icon('receipt') ?></span></div><div class="stat-val"><?= angka($jmlTrx) ?></div></div>
  <div class="card stat"><div class="stat-top"><span class="stat-label">Rata-rata / transaksi</span><span class="stat-ic"><?= icon('cart') ?></span></div><div class="stat-val"><?= rp($jmlTrx ? $omzet / $jmlTrx : 0) ?></div></div>
</div>

<div class="grid g-main mb">
  <div class="card"><div class="card-head"><h2>Omzet &amp; laba harian</h2><div class="legend"><span><i style="background:var(--ink-3)"></i>Omzet</span><span><i style="background:var(--gold)"></i>Laba</span></div></div><?= svg_bars($lab, $dO, $dL) ?></div>
  <div class="card"><div class="card-head"><h2>Produk terlaris</h2></div>
    <?php if (!$top) echo '<p class="muted">Tidak ada data pada periode ini.</p>'; foreach ($top as $i => $t): ?>
      <div class="list-row"><span style="display:flex;gap:.7rem;align-items:center"><span class="rank-no <?= $i === 0 ? 'top' : '' ?>"><?= $i + 1 ?></span><span class="prod-name"><?= e($t['nama']) ?></span></span>
      <span class="num small"><b><?= angka($t['q']) ?></b> pcs<br><span class="muted"><?= rp($t['om']) ?></span></span></div>
    <?php endforeach; ?></div>
</div>

<div class="card flush"><div class="card-head"><h2>Daftar transaksi</h2><span class="muted small no-print">Klik baris untuk membuka struk</span></div>
<div class="table-wrap"><table class="tbl">
  <thead><tr><th>No</th><th>Waktu</th><th>Kasir</th><th>Item</th><th class="num">Total</th><th class="num">Bayar</th><th class="num">Kembali</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr class="click" onclick="location='struk.php?id=<?= $r['id'] ?>'">
    <td class="nowrap"><b><?= no_trx($r['id']) ?></b></td><td class="nowrap small"><?= tgl_id($r['tanggal'], true) ?></td><td class="small nowrap"><?= e($r['kasir']) ?></td>
    <td class="small muted" style="max-width:340px"><?= e(implode(', ', $items[$r['id']] ?? [])) ?></td>
    <td class="num serif" style="font-weight:700"><?= rp($r['total']) ?></td><td class="num"><?= rp($r['bayar']) ?></td><td class="num"><?= rp($r['kembali']) ?></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="empty">' . icon('receipt') . 'Belum ada transaksi pada periode ini.</td></tr>'; ?></tbody>
  <?php if ($rows): ?><tfoot><tr><td colspan="4">Total periode (<?= angka($jmlTrx) ?> transaksi)</td><td class="num serif"><?= rp($omzet) ?></td><td colspan="2"></td></tr></tfoot><?php endif; ?></table></div>
<?php if ($totHal > 1): $pq = fn($h) => '?dari=' . $dari . '&sampai=' . $sampai . '&hal=' . $h; ?>
  <div class="pager no-print"><span class="muted small">Halaman <?= $hal ?> dari <?= $totHal ?> · menampilkan <?= count($rows) ?> dari <?= angka($jmlTrx) ?></span>
    <span class="chips"><?php if ($hal > 1): ?><a class="chip" href="<?= $pq($hal - 1) ?>">‹ Sebelumnya</a><?php endif; ?>
    <?php for ($i = max(1, $hal - 2); $i <= min($totHal, $hal + 2); $i++): ?><a class="chip <?= $i === $hal ? 'on' : '' ?>" href="<?= $pq($i) ?>"><?= $i ?></a><?php endfor; ?>
    <?php if ($hal < $totHal): ?><a class="chip" href="<?= $pq($hal + 1) ?>">Berikutnya ›</a><?php endif; ?></span></div>
<?php endif; ?></div>
<?php include __DIR__ . '/includes/footer.php';
