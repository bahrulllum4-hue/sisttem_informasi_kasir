<?php
require __DIR__ . '/includes/auth.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare('SELECT t.*, u.nama kasir FROM transaksi t JOIN users u ON u.id=t.user_id WHERE t.id=?');
$s->execute([$id]); $t = $s->fetch();
if (!$t) { flash('err', 'Transaksi tidak ditemukan.'); redirect('dashboard.php'); }
$s = $pdo->prepare('SELECT d.*, p.nama FROM detail_transaksi d JOIN produk p ON p.id=d.produk_id WHERE d.transaksi_id=? ORDER BY d.id');
$s->execute([$id]); $items = $s->fetchAll();
if (!empty($_GET['baru'])) flash('ok', no_trx($id) . ' berhasil disimpan. Kembalian ' . rp($t['kembali']) . '.');

$judul = 'Struk ' . no_trx($id);
$menu_aktif = 'kasir.php';
$aksi = '<a href="kasir.php" class="btn btn-gold">' . icon('plus') . ' Transaksi baru</a><button class="btn" onclick="window.print()">' . icon('print') . ' Cetak</button>';
include __DIR__ . '/includes/header.php';
?>
<div class="struk-wrap"><div class="struk">
  <div class="brand-s"><b><?= e(STORE_NAME) ?></b><?= e(STORE_ADDRESS) ?><br>Telp <?= e(STORE_PHONE) ?></div>
  <hr>
  <div class="r"><span>No</span><span><?= no_trx($t['id']) ?></span></div>
  <div class="r"><span>Tanggal</span><span><?= tgl_id($t['tanggal'], true) ?></span></div>
  <div class="r"><span>Kasir</span><span><?= e($t['kasir']) ?></span></div>
  <hr>
  <?php foreach ($items as $d): ?>
    <div class="it"><?= e($d['nama']) ?></div>
    <div class="r"><span><?= $d['qty'] ?> x <?= angka($d['harga']) ?></span><span><?= angka($d['qty'] * $d['harga']) ?></span></div>
  <?php endforeach; ?>
  <hr>
  <div class="r grand"><span>TOTAL</span><span><?= rp($t['total']) ?></span></div>
  <div class="r"><span>Bayar</span><span><?= rp($t['bayar']) ?></span></div>
  <div class="r"><span>Kembali</span><span><?= rp($t['kembali']) ?></span></div>
  <hr>
  <div class="foot">Terima kasih telah berbelanja!<br>Barang yang sudah dibeli tidak dapat dikembalikan.</div>
</div></div>
<?php include __DIR__ . '/includes/footer.php';
