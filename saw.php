<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/saw.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {          // simpan bobot (dalam persen, total harus 100)
    $w = array_map('intval', $_POST['bobot'] ?? []);
    if (array_sum($w) !== 100 || min($w ?: [0]) < 0) flash('err', 'Total bobot harus tepat 100%.');
    else {
        foreach ($w as $id => $b) $pdo->prepare('UPDATE kriteria SET bobot=? WHERE id=?')->execute([$b / 100, (int)$id]);
        flash('ok', 'Bobot kriteria disimpan. Peringkat dihitung ulang.');
    }
    redirect('saw.php?hari=' . (int)($_POST['hari'] ?? 30));
}
$hari = (int)($_GET['hari'] ?? 30);
if (!in_array($hari, [7, 14, 30, 60, 90], true)) $hari = 30;
$kr = $pdo->query('SELECT * FROM kriteria ORDER BY kode')->fetchAll();
$X = saw_data($pdo, $hari);
$hasil = saw_hitung($X, $kr);
$maxV = $hasil ? max(array_column($hasil, 'v')) : 1;
$lv = ['tinggi' => 'b-bad', 'sedang' => 'b-warn', 'rendah' => 'b-ok'];
$fmt = ['c1' => fn($v) => angka($v) . ' pcs', 'c2' => fn($v) => rp($v), 'c3' => fn($v) => angka($v) . ' pcs', 'c4' => fn($v) => angka($v) . '×'];

$judul = 'Prioritas Restok';
$aksi = '<form method="get" class="inline"><select class="in" name="hari" onchange="this.form.submit()" style="margin:0;width:auto;padding:.55rem 2rem .55rem .9rem">' .
    implode('', array_map(fn($d) => '<option value="' . $d . '"' . ($d === $hari ? ' selected' : '') . '>Data ' . $d . ' hari terakhir</option>', [7, 14, 30, 60, 90])) . '</select></form>';
include __DIR__ . '/includes/header.php';
?>
<p class="muted" style="max-width:760px;margin:-.4rem 0 1.4rem">Metode <b style="color:var(--ink)">Simple Additive Weighting (SAW)</b> menilai tiap produk dari kriteria berbobot. Nilai preferensi <b>V</b> tertinggi berarti produk itu paling mendesak untuk diisi ulang: laris, menguntungkan, dan stoknya tipis.</p>

