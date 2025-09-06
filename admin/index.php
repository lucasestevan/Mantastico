<?php
session_start();

// Se já estiver logado, redireciona para painel
if (isset($_SESSION['admin'])) {
  header("Location: dashboard.php");
  exit;
}

$conn = new mysqli("localhost", "root", "", "mantastico");
if ($conn->connect_error) die("Erro na conexão");

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario = $_POST['usuario'];
  $senha = $_POST['senha'];

  $stmt = $conn->prepare("SELECT * FROM admins WHERE usuario = ? AND senha = ?");
  $stmt->bind_param("ss", $usuario, $senha);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows === 1) {
    $_SESSION['admin'] = $usuario;
    header("Location: dashboard.php");
    exit;
  } else {
    $erro = "Usuário ou senha incorretos.";
  }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Login Admin - Mantástico</title>
</head>
<body>
  <h2>Login do Administrador</h2>
  <?php if ($erro): ?>
    <p style="color:red;"><?= $erro ?></p>
  <?php endif; ?>
  <form method="post">
    <input type="text" name="usuario" placeholder="Usuário" required><br><br>
    <input type="password" name="senha" placeholder="Senha" required><br><br>
    <button type="submit">Entrar</button>
  </form>
</body>
</html>
