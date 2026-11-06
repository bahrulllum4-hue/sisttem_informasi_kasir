<?php
require __DIR__ . '/includes/auth.php';
require_login();

$produk = $pdo->query("SELECT p.id, p.kode, p.nama, p.harga_jual harga, p.stok, COALESCE(k.nama, 'Lainnya') kat
    FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id WHERE p.aktif = 1 ORDER BY p.nama")->fetchAll();
foreach ($produk as &$p) { $p['id'] = (int)$p['id']; $p['harga'] = (int)$p['harga']; $p['stok'] = (int)$p['stok']; }
unset($p);
$kategori = array_values(array_unique(array_column($produk, 'kat')));

$judul = 'Kasir';
$aksi = '<span class="badge b-gold">' . icon('key') . ' Tekan <b>/</b> untuk cari · <b>Enter</b> untuk tambah</span>';
$extra_js = ['kasir.js'];
include __DIR__ . '/includes/header.php';
?>
<div class="pos">
  <section>
    <div class="pos-tools">
      <div class="search"><?= icon('search') ?><input id="cari" class="in" placeholder="Cari nama produk atau scan kode…" autocomplete="off"></div>
    </div>
    <div class="chips mb" id="kat-chips">
      <button type="button" class="chip on" data-kat="">Semua</button>
      <?php foreach ($kategori as $k): ?><button type="button" class="chip" data-kat="<?= e($k) ?>"><?= e($k) ?></button><?php endforeach; ?>
    </div>
    <div class="pgrid" id="grid"></div>
    <div class="card empty" id="kosong" hidden><?= icon('search') ?>Produk tidak ditemukan.</div>
  </section>

  <aside class="card cart">
    <div class="cart-head"><h2><?= icon('cart') ?> Keranjang <span class="badge b-gold" id="jml">0</span></h2><button type="button" class="btn btn-sm" id="kosongkan" style="background:rgba(255,255,255,.08);color:#fff;border-color:transparent" hidden>Kosongkan</button></div>
    <div class="cart-body" id="cart-items"></div>
    <div class="cart-foot">
      <div class="sum"><span class="lbl">Total belanja</span><span class="big" id="total">Rp 0</span></div>
      <label class="f" style="margin-bottom:.5rem">Uang diterima<input class="in" type="number" id="bayar" min="0" inputmode="numeric" placeholder="0"></label>
      <div class="chips" id="quick"></div>
      <div class="kembali" id="kembali-box"><span>Kembalian</span><b id="kembali">Rp 0</b></div>
      <button type="button" class="btn btn-gold btn-block" id="btn-bayar" disabled><?= icon('check') ?> Bayar &amp; cetak struk</button>
    </div>
  </aside>
</div>
<script>window.KASIR = <?= json_encode(['produk' => $produk, 'csrf' => csrf_token()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php include __DIR__ . '/includes/footer.php';
