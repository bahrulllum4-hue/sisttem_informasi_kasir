<?php
require __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id = (int)$_POST['hapus'];
    try {
        $pdo->prepare('DELETE FROM produk WHERE id=?')->execute([$id]);
        flash('ok', 'Produk dihapus.');
    } catch (PDOException $ex) {   // sudah pernah terjual → diarsipkan agar riwayat laporan tetap utuh
        $pdo->prepare('UPDATE produk SET aktif=0 WHERE id=?')->execute([$id]);
        flash('warn', 'Produk sudah pernah terjual, sehingga diarsipkan (nonaktif) — bukan dihapus.');
    }
    redirect('produk.php');
}

$list = $pdo->query('SELECT p.*, k.nama kategori FROM produk p LEFT JOIN kategori k ON k.id=p.kategori_id ORDER BY p.aktif DESC, p.nama')->fetchAll();
$kats = $pdo->query('SELECT nama FROM kategori ORDER BY nama')->fetchAll(PDO::FETCH_COLUMN);
$nilai = 0; $tipis = 0;
foreach ($list as $p) if ($p['aktif']) { $nilai += $p['harga_beli'] * $p['stok']; if ($p['stok'] <= $p['stok_min']) $tipis++; }

$judul = 'Produk & Stok';
$aksi = '<a href="produk_form.php" class="btn btn-gold">' . icon('plus') . ' Tambah produk</a>';
include __DIR__ . '/includes/header.php';
?>
<div class="grid g-3 mb">
  <div class="card stat"><div class="stat-top"><span class="stat-label">Produk aktif</span><span class="stat-ic"><?= icon('box') ?></span></div><div class="stat-val"><?= count(array_filter($list, fn($p) => $p['aktif'])) ?></div></div>
  <div class="card stat"><div class="stat-top"><span class="stat-label">Nilai persediaan (modal)</span><span class="stat-ic"><?= icon('wallet') ?></span></div><div class="stat-val"><?= rp($nilai) ?></div></div>
  <div class="card stat <?= $tipis ? 'warn' : '' ?>"><div class="stat-top"><span class="stat-label">Stok di bawah minimum</span><span class="stat-ic"><?= icon('alert') ?></span></div><div class="stat-val"><?= $tipis ?></div></div>
</div>
<div class="card flush">
  <div class="card-head"><div class="search" style="min-width:260px;flex:1;max-width:420px"><?= icon('search') ?><input id="cari" class="in" data-filter="#tabel tbody tr[data-search]" placeholder="Cari kode atau nama produk…"></div>
    <div class="chips"><button class="chip on" data-chip-for="cari" data-val="">Semua</button><?php foreach ($kats as $k): ?><button class="chip" data-chip-for="cari" data-val="<?= e(strtolower($k)) ?>"><?= e($k) ?></button><?php endforeach; ?></div></div>
  <div class="table-wrap"><table class="tbl" id="tabel">
    <thead><tr><th>Produk</th><th>Kategori</th><th class="num">Harga beli</th><th class="num">Harga jual</th><th class="num">Margin</th><th class="num">Stok</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $p):
      $mg = $p['harga_jual'] > 0 ? round(($p['harga_jual'] - $p['harga_beli']) / $p['harga_jual'] * 100) : 0;
      $cls = $p['stok'] <= $p['stok_min'] / 2 ? 'b-bad' : ($p['stok'] <= $p['stok_min'] ? 'b-warn' : 'b-ok'); ?>
      <tr data-search="<?= e(strtolower($p['kode'] . ' ' . $p['nama'])) ?>" data-kat="<?= e(strtolower($p['kategori'] ?? '')) ?>" style="<?= $p['aktif'] ? '' : 'opacity:.55' ?>">
        <td><div class="prod-name"><?= e($p['nama']) ?> <?= $p['aktif'] ? '' : '<span class="badge b-mute">Arsip</span>' ?></div><div class="prod-sub"><?= e($p['kode']) ?></div></td>
        <td class="muted"><?= e($p['kategori'] ?? '—') ?></td>
        <td class="num"><?= rp($p['harga_beli']) ?></td><td class="num serif" style="font-weight:700"><?= rp($p['harga_jual']) ?></td>
        <td class="num"><span class="badge <?= $mg < 0 ? 'b-bad' : 'b-gold' ?>"><?= $mg ?>%</span></td>
        <td class="num"><span class="badge <?= $cls ?>"><?= $p['stok'] ?></span></td>
        <td class="actions"><a class="btn btn-sm" href="produk_form.php?id=<?= $p['id'] ?>"><?= icon('edit') ?> Ubah</a>
          <form method="post" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-soft-danger" name="hapus" value="<?= $p['id'] ?>" data-confirm="Hapus produk &quot;<?= e($p['nama']) ?>&quot;?" aria-label="Hapus"><?= icon('trash') ?></button></form></td></tr>
    <?php endforeach; ?>
    <tr id="filter-kosong" style="display:none"><td colspan="7" class="empty"><?= icon('search') ?>Tidak ada produk yang cocok.</td></tr>
    </tbody></table></div>
</div>
<?php include __DIR__ . '/includes/footer.php';
