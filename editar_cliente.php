<?php
require 'conexao.php';
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (isset($_POST['editar'])) {
    $nome = trim($_POST['nome']);
    $data_nascimento = $_POST['data_nascimento'];
    $cidade = trim($_POST['cidade']);

    if (!empty($nome) && !empty($data_nascimento) && !empty($cidade)) {
        $stmt = $pdo->prepare("UPDATE clientes SET nome = ?, data_nascimento = ?, cidade = ? WHERE id = ?");
        $stmt->execute([$nome, $data_nascimento, $cidade, $id]);
        header("Location: clientes.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
    <h2>Editar Cliente</h2>
    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
        </div>
        <div class="col-md-3">
            <input type="date" name="data_nascimento" class="form-control" value="<?= $cliente['data_nascimento'] ?>" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="cidade" class="form-control" value="<?= htmlspecialchars($cliente['cidade']) ?>" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="editar" class="btn btn-success w-100">Salvar</button>
        </div>
    </form>
</body>
</html>