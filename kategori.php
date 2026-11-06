<?php
require __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['hapus'])) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM produk WHERE kategori_id=?'); $s->execute([(int)$_POST['hapus']]);
            if ($s->fetchColumn() > 0) flash('err', 'Kategori masih dipakai produk, tidak bisa dihapus.');
            else { $pdo->prepare('DELETE FROM kategori WHERE id=?')->execute([(int)$_POST['hapus']]); flash('ok', 'Kategori dihapus.'); }
        } else {
            $nama = trim($_POST['nama'] ?? '');
            if ($nama === '') flash('err', 'Nama kategori wajib diisi.');
            elseif (!empty($_POST['id'])) { $pdo->prepare('UPDATE kategori SET nama=? WHERE id=?')->execute([$nama, (int)$_POST['id']]); flash('ok', 'Kategori diperbarui.'); }
            else { $pdo->prepare('INSERT INTO kategori(nama) VALUES(?)')->execute([$nama]); flash('ok', 'Kategori ditambahkan.'); }
        }
    } catch (PDOException $ex) { flash('err', 'Nama kategori sudah ada.'); }
    redirect('kategori.php');
}
$edit = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM kategori WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
$list = $pdo->query('SELECT k.*, (SELECT COUNT(*) FROM produk p WHERE p.kategori_id=k.id) jml FROM kategori k ORDER BY k.nama')->fetchAll();

$judul = 'Kategori Produk';
include __DIR__ . '/includes/header.php';
?>
<div class="grid g-side" style="grid-template-columns:minmax(0,1fr) 360px">
  <div class="card flush"><div class="table-wrap"><table class="tbl">
    <thead><tr><th>Nama kategori</th><th class="num">Jumlah produk</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $k): ?><tr>
      <td class="prod-name"><?= e($k['nama']) ?></td><td class="num"><span class="badge b-gold"><?= $k['jml'] ?> produk</span></td>
      <td class="actions"><a class="btn btn-sm" href="?edit=<?= $k['id'] ?>"><?= icon('edit') ?> Ubah</a>
        <form method="post" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-soft-danger" name="hapus" value="<?= $k['id'] ?>" data-confirm="Hapus kategori ini?" aria-label="Hapus"><?= icon('trash') ?></button></form></td></tr>
    <?php endforeach; if (!$list) echo '<tr><td colspan="3" class="empty">' . icon('tag') . 'Belum ada kategori.</td></tr>'; ?></tbody></table></div></div>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
    <h2 style="margin-bottom:1rem"><?= $edit ? 'Ubah kategori' : 'Tambah kategori' ?></h2>
    <label class="f">Nama kategori<input class="in" name="nama" value="<?= e($edit['nama'] ?? '') ?>" required maxlength="50" autofocus></label>
    <div class="form-actions"><button class="btn btn-gold"><?= icon('check') ?> <?= $edit ? 'Simpan' : 'Tambah' ?></button><?php if ($edit): ?><a href="kategori.php" class="btn">Batal</a><?php endif; ?></div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php';
