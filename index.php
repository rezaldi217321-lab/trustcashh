<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';
verify_csrf();
$page = $_GET['page'] ?? (isset($_SESSION['user_id']) ? 'dashboard' : 'login');
$err = '';
if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
 $name=trim($_POST['name']??''); $email=filter_var($_POST['email']??'', FILTER_VALIDATE_EMAIL); $pw=$_POST['password']??'';
 if (!$name || !$email || strlen($pw)<8) $err='Isi nama, email valid, dan password minimal 8 karakter.';
 else {
  try {
   $s=$pdo->prepare('INSERT INTO users(name,email,password) VALUES(?,?,?)');
   $s->execute([$name,$email,password_hash($pw,PASSWORD_DEFAULT)]);
   $id=(int)$pdo->lastInsertId();
   foreach ([['Gaji','income'],['Bonus','income'],['Makanan','expense'],['Transportasi','expense'],['Belanja','expense'],['Tagihan','expense'],['Lainnya','expense']] as $c) {
    $pdo->prepare('INSERT INTO categories(user_id,name,type) VALUES(?,?,?)')->execute([$id,$c[0],$c[1]]);
   }
   $_SESSION['user_id']=$id; $_SESSION['user_name']=$name; redirect('index.php');
  } catch (PDOException $ex) { $err='Email sudah digunakan atau data tidak valid.'; }
 }
}
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
 $s=$pdo->prepare('SELECT * FROM users WHERE email=?'); $s->execute([trim($_POST['email']??'')]); $u=$s->fetch();
 if ($u && password_verify($_POST['password']??'', $u['password'])) { session_regenerate_id(true); $_SESSION['user_id']=(int)$u['id']; $_SESSION['user_name']=$u['name']; redirect('index.php'); }
 $err='Email atau password salah.';
}
if ($page === 'logout') { $_SESSION=[]; session_destroy(); redirect('index.php?page=login'); }
if ($page === 'login' || $page === 'register') {
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TRUSTCASH</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth-bg"><main class="auth-card"><div class="brand-mark">S</div><p class="eyebrow">PERSONAL FINANCE SYSTEM</p><h1><?= $page==='login'?'Selamat datang kembali':'Buat akun TRUSTCASH' ?></h1><p class="muted">Kelola uangmu dengan lebih terarah.</p><?php if($err): ?><div class="alert"><?=e($err)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><?php if($page==='register'): ?><label>Nama lengkap</label><input name="name" required maxlength="100"><?php endif; ?><label>Email</label><input type="email" name="email" required><label>Password</label><input type="password" name="password" minlength="8" required><button class="btn primary full"><?= $page==='login'?'Masuk':'Daftar akun' ?></button></form><p class="switch"><?= $page==='login'?'Belum punya akun? <a href="index.php?page=register">Daftar</a>':'Sudah punya akun? <a href="index.php?page=login">Masuk</a>' ?></p></main></body></html><?php exit; }
require_login();
$uid=(int)$_SESSION['user_id'];
// Actions
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
 $action=$_POST['action'];
 if ($action==='save_tx') {
  $id=(int)($_POST['id']??0); $type=$_POST['type']==='income'?'income':'expense';
  $amount=(float)($_POST['amount']??0); $date=$_POST['transaction_date']??date('Y-m-d');
  $cat=(int)($_POST['category_id']??0); $note=trim($_POST['note']??'');
  if ($amount>0 && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) {
   if ($id) $q=$pdo->prepare('UPDATE transactions SET type=?,amount=?,category_id=?,note=?,transaction_date=? WHERE id=? AND user_id=?');
   else $q=$pdo->prepare('INSERT INTO transactions(type,amount,category_id,note,transaction_date,user_id) VALUES(?,?,?,?,?,?)');
   $q->execute($id?[$type,$amount,$cat?:null,$note,$date,$id,$uid]:[$type,$amount,$cat?:null,$note,$date,$uid]);
  }
  redirect('index.php?page=transactions');
 }
 if ($action==='delete_tx') { $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]); redirect('index.php?page=transactions'); }
 if ($action==='save_budget') { $month=date('Y-m'); $amount=max(0,(float)$_POST['amount']); $pdo->prepare('INSERT INTO budgets(user_id,month,amount) VALUES(?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)')->execute([$uid,$month,$amount]); redirect('index.php?page=budget'); }
}
if ($page==='export') {
 header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="sippp-transaksi.csv"');
 $out=fopen('php://output','w'); fputcsv($out,['Tanggal','Jenis','Kategori','Catatan','Jumlah']);
 $q=$pdo->prepare("SELECT t.transaction_date,t.type,c.name,t.note,t.amount FROM transactions t LEFT JOIN categories c ON c.id=t.category_id WHERE t.user_id=? ORDER BY t.transaction_date DESC"); $q->execute([$uid]);
 while($r=$q->fetch()) fputcsv($out,[$r['transaction_date'],$r['type']==='income'?'Pemasukan':'Pengeluaran',$r['name'],$r['note'],$r['amount']]); fclose($out); exit;
}
$month=date('Y-m');
$q=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) inc, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) exp FROM transactions WHERE user_id=?"); $q->execute([$uid]); $tot=$q->fetch();
$balance=(float)$tot['inc']-(float)$tot['exp'];
$q=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) inc, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) exp FROM transactions WHERE user_id=? AND DATE_FORMAT(transaction_date,'%Y-%m')=?"); $q->execute([$uid,$month]); $mt=$q->fetch();
$q=$pdo->prepare("SELECT t.*,c.name category FROM transactions t LEFT JOIN categories c ON c.id=t.category_id WHERE t.user_id=? ORDER BY t.transaction_date DESC,t.id DESC LIMIT 8"); $q->execute([$uid]); $recent=$q->fetchAll();
$q=$pdo->prepare("SELECT id,name,type FROM categories WHERE user_id=? ORDER BY type,name"); $q->execute([$uid]); $cats=$q->fetchAll();
$q=$pdo->prepare('SELECT amount FROM budgets WHERE user_id=? AND month=?'); $q->execute([$uid,$month]); $budget=(float)($q->fetchColumn()?:0);
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TRUSTCASH</title><link rel="stylesheet" href="assets/style.css"><script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head><body><div class="app-shell"><aside class="sidebar"><a class="logo" href="index.php"><span class="brand-mark small">S</span><span>TRUSTCASH <small>PERSONAL FINANCE</small></span></a><p class="nav-label">MENU UTAMA</p><a class="<?= $page==='dashboard'?'active':''?>" href="index.php">▦　Dashboard</a><a class="<?= $page==='transactions'?'active':''?>" href="index.php?page=transactions">↕　Transaksi</a><a class="<?= $page==='budget'?'active':''?>" href="index.php?page=budget">◎　Anggaran</a><a href="index.php?page=export">⇩　Ekspor CSV</a><div class="sidebar-bottom"><div class="user-mini"><div class="avatar"><?=e(strtoupper(substr($_SESSION['user_name'],0,1)))?></div><div><?=e($_SESSION['user_name'])?><small>Personal account</small></div></div><a href="index.php?page=logout">↪　Keluar</a></div></aside><main class="main"><header class="topbar"><div><p class="eyebrow">OVERVIEW / <?=strtoupper(e($page))?></p><h2><?= $page==='dashboard'?'Dashboard keuangan':($page==='transactions'?'Riwayat transaksi':($page==='budget'?'Anggaran bulanan':'TRUSTCASH')) ?></h2></div><div class="top-right"><span class="live-dot"></span> Data pribadi <div class="avatar"><?=e(strtoupper(substr($_SESSION['user_name'],0,1)))?></div></div></header>
<?php if($page==='dashboard'): ?>
<section class="welcome"><div><p class="eyebrow light">FINANCIAL OVERVIEW</p><h1>Atur uangmu,<br>bangun masa depan.</h1><p>Ringkasan keuangan pribadi dalam satu tempat.</p><a class="btn white" href="index.php?page=transactions">+ Catat transaksi</a></div><div class="orb"><div class="orb-inner">S</div></div></section>
<section class="stats"><article class="stat-card featured"><span>Total saldo</span><small>Akumulasi pemasukan - pengeluaran</small><h2><?=money($balance)?></h2><div class="stat-icon">◈</div></article><article class="stat-card"><span>Total pemasukan</span><small>Bulan ini</small><h2 class="positive"><?=money($mt['inc'])?></h2><div class="stat-icon">↗</div></article><article class="stat-card"><span>Total pengeluaran</span><small>Bulan ini</small><h2 class="negative"><?=money($mt['exp'])?></h2><div class="stat-icon">↘</div></article></section>
<section class="content-grid"><article class="panel chart-panel"><div class="panel-head"><div><h3>Arus kas bulanan</h3><p class="muted">Pemasukan dan pengeluaran bulan ini</p></div><span class="tag"><?=date('F Y')?></span></div><canvas id="cashChart" height="120"></canvas></article><article class="panel budget-panel"><div class="panel-head"><div><h3>Anggaran bulan ini</h3><p class="muted">Pantau batas pengeluaran</p></div><span class="tag">BUDGET</span></div><?php $pct=$budget>0?min(100,($mt['exp']/$budget)*100):0; ?><div class="budget-number"><?= $budget?money($budget):'Belum diatur' ?></div><div class="progress"><span style="width:<?=$pct?>%"></span></div><p class="muted"><?= $budget?number_format($pct,1).'% anggaran terpakai':'Atur anggaran agar pengeluaran lebih terkontrol.' ?></p><a class="text-link" href="index.php?page=budget">Kelola anggaran →</a></article></section>
<?php elseif($page==='transactions'): ?>
<section class="panel"><div class="panel-head"><div><h3>Tambah transaksi</h3><p class="muted">Catat setiap uang masuk dan keluar.</p></div></div><form class="tx-form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_tx"><div><label>Jenis transaksi</label><select name="type"><option value="expense">Pengeluaran</option><option value="income">Pemasukan</option></select></div><div><label>Jumlah (Rp)</label><input type="number" name="amount" min="1" step="1" placeholder="50000" required></div><div><label>Kategori</label><select name="category_id"><?php foreach($cats as $c):?><option value="<?=$c['id']?>"><?=e($c['name'])?> · <?=$c['type']==='income'?'Masuk':'Keluar'?></option><?php endforeach;?></select></div><div><label>Tanggal</label><input type="date" name="transaction_date" value="<?=date('Y-m-d')?>" required></div><div class="wide"><label>Catatan</label><input name="note" maxlength="255" placeholder="Contoh: makan siang"></div><button class="btn primary">Simpan transaksi</button></form></section>
<section class="panel"><div class="panel-head"><h3>Semua transaksi</h3><a class="text-link" href="index.php?page=export">Ekspor CSV ↓</a></div><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Keterangan</th><th>Kategori</th><th>Jenis</th><th>Jumlah</th><th>Aksi</th></tr></thead><tbody><?php $q=$pdo->prepare("SELECT t.*,c.name category FROM transactions t LEFT JOIN categories c ON c.id=t.category_id WHERE t.user_id=? ORDER BY transaction_date DESC,t.id DESC");$q->execute([$uid]);$rows=$q->fetchAll(); foreach($rows as $r):?><tr><td><?=e($r['transaction_date'])?></td><td><?=e($r['note']?:'-')?></td><td><?=e($r['category']?:'-')?></td><td><span class="pill <?=$r['type']?>"><?=$r['type']==='income'?'Pemasukan':'Pengeluaran'?></span></td><td class="<?=$r['type']==='income'?'positive':'negative'?>"><?=$r['type']==='income'?'+':'-'?><?=money($r['amount'])?></td><td><form method="post" onsubmit="return confirm('Hapus transaksi ini?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_tx"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="delete-btn">Hapus</button></form></td></tr><?php endforeach; if(!$rows):?><tr><td colspan="6" class="empty">Belum ada transaksi.</td></tr><?php endif;?></tbody></table></div></section>
<?php elseif($page==='budget'): ?><section class="panel budget-edit"><h3>Atur anggaran pengeluaran</h3><p class="muted">Tetapkan batas pengeluaran untuk bulan <?=e($month)?>.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_budget"><label>Anggaran bulanan (Rupiah)</label><input type="number" name="amount" min="0" value="<?=e($budget)?>" placeholder="Contoh: 2000000" required><button class="btn primary">Simpan anggaran</button></form><div class="budget-summary"><span>Pengeluaran bulan ini</span><strong><?=money($mt['exp'])?></strong><span>Sisa anggaran</span><strong><?=money(max(0,$budget-$mt['exp']))?></strong></div></section><?php endif; ?>
<footer>© <?=date('Y')?> TRUSTCASH <span>Kelola keuangan dengan bijak.</span></footer></main></div>
<script>
const ctx=document.getElementById('cashChart');
if(ctx) new Chart(ctx,{type:'bar',data:{labels:['Pemasukan','Pengeluaran'],datasets:[{label:'Rupiah',data:[<?=json_encode((float)$mt['inc'])?>,<?=json_encode((float)$mt['exp'])?>],backgroundColor:['#3988ff','#8b9bb4'],borderRadius:12,borderSkipped:false}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'rgba(120,140,170,.12)'},ticks:{callback:v=>'Rp '+Number(v).toLocaleString('id-ID')}},x:{grid:{display:false}}}}});
</script></body></html>
