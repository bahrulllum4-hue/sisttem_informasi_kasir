<?php
require __DIR__ . '/includes/auth.php';
require_login();
$me = user(); $admin = is_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['aksi'] ?? '';
    try {
        if ($act === 'profil') {                                   // ganti nama / password sendiri
            $s = $pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$me['id']]); $u = $s->fetch();
            $nama = trim($_POST['nama'] ?? ''); $lama = $_POST['lama'] ?? ''; $baru = $_POST['baru'] ?? '';
            if ($nama === '') flash('err', 'Nama tidak boleh kosong.');
            elseif ($baru !== '' && !password_verify($lama, $u['password'])) flash('err', 'Password lama salah.');
            elseif ($baru !== '' && strlen($baru) < 6) flash('err', 'Password baru minimal 6 karakter.');
            elseif ($baru !== ($_POST['ulang'] ?? '')) flash('err', 'Konfirmasi password tidak sama.');
            else {
                $pdo->prepare('UPDATE users SET nama=?, password=? WHERE id=?')->execute([$nama, $baru !== '' ? password_hash($baru, PASSWORD_DEFAULT) : $u['password'], $me['id']]);
                $_SESSION['u']['nama'] = $nama; flash('ok', 'Akun Anda diperbarui.');
            }
        } elseif ($admin && $act === 'simpan') {                    // tambah / ubah pengguna
            $id = (int)($_POST['id'] ?? 0); $un = trim($_POST['username'] ?? ''); $nama = trim($_POST['nama'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'kasir'; $aktif = isset($_POST['aktif']) ? 1 : 0; $pw = $_POST['password'] ?? '';
            if ($id === (int)$me['id']) { $role = 'admin'; $aktif = 1; }   // admin tidak bisa menurunkan/menonaktifkan dirinya
            if (!preg_match('/^[a-z0-9_.]{3,30}$/i', $un)) flash('err', 'Username 3–30 karakter (huruf, angka, titik, garis bawah).');
            elseif ($nama === '') flash('err', 'Nama wajib diisi.');
            elseif (!$id && strlen($pw) < 6) flash('err', 'Password minimal 6 karakter.');
            elseif ($pw !== '' && strlen($pw) < 6) flash('err', 'Password minimal 6 karakter.');
            elseif ($id) {
                $pdo->prepare('UPDATE users SET username=?, nama=?, role=?, aktif=? WHERE id=?')->execute([$un, $nama, $role, $aktif, $id]);
                if ($pw !== '') $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
                flash('ok', 'Pengguna diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO users(username,password,nama,role,aktif) VALUES(?,?,?,?,?)')->execute([$un, password_hash($pw, PASSWORD_DEFAULT), $nama, $role, $aktif]);
                flash('ok', 'Pengguna "' . $nama . '" ditambahkan.');
            }
        } elseif ($admin && $act === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$me['id']) flash('err', 'Anda tidak bisa menghapus akun sendiri.');
            else {
                try { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]); flash('ok', 'Pengguna dihapus.'); }
                catch (PDOException $ex) { $pdo->prepare('UPDATE users SET aktif=0 WHERE id=?')->execute([$id]); flash('warn', 'Pengguna punya riwayat transaksi, sehingga dinonaktifkan (bukan dihapus).'); }
            }
        }
    } catch (PDOException $ex) { flash('err', 'Username sudah dipakai pengguna lain.'); }
    redirect('pengguna.php');
}

$edit = null;
if ($admin && isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
$list = $admin ? $pdo->query('SELECT u.*, (SELECT COUNT(*) FROM transaksi t WHERE t.user_id=u.id) trx FROM users u ORDER BY u.role, u.nama')->fetchAll() : [];

$judul = $admin ? 'Pengguna & Akun' : 'Akun Saya';
include __DIR__ . '/includes/header.php';
?>
<div class="grid <?= $admin ? 'g-side' : '' ?>" style="<?= $admin ? 'grid-template-columns:minmax(0,1fr) 380px' : 'max-width:520px' ?>">
<?php if ($admin): ?>
  <div class="card flush"><div class="card-head"><h2>Daftar pengguna</h2></div><div class="table-wrap"><table class="tbl">
    <thead><tr><th>Pengguna</th><th>Peran</th><th>Status</th><th class="num">Transaksi</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $u): ?><tr>
      <td style="display:flex;gap:.7rem;align-items:center"><span class="avatar" style="background:var(--ink);width:36px;height:36px"><?= e(strtoupper(mb_substr($u['nama'], 0, 1))) ?></span><span><span class="prod-name"><?= e($u['nama']) ?></span><div class="prod-sub">@<?= e($u['username']) ?></div></span></td>
      <td><span class="badge <?= $u['role'] === 'admin' ? 'b-gold' : 'b-info' ?>"><?= $u['role'] === 'admin' ? 'Admin' : 'Kasir' ?></span></td>
      <td><span class="badge <?= $u['aktif'] ? 'b-ok' : 'b-mute' ?>"><?= $u['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
      <td class="num"><?= angka($u['trx']) ?></td>
      <td class="actions"><a class="btn btn-sm" href="?edit=<?= $u['id'] ?>"><?= icon('edit') ?> Ubah</a>
        <?php if ($u['id'] != $me['id']): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm btn-soft-danger" data-confirm="Hapus pengguna ini?" aria-label="Hapus"><?= icon('trash') ?></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div></div>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
    <h2 style="margin-bottom:1rem"><?= $edit ? 'Ubah pengguna' : 'Tambah pengguna' ?></h2>
    <label class="f">Nama lengkap<input class="in" name="nama" value="<?= e($edit['nama'] ?? '') ?>" required></label>
    <label class="f">Username<input class="in" name="username" value="<?= e($edit['username'] ?? '') ?>" required autocomplete="off"></label>
    <label class="f">Password <?= $edit ? '<span style="text-transform:none;letter-spacing:0;font-weight:500">(kosongkan jika tidak diganti)</span>' : '' ?><input class="in" type="password" name="password" <?= $edit ? '' : 'required' ?> autocomplete="new-password"></label>
    <label class="f">Peran<select class="in" name="role"><option value="kasir" <?= ($edit['role'] ?? '') === 'kasir' ? 'selected' : '' ?>>Kasir</option><option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option></select></label>
    <label class="check"><input type="checkbox" name="aktif" <?= !$edit || $edit['aktif'] ? 'checked' : '' ?>> Akun aktif</label>
    <div class="form-actions"><button class="btn btn-gold"><?= icon('check') ?> Simpan</button><?php if ($edit): ?><a class="btn" href="pengguna.php">Batal</a><?php endif; ?></div>
  </form>
<?php endif; ?>
  <form method="post" class="card" <?= $admin ? 'style="grid-column:1/-1;max-width:520px"' : '' ?>><?= csrf_field() ?><input type="hidden" name="aksi" value="profil">
    <h2 style="margin-bottom:1rem"><?= icon('shield') ?> Akun saya</h2>
    <label class="f">Nama tampilan<input class="in" name="nama" value="<?= e($me['nama']) ?>" required></label>
    <div class="muted small" style="margin:.2rem 0 .8rem">Ganti password — kosongkan jika tidak ingin mengubah.</div>
    <label class="f">Password lama<input class="in" type="password" name="lama" autocomplete="current-password"></label>
    <label class="f">Password baru<input class="in" type="password" name="baru" autocomplete="new-password" placeholder="Minimal 6 karakter"></label>
    <label class="f">Ulangi password baru<input class="in" type="password" name="ulang" autocomplete="new-password"></label>
    <button class="btn btn-dark"><?= icon('check') ?> Simpan akun</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php';
