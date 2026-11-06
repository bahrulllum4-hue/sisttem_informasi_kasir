<?php
require __DIR__ . '/includes/auth.php';
if (user()) redirect('dashboard.php');

$err = '';
$kunci = ($_SESSION['login_kunci'] ?? 0) - time();   // pembatas percobaan: 5x salah = tunggu 60 detik
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($kunci > 0) {
        $err = "Terlalu banyak percobaan. Coba lagi dalam {$kunci} detik.";
    } else {
        $s = $pdo->prepare('SELECT * FROM users WHERE username=? AND aktif=1');
        $s->execute([trim($_POST['username'] ?? '')]);
        $u = $s->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
            session_regenerate_id(true);
            unset($u['password']);
            $_SESSION['u'] = $u;
            unset($_SESSION['gagal'], $_SESSION['login_kunci']);
            redirect('dashboard.php');
        }
        $_SESSION['gagal'] = ($_SESSION['gagal'] ?? 0) + 1;
        if ($_SESSION['gagal'] >= 5) { $_SESSION['login_kunci'] = time() + 60; $_SESSION['gagal'] = 0; }
        $err = 'Username atau password salah, atau akun nonaktif.';
    }
}
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet"></head>
<body>
<div class="login">
  <section class="login-hero">
    <div class="brand"><span class="brand-mark"><?= icon('crown') ?></span><span><b><?= e(APP_NAME) ?></b><small><?= e(STORE_NAME) ?></small></span></div>
    <div>
      <h2>Kelola toko dengan <em>elegan</em> dan presisi.</h2>
      <ul>
        <li><?= icon('cart') ?>Kasir cepat dengan struk profesional</li>
        <li><?= icon('chart') ?>Laporan omzet &amp; laba yang jernih</li>
        <li><?= icon('star') ?>Prioritas restok cerdas dengan metode SAW</li>
      </ul>
    </div>
    <div class="legal">&copy; <?= date('Y') ?> <?= e(STORE_NAME) ?></div>
  </section>
  <section class="login-form">
    <form method="post" class="login-card" autocomplete="on">
      <?= csrf_field() ?>
      <div class="crumb">Selamat datang kembali</div>
      <h1>Masuk ke akun</h1>
      <p class="muted" style="margin:.2rem 0 1.6rem">Silakan masukkan kredensial Anda untuk melanjutkan.</p>
      <?php if ($err): ?><div class="alert err"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
      <label class="f">Username<input class="in" name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></label>
      <label class="f">Password
        <span class="pw"><input class="in" type="password" name="password" id="pw" required autocomplete="current-password">
        <button type="button" aria-label="Tampilkan password" onclick="var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'"><?= icon('eye') ?></button></span></label>
      <button class="btn btn-gold btn-block" style="margin-top:.6rem">Masuk <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></button>
    </form>
  </section>
</div>
</body></html>