<div class="grid g-side">
  <div data-tabs>
    <div class="tabs"><button class="tab on" data-tab="rank">Peringkat</button><button class="tab" data-tab="x">1 · Matriks keputusan</button><button class="tab" data-tab="r">2 · Normalisasi</button><button class="tab" data-tab="v">3 · Nilai preferensi</button></div>

    <div data-pane="rank"><div class="card" style="padding:.4rem 1.4rem">
      <?php if (!$hasil) echo '<p class="muted">Belum ada produk aktif.</p>'; foreach ($hasil as $h): ?>
        <div class="list-row" style="padding:1rem 0;align-items:flex-start;gap:1rem">
          <span class="rank-no <?= $h['rank'] <= 3 ? 'top' : '' ?>"><?= $h['rank'] ?></span>
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap"><span><span class="prod-name"><?= e($h['nama']) ?></span> <span class="muted small">· <?= e($h['kategori'] ?? '—') ?></span></span>
              <span class="badge <?= $lv[$h['level']] ?>"><?= saw_label($h['level']) ?></span></div>
            <div class="meter <?= $h['level'] === 'tinggi' ? 'hot' : '' ?>" style="margin:.55rem 0 .35rem"><i style="width:<?= round($h['v'] / max($maxV, 0.0001) * 100) ?>%"></i></div>
            <div class="muted small">Terjual <?= angka($h['c1']) ?> · Laba <?= rp($h['c2']) ?> · Sisa stok <b style="color:var(--ink)"><?= $h['c3'] ?></b> · V = <b class="serif" style="color:var(--ink)"><?= number_format($h['v'], 3) ?></b></div>
          </div></div>
      <?php endforeach; ?></div></div>

    <div data-pane="x" hidden><div class="card flush"><div class="card-head"><h2>Matriks keputusan (X)</h2></div><div class="table-wrap"><table class="tbl compact">
      <thead><tr><th>Produk</th><?php foreach ($kr as $k): ?><th class="num"><?= e($k['kode']) ?><br><span style="text-transform:none;letter-spacing:0;font-weight:500"><?= e($k['nama']) ?></span></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($X as $r): ?><tr><td class="prod-name"><?= e($r['nama']) ?></td><?php foreach ($kr as $k): $key = strtolower($k['kode']); ?><td class="num"><?= $fmt[$key]($r[$key]) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div></div>

    <div data-pane="r" hidden><div class="card flush"><div class="card-head"><h2>Matriks ternormalisasi (R)</h2></div>
      <div style="padding:0 1.4rem 1rem"><div class="formula"><b>Benefit</b>: r = x ÷ max(x) &nbsp;·&nbsp; <b>Cost</b>: r = (min(x) + 1) ÷ (x + 1)<br><span style="opacity:.7">Kriteria sisa stok bertipe <i>cost</i>: makin sedikit stok, makin besar nilainya. Angka +1 mencegah pembagian nol saat stok 0.</span></div></div>
      <div class="table-wrap"><table class="tbl compact"><thead><tr><th>Produk</th><?php foreach ($kr as $k): ?><th class="num"><?= e($k['kode']) ?> <span class="badge b-mute" style="text-transform:none;letter-spacing:0"><?= $k['tipe'] ?></span></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($hasil as $h): ?><tr><td class="prod-name"><?= e($h['nama']) ?></td><?php foreach ($kr as $k): ?><td class="num"><?= number_format($h['r'][strtolower($k['kode'])], 3) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div></div>

    <div data-pane="v" hidden><div class="card flush"><div class="card-head"><h2>Nilai preferensi (V)</h2></div>
      <div style="padding:0 1.4rem 1rem"><div class="formula"><b>V<sub>i</sub></b> = Σ ( bobot<sub>j</sub> × r<sub>ij</sub> )</div></div>
      <div class="table-wrap"><table class="tbl compact"><thead><tr><th>#</th><th>Produk</th><?php foreach ($kr as $k): ?><th class="num"><?= e($k['kode']) ?> × <?= round($k['bobot'] * 100) ?>%</th><?php endforeach; ?><th class="num">V</th></tr></thead><tbody>
      <?php foreach ($hasil as $h): ?><tr><td><?= $h['rank'] ?></td><td class="prod-name"><?= e($h['nama']) ?></td><?php foreach ($kr as $k): ?><td class="num"><?= number_format($h['w'][strtolower($k['kode'])], 3) ?></td><?php endforeach; ?><td class="num serif" style="font-weight:800"><?= number_format($h['v'], 3) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
  </div>

  <aside class="card"><h2 style="margin-bottom:.3rem">Bobot kriteria</h2><p class="muted small" style="margin:0 0 1.1rem">Atur kepentingan tiap kriteria. Total harus 100%.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="hari" value="<?= $hari ?>">
      <?php foreach ($kr as $k): $pct = (int)round($k['bobot'] * 100); ?>
        <div class="bobot-row"><div class="top"><span><?= e($k['kode']) ?> · <?= e($k['nama']) ?> <span class="badge b-mute"><?= $k['tipe'] ?></span></span><span class="bobot-nilai"><?= $pct ?>%</span></div>
          <input type="range" class="bobot-range" min="0" max="100" step="5" name="bobot[<?= $k['id'] ?>]" value="<?= $pct ?>"></div>
      <?php endforeach; ?>
      <div class="total-line"><span>Total bobot</span><span id="bobot-total">100%</span></div>
      <button class="btn btn-gold btn-block" id="bobot-simpan" style="padding:.7rem"><?= icon('check') ?> Simpan bobot</button>
    </form>
    <div class="small muted" style="margin-top:1rem;line-height:1.6"><b style="color:var(--ink)">Tingkat prioritas</b><br><span class="badge b-bad">V ≥ <?= SAW_BATAS_TINGGI ?></span> Restok segera<br><span class="badge b-warn" style="margin-top:.3rem">V ≥ <?= SAW_BATAS_SEDANG ?></span> Perlu dipantau<br><span class="badge b-ok" style="margin-top:.3rem">V &lt; <?= SAW_BATAS_SEDANG ?></span> Aman</div>
  </aside>
</div>
<?php include __DIR__ . '/includes/footer.php';
