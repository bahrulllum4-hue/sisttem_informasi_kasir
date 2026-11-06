<?php
/* Endpoint JSON: simpan transaksi dari halaman kasir. Harga & stok selalu dibaca dari database (bukan dari browser). */
require __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

function balas($ok, $data = [], $kode = 200) { http_response_code($kode); echo json_encode(['ok' => $ok] + $data); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') balas(false, ['pesan' => 'Metode tidak diizinkan.'], 405);
if (!user()) balas(false, ['pesan' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) balas(false, ['pesan' => 'Token keamanan tidak valid. Muat ulang halaman.'], 400);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['items']) || !is_array($in['items'])) balas(false, ['pesan' => 'Keranjang kosong.'], 422);

$qty = [];                                   // gabungkan item ganda
foreach ($in['items'] as $it) {
    $id = (int)($it['id'] ?? 0); $q = (int)($it['qty'] ?? 0);
    if ($id <= 0 || $q <= 0 || $q > 10000) balas(false, ['pesan' => 'Data keranjang tidak valid.'], 422);
    $qty[$id] = ($qty[$id] ?? 0) + $q;
}
$bayar = (int)($in['bayar'] ?? 0);

try {
    $pdo->beginTransaction();
    $total = 0; $baris = [];
    foreach ($qty as $id => $q) {
        $s = $pdo->prepare('SELECT * FROM produk WHERE id=? AND aktif=1' . lock_sql());
        $s->execute([$id]); $p = $s->fetch();
        if (!$p) throw new RuntimeException('Produk tidak ditemukan atau sudah nonaktif.');
        if ($p['stok'] < $q) throw new RuntimeException('Stok ' . $p['nama'] . ' tidak cukup (sisa ' . $p['stok'] . ').');
        $baris[] = [$p, $q]; $total += $p['harga_jual'] * $q;
    }
    if ($bayar < $total) throw new RuntimeException('Uang diterima kurang dari total ' . rp($total) . '.');

    $pdo->prepare('INSERT INTO transaksi(tanggal,total,bayar,kembali,user_id) VALUES(?,?,?,?,?)')
        ->execute([date('Y-m-d H:i:s'), $total, $bayar, $bayar - $total, user()['id']]);
    $tid = (int)$pdo->lastInsertId();
    foreach ($baris as [$p, $q]) {
        $pdo->prepare('INSERT INTO detail_transaksi(transaksi_id,produk_id,qty,harga,harga_beli) VALUES(?,?,?,?,?)')
            ->execute([$tid, $p['id'], $q, $p['harga_jual'], $p['harga_beli']]);
        $u = $pdo->prepare('UPDATE produk SET stok=stok-? WHERE id=? AND stok>=?');
        $u->execute([$q, $p['id'], $q]);
        if ($u->rowCount() !== 1) throw new RuntimeException('Stok ' . $p['nama'] . ' berubah. Coba lagi.');
    }
    $pdo->commit();
    balas(true, ['id' => $tid, 'total' => $total, 'kembali' => $bayar - $total]);
} catch (RuntimeException $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    balas(false, ['pesan' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    balas(false, ['pesan' => 'Terjadi kesalahan server.'], 500);
}
