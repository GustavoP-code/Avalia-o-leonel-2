<?php
require 'conexao.php';

if (isset($_POST['adicionar'])) {
    $modelo = trim($_POST['modelo']);
    $placa = trim($_POST['placa']);
    $categoria = $_POST['categoria'];
    $valor_diaria = $_POST['valor_diaria'];

    if (!empty($modelo) && !empty($placa) && !empty($valor_diaria)) {
        $stmt = $pdo->prepare("INSERT INTO veiculos (modelo, placa, categoria, valor_diaria) VALUES (?, ?, ?, ?)");
        $stmt->execute([$modelo, $placa, $categoria, $valor_diaria]);
        header("Location: veiculos.php");
        exit;
    }
}

if (isset($_GET['excluir'])) {
    $id = $_GET['excluir'];
    $stmt = $pdo->prepare("DELETE FROM veiculos WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: veiculos.php");
    exit;
}

$veiculos = $pdo->query("SELECT * FROM veiculos")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>CRUD Veículos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <a href="index.php" class="btn btn-secondary mb-3">⬅ Voltar ao Menu</a>
    <h2>🚗 Cadastro de Veículos</h2>
    
    <form method="POST" class="row g-3 mb-4">
        <div class="col-md-3">
            <input type="text" name="modelo" class="form-control" placeholder="Modelo (ex: Corolla)" required>
        </div>
        <div class="col-md-2">
            <input type="text" name="placa" class="form-control" placeholder="Placa (ex: ABC-1234)" required>
        </div>
        <div class="col-md-3">
            <select name="categoria" class="form-select">
                <option value="Sedan">Sedan</option>
                <option value="Hatch">Hatch</option>
                <option value="SUV">SUV</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="valor_diaria" class="form-control" placeholder="Diária R$" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="adicionar" class="btn btn-success w-100">Adicionar</button>
        </div>
    </form>

    <table class="table table-striped">
        <thead>
            <tr><th>ID</th><th>Modelo</th><th>Placa</th><th>Categoria</th><th>Diária</th><th>Status</th><th>Ações</th></tr>
        </thead>
        <tbody>
            <?php foreach ($veiculos as $v): ?>
            <tr>
                <td><?= $v['id'] ?></td>
                <td><?= htmlspecialchars($v['modelo']) ?></td>
                <td><?= htmlspecialchars($v['placa']) ?></td>
                <td><?= $v['categoria'] ?></td>
                <td>R$ <?= number_format($v['valor_diaria'], 2, ',', '.') ?></td>
                <td><span class="badge <?= $v['status'] == 'disponivel' ? 'bg-success' : 'bg-danger' ?>"><?= $v['status'] ?></span></td>
                <td>
                    <a href="veiculos.php?excluir=<?= $v['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deseja excluir?')">Excluir</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>