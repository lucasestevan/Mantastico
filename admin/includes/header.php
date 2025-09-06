<?php
// Verifica se nenhuma sessão foi iniciada ainda antes de iniciar uma nova.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
  header("Location: ../index.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Admin - Mantástico</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="d-flex">
  <nav class="bg-dark text-white p-3 vh-100" style="width: 250px;">
    <h4>Mantástico ⚽</h4>
    <ul class="nav flex-column mt-4">
      <li class="nav-item"><a href="dashboard.php" class="nav-link text-white">🏠 Dashboard</a></li>
      <li class="nav-item"><a href="produtos.php" class="nav-link text-white">🛍 Produtos</a></li>
      <li class="nav-item"><a href="pedidos.php" class="nav-link text-white">📦 Pedidos</a></li>
      <li class="nav-item"><a href="automacao.php" class="nav-link text-white">⚙️ Cadastrar em Massa</a></li>
      <li class="nav-item"><a href="logout.php" class="nav-link text-white">🚪 Sair</a></li>
    </ul>
  </nav>
  <main class="p-4" style="flex-grow: 1;">
