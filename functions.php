<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf() {
 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $t = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'] ?? '', $t)) { http_response_code(403); exit('Permintaan tidak valid. Muat ulang halaman.'); }
 }
}
function require_login() { if (empty($_SESSION['user_id'])) { header('Location: index.php?page=login'); exit; } }
function money($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function redirect($url) { header("Location: $url"); exit; }
