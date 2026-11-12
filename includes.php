<?php
session_start();
// Front-end prototype store. Demo data lives in the session; no database connection is made.
$_SESSION['demo_users'] ??= [
 ['id'=>1,'name'=>'Super Admin','email'=>'superadmin@park.com','password'=>password_hash('super123',PASSWORD_DEFAULT),'role'=>'superadmin','blocked'=>false],
 ['id'=>2,'name'=>'Parking Admin','email'=>'admin@park.com','password'=>password_hash('admin123',PASSWORD_DEFAULT),'role'=>'admin','blocked'=>false],
 ['id'=>3,'name'=>'Parking Staff','email'=>'staff@park.com','password'=>password_hash('staff123',PASSWORD_DEFAULT),'role'=>'staff','blocked'=>false],
 ['id'=>4,'name'=>'Demo Member','email'=>'user@park.com','password'=>password_hash('user123',PASSWORD_DEFAULT),'role'=>'user','blocked'=>false]
];
$_SESSION['demo_lots'] ??= [
 ['id'=>1,'name'=>'Kota Kinabalu Waterfront','city'=>'Kota Kinabalu','address'=>'Jalan Tun Fuad Stephens','rate'=>3.00,'capacity'=>36,'lat'=>5.9804,'lng'=>116.0735],
 ['id'=>2,'name'=>'Sandakan Central Parking','city'=>'Sandakan','address'=>'Bandar Sandakan','rate'=>2.00,'capacity'=>24,'lat'=>5.8402,'lng'=>118.1179],
 ['id'=>3,'name'=>'Tawau Town Centre','city'=>'Tawau','address'=>'Jalan Dunlop','rate'=>2.50,'capacity'=>30,'lat'=>4.2498,'lng'=>117.8871]
];
$_SESSION['demo_bookings'] ??= [];
$_SESSION['demo_wallet_balance'] ??= 30.00;
$_SESSION['demo_wallet_transactions'] ??= [];
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function csrf(){return $_SESSION['csrf']??=bin2hex(random_bytes(16));}
function check_csrf(){if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(400);exit('Invalid request');}}
function me(){return $_SESSION['user']??null;}
function require_login(){if(!me()){header('Location: login.php');exit;}}
function require_admin(){require_login();if(!in_array(me()['role'],['admin','superadmin'],true)){http_response_code(403);exit('Forbidden');}}
function flash($m=null){if($m!==null){$_SESSION['flash']=$m;return;}$f=$_SESSION['flash']??'';unset($_SESSION['flash']);return $f;}
function head($title){$u=me();$f=flash();$notice=$u?count(array_filter($_SESSION['demo_bookings']??[],fn($b)=>$b['user_id']===$u['id']&&$b['status']==='active')):0;echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' - ParkEasy Sabah</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"><script src="assets/loading.js"></script><link rel="stylesheet" href="assets/style.css"></head><body><header><a class="logo" href="index.php">🅿 ParkEasy Sabah</a><nav>';if($u){echo '<a href="dashboard.php">Parking</a><a href="history.php">History</a><a href="wallet.php">Wallet</a><a href="profile.php">Profile</a><a href="support.php">Support</a>';if(in_array($u['role'],['admin','superadmin','staff'],true))echo '<a href="admin.php">Operations</a>';echo '<a class="notification-link" href="history.php" aria-label="Parking notifications">🔔'.($notice?'<b>'.$notice.'</b>':'').'</a><span>'.e($u['name']).' · '.e($u['role']).'</span><a href="logout.php">Logout</a>';}else echo '<a href="login.php">Login</a><a href="register.php">Register</a>';echo '<button id="themeBtn" class="ghost" type="button">🌓</button></nav></header><main>';if($f)echo '<div class="flash">'.e($f).'</div>';}
function foot(){echo '</main><footer><section><b>ParkEasy Sabah</b><p>Simple parking reservations across Sabah.</p></section><section><b>Contact</b><p>support@parkeasy-sabah.demo<br>+60 88 000 000</p></section><section><b>Help</b><p><a href="support.php">Customer support</a><br><a href="profile.php">My profile</a></p></section></footer><script src="assets/app.js"></script></body></html>';}
