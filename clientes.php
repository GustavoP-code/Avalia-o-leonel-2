<?php
require 'conexao.php';

// Inclusão de Cliente (Correção: 'cidade' em vez de 'city')
if (isset($_POST['adicionar'])) {
    $nome = trim($_POST['nome']);
    $data_nascimento = $_POST['data_nascimento'];
    $cidade = trim($_POST['cidade']);

    if (!empty($nome) && !empty($data_nascimento) && !empty($cidade)) {
        // Correção aplicada na linha abaixo: alterado de 'city' para 'cidade'
        $stmt = $pdo->prepare("INSERT INTO clientes (nome, data_nascimento, cidade) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $data_nascimento, $cidade]);
        header("Location: clientes.php");
        exit;
    }
}

// Exclusão de Cliente
if (isset($_GET['excluir'])) {
    $id = $_GET['excluir'];
    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: clientes.php");
    exit;
}

$clientes = $pdo->query("SELECT * FROM clientes")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>CRUD Clientes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <a href="index.php" class="btn btn-secondary mb-3">⬅ Voltar ao Menu</a>
    <h2>👥 Cadastro de Clientes</h2>
    
    <form method="POST" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="nome" class="form-control" placeholder="Nome Completo" required>
        </div>
        <div class="col-md-3">
            <input type="date" name="data_nascimento" class="form-control" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="cidade" class="form-control" placeholder="Cidade" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="adicionar" class="btn btn-primary w-100">Adicionar</button>
        </div>
    </form>

    <table class="table table-striped">
        <thead>
            <tr><th>ID</th><th>Nome</th><th>Data de Nasc.</th><th>Cidade</th><th>Ações</th></tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= htmlspecialchars($c['nome']) ?></td>
                <td><?= date('d/m/Y', strtotime($c['data_nascimento'])) ?></td>
                <td><?= htmlspecialchars($c['cidade']) ?></td>
                <td>
                    <a href="editar_cliente.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                    <a href="clientes.php?excluir=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deseja excluir?')">Excluir</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>