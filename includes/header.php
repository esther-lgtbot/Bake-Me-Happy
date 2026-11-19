<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bake Me Happy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/EL3/style.css" rel="stylesheet">
<style>
  /* --- FOOD PALETTE - isang palette lang, hindi na mag-aaway --- */
  :root{
    --cream:#FFF6EE;      /* vanilla background */
    --card:#FFFBF7;       /* cake card */
    --line:#F8D8B8;       /* crust border */
    --choco:#3B2314;      /* dark choco - header */
    --choco2:#5A3320;     /* lighter choco on hover */
    --orange:#C2410C;     /* burnt orange - main button */
    --orange-dark:#9A3412;
    --caramel:#A05A2C;    /* price */
  }
  body{ background-color:var(--cream) !important; color:var(--choco); }

  /* cards */
  .card, .product-card{
    background:var(--card) !important;
    border:2px solid var(--line) !important;
    border-radius:16px !important;
    box-shadow:0 4px 10px rgba(160,90,44,.08) !important;
  }
  .price, .card-text strong{ color:var(--caramel) !important; }

  /* --- HEADER --- */
  .navbar-bake{
    background-color:var(--choco) !important;
    padding:14px 0;
    position:sticky; top:0; z-index:100;
    box-shadow:0 2px 12px rgba(0,0,0,.15);
  }
  .navbar-bake .navbar-brand{
    color:#FFF6EE !important;
    font-size:1.35rem; letter-spacing:-.02em;
    display:flex; align-items:center; gap:10px;
  }
  .navbar-bake .navbar-brand > span{ color:#FFF6EE; }
  .navbar-bake .navbar-brand > span span{ color:#FFC79A; }
  .navbar-logo{ width:46px; height:46px; object-fit:contain; border-radius:50%; }

  /* words lang, walang icons, walang iba-ibang button colors */
  .nav-word{
    color:#FFF6EE !important;
    text-decoration:none;
    font-weight:600;
    font-size:.92rem;
    padding:8px 10px;
    border-radius:999px;
  }
  .nav-word:hover{ color:#FFC79A !important; background:rgba(255,255,255,.08); }
  .nav-word.active{ background:rgba(255,255,255,.12); color:#FFC79A !important; }

  .nav-cta{
    background:var(--orange) !important;
    color:#fff !important;
    font-weight:700;
    padding:8px 18px !important;
    border-radius:999px;
  }
  .nav-cta:hover{ background:var(--orange-dark) !important; color:#fff !important; }
  .nav-user{ color:#FFC79A; font-weight:700; font-size:.92rem; }

  /* lahat ng dating btn-warning / btn-primary sa loob ng site, gawing orange na lang */
  .btn-primary, .btn-warning{
    background-color:var(--orange) !important;
    border-color:var(--orange) !important;
    color:#fff !important;
  }
  .btn-primary:hover, .btn-warning:hover{ background-color:var(--orange-dark) !important; border-color:var(--orange-dark) !important; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-bake">
  <div class="container">
    <?php
      $role = $_SESSION['role'] ?? '';
      $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
      $homeUrl = $role === 'admin'
        ? '/EL3/admin/orders.php'
        : ($role === 'seller' ? '/EL3/seller/index.php' : '/EL3/products/');
    ?>
    <a class="navbar-brand fw-bold" href="<?= h($homeUrl) ?>">
      <img src="/EL3/assets/bake-me-happy-logo.png" alt="" class="navbar-logo">
      <span>Bake Me <span>Happy</span></span>
    </a>

    <div class="d-flex gap-1 align-items-center flex-wrap">
      <?php if(isset($_SESSION['user_id'])): ?>
        <?php if ($role === 'admin'): ?>
          <?php if ($currentPath === '/EL3/admin/orders.php'): ?>
            <a href="/EL3/admin/products.php" class="nav-word">Seller Products</a>
          <?php elseif ($currentPath === '/EL3/admin/products.php'): ?>
            <a href="/EL3/admin/orders.php" class="nav-word">Manage Orders</a>
          <?php else: ?>
            <a href="/EL3/admin/orders.php" class="nav-word">Manage Orders</a>
            <a href="/EL3/admin/products.php" class="nav-word">Seller Products</a>
          <?php endif; ?>
        <?php elseif ($role === 'seller'): ?>
          <?php if ($currentPath === '/EL3/seller/index.php'): ?>
            <a href="/EL3/seller/orders.php" class="nav-word">Seller Orders</a>
          <?php elseif ($currentPath === '/EL3/seller/orders.php'): ?>
            <a href="/EL3/seller/index.php" class="nav-word">Manage Products</a>
          <?php else: ?>
            <a href="/EL3/seller/index.php" class="nav-word">Manage Products</a>
            <a href="/EL3/seller/orders.php" class="nav-word">Seller Orders</a>
          <?php endif; ?>
        <?php else: ?>
          <a href="/EL3/cart/index.php" class="nav-word">Cart</a>
          <a href="/EL3/orders.php" class="nav-word">Orders</a>
        <?php endif; ?>

        <span class="nav-user ms-2">Hi, <?= h($_SESSION['name'] ?? 'Customer') ?></span>
        <a href="/EL3/auth/logout.php" class="nav-word">Logout</a>
      <?php else: ?>
        <a href="/EL3/auth/login.php" class="nav-word">Login</a>
        <a href="/EL3/auth/register.php" class="nav-word nav-cta">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container mt-4">