<?php
require __DIR__ . '/includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = ['id' => 0, 'kategori_id' => '', 'kode' => '', 'nama' => '', 'harga_beli' => '', 'harga_jual' => '', 'stok' => 0, 'stok_min' => 10, 'aktif' => 1];
if ($id) {
    $s = $pdo->prepare('SELECT * FROM produk WHERE id=?'); $s->execute([$id]);
    $p = $s->fetch();
    if (!$p) { flash('err', 'Produk tidak ditemukan.'); redirect('produk.php'); }
} else {   // saran kode berikutnya: P### tertinggi + 1
    $mx = 0; foreach ($pdo->query('SELECT kode FROM produk')->fetchAll(PDO::FETCH_COLUMN) as $k) if (preg_match('/^P(\d+)$/i', $k, $m)) $mx = max($mx, (int)$m[1]);
    $p['kode'] = 'P' . str_pad($mx + 1, 3, '0', STR_PAD_LEFT);
}
$kats = $pdo->query('SELECT id, nama FROM kategori ORDER BY nama')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = ['kategori_id' => (int)($_POST['kategori_id'] ?? 0) ?: null, 'kode' => trim($_POST['kode'] ?? ''), 'nama' => trim($_POST['nama'] ?? ''),
          'harga_beli' => (int)($_POST['harga_beli'] ?? 0), 'harga_jual' => (int)($_POST['harga_jual'] ?? 0),
          'stok' => (int)($_POST['stok'] ?? 0), 'stok_min' => (int)($_POST['stok_min'] ?? 0), 'aktif' => isset($_POST['aktif']) ? 1 : 0];
    $p = array_merge($p, $d);
    if ($d['kode'] === '' || $d['nama'] === '') flash('err', 'Kode dan nama produk wajib diisi.');
    elseif (min($d['harga_beli'], $d['harga_jual'], $d['stok'], $d['stok_min']) < 0) flash('err', 'Harga dan stok tidak boleh negatif.');
    else {
        try {
            if ($id) {
                $pdo->prepare('UPDATE produk SET kategori_id=?,kode=?,nama=?,harga_beli=?,harga_jual=?,stok=?,stok_min=?,aktif=? WHERE id=?')->execute(array_merge(array_values($d), [$id]));
                flash('ok', 'Perubahan produk disimpan.');
            } else {
                $pdo->prepare('INSERT INTO produk(kategori_id,kode,nama,harga_beli,harga_jual,stok,stok_min,aktif) VALUES(?,?,?,?,?,?,?,?)')->execute(array_values($d));
                flash('ok', 'Produk "' . $d['nama'] . '" ditambahkan.');
            }
            if ($d['harga_jual'] < $d['harga_beli']) flash('warn', 'Perhatian: harga jual lebih rendah dari harga beli (rugi per item).');
            redirect('produk.php');
        } catch (PDOException $ex) { flash('err', 'Gagal menyimpan. Pastikan kode produk unik.'); }
    }
}
$judul = $id ? 'Ubah Produk' : 'Tambah Produk';
$aksi = '<a href="produk.php" class="btn">' . icon('back') . ' Kembali</a>';
include __DIR__ . '/includes/header.php';
?>
<div class="grid g-side">
<form method="post" class="card"><?= csrf_field() ?>
  <h2 style="margin-bottom:1.2rem">Informasi produk</h2>
  <div class="form-grid">
    <label class="f">Kode produk<input class="in" name="kode" value="<?= e($p['kode']) ?>" required></label>
    <label class="f">Kategori<select class="in" name="kategori_id"><option value="">— Tanpa kategori —</option>
      <?php foreach ($kats as $k): ?><option value="<?= $k['id'] ?>" <?= (int)$p['kategori_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option><?php endforeach; ?></select></label>
    <label class="f full">Nama produk<input class="in" name="nama" value="<?= e($p['nama']) ?>" required autofocus></label>
    <label class="f">Harga beli (modal)<input class="in" type="number" min="0" name="harga_beli" id="beli" value="<?= e($p['harga_beli']) ?>" required></label>
    <label class="f">Harga jual<input class="in" type="number" min="0" name="harga_jual" id="jual" value="<?= e($p['harga_jual']) ?>" required></label>
    <label class="f">Stok saat ini<input class="in" type="number" min="0" name="stok" value="<?= e($p['stok']) ?>" required></label>
    <label class="f">Batas stok minimum<input class="in" type="number" min="0" name="stok_min" value="<?= e($p['stok_min']) ?>" required></label>
    <label class="check full"><input type="checkbox" name="aktif" <?= $p['aktif'] ? 'checked' : '' ?>> Produk aktif (tampil di kasir)</label>
  </div>
  <div class="form-actions"><button class="btn btn-gold"><?= icon('check') ?> Simpan produk</button><a href="produk.php" class="btn">Batal</a></div>
</form>
<div class="card"><h2 style="margin-bottom:.8rem">Perkiraan margin</h2>
  <div class="stat-val" id="mg-val">—</div><div class="muted small" id="mg-sub" style="margin-top:.3rem">Isi harga beli &amp; jual untuk melihat laba per item.</div></div>
</div>
<script>
(function(){var b=document.getElementById('beli'),j=document.getElementById('jual'),v=document.getElementById('mg-val'),s=document.getElementById('mg-sub');
function h(){var B=+b.value,J=+j.value;if(!J){v.textContent='—';return}var l=J-B;v.textContent=Math.round(l/J*100)+'%';v.style.color=l<0?'var(--bad)':'var(--ink)';
s.textContent='Laba per item Rp '+l.toLocaleString('id-ID')+(l<0?' (rugi)':'');}
b.oninput=j.oninput=h;h();})();
</script>
<?php include __DIR__ . '/includes/footer.php';
