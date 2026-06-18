<?php
require 'conexao.php';

// REGRA 1: Ao cadastrar, verifica se o veículo está disponível
if (isset($_POST['alugar'])) {
    $cliente_id = $_POST['cliente_id'];
    $veiculo_id = $_POST['veiculo_id'];
    $data_retirada = $_POST['data_retirada'];
    $data_prevista = $_POST['data_prevista'];

    // Consulta o status atual do veículo
    $stmt = $pdo->prepare("SELECT status FROM veiculos WHERE id = ?");
    $stmt->execute([$veiculo_id]);
    $statusVeiculo = $stmt->fetchColumn();

    if ($statusVeiculo != "disponivel") { // Exemplo exato exigido no documento Word
        echo "<script>alert('Este veículo não está disponível para aluguel.'); window.location.href='locacoes.php';</script>";
        exit;
    }

    // Se estiver disponível, realiza a locação e altera o status do veículo para 'alugado'
    $stmt = $pdo->prepare("INSERT INTO locacoes (cliente_id, veiculo_id, data_retirada, data_prevista, status) VALUES (?, ?, ?, ?, 'Ativo')");
    $stmt->execute([$cliente_id, $veiculo_id, $data_retirada, $data_prevista]);

    $stmt = $pdo->prepare("UPDATE veiculos SET status = 'alugado' WHERE id = ?");
    $stmt->execute([$veiculo_id]);

    header("Location: locacoes.php");
    exit;
}

// DEVOLUÇÃO DO VEÍCULO COM OS CÁLCULOS DE REGRAS DE NEGÓCIO
if (isset($_POST['devolver'])) {
    $locacao_id = $_POST['locacao_id'];
    $data_devolucao = $_POST['data_devolucao'];

    // Buscar dados da locação e do veículo associado
    $stmt = $pdo->prepare("SELECT l.*, v.valor_diaria, v.id as veiculo_id FROM locacoes l JOIN veiculos v ON l.veiculo_id = v.id WHERE l.id = ?");
    $stmt->execute([$locacao_id]);
    $loc = $stmt->fetch(PDO::FETCH_ASSOC);

    // 1. Calcular dias reais e previstos contratados
    $dias_contratados = (strtotime($loc['data_prevista']) - strtotime($loc['data_retirada'])) / 86400;
    $dias_reais = (strtotime($data_devolucao) - strtotime($loc['data_retirada'])) / 86400;
    if ($dias_reais <= 0) $dias_reais = 1;

    // REGRA 2: Cálculo bruto da locação
    $valor_bruto = $dias_reais * $loc['valor_diaria'];

    // REGRA 3: Desconto por Fidelidade (Contagem de locações passadas do cliente)
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM locacoes WHERE cliente_id = ? AND status = 'Entregue'");
    $stmt_count->execute([$loc['cliente_id']]);
    $historico_locacoes = $stmt_count->fetchColumn();

    $desc_fidelidade = 0;
    if ($historico_locacoes >= 10) $desc_fidelidade = 0.15;
    elseif ($historico_locacoes >= 5) $desc_fidelidade = 0.10;
    elseif ($historico_locacoes >= 3) $desc_fidelidade = 0.05;

    // REGRA 4: Desconto por quantidade de dias contratados
    $desc_dias = 0;
    if ($dias_contratados > 15) $desc_dias = 0.15;
    elseif ($dias_contratados > 7) $desc_dias = 0.10;

    // Aplicação dos descontos (acumulados ou o maior deles, usaremos a soma limitada ao teto)
    $total_desconto_percentual = $desc_fidelidade + $desc_dias;
    $valor_desconto = $valor_bruto * $total_desconto_percentual;

    // REGRA 5: Taxa extra por atraso
    $valor_multa = 0;
    $status_final = 'Entregue';

    if (strtotime($data_devolucao) > strtotime($loc['data_prevista'])) {
        $dias_atraso = (strtotime($data_devolucao) - strtotime($loc['data_prevista'])) / 86400;
        $valor_multa = $dias_atraso * $loc['valor_diaria'] * 1.2;
        $status_final = 'Atrasado';
    } elseif (strtotime($data_devolucao) < strtotime($loc['data_prevista'])) {
        $status_final = 'Entregue Antecipado';
    }

    // Atualiza tabela de locações e libera o veículo mudando para 'disponivel'
    $stmt = $pdo->prepare("UPDATE locacoes SET data_devolucao = ?, status = ? WHERE id = ?");
    $stmt->execute([$data_devolucao, $status_final, $locacao_id]);

    $stmt = $pdo->prepare("UPDATE veiculos SET status = 'disponivel' WHERE id = ?");
    $stmt->execute([$loc['veiculo_id']]);

    header("Location: locacoes.php");
    exit;
}

$clientes = $pdo->query("SELECT * FROM clientes")->fetchAll(PDO::FETCH_ASSOC);
$veiculos_disp = $pdo->query("SELECT * FROM veiculos WHERE status = 'disponivel'")->fetchAll(PDO::FETCH_ASSOC);
$locacoes = $pdo->query("SELECT l.*, c.nome as cliente, v.modelo as veiculo FROM locacoes l JOIN clientes c ON l.cliente_id = c.id JOIN veiculos v ON l.veiculo_id = v.id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciamento de Locações</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <a href="index.php" class="btn btn-secondary mb-3">⬅ Voltar ao Menu</a>
    <h2>🔑 Nova Locação</h2>
    
    <form method="POST" class="row g-3 mb-5">
        <div class="col-md-3">
            <select name="cliente_id" class="form-select" required>
                <option value="">Selecione o Cliente</option>
                <?php foreach($clientes as $c): ?> <option value="<?=$c['id']?>"><?=$c['nome']?></option> <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="veiculo_id" class="form-select" required>
                <option value="">Selecione o Veículo</option>
                <?php foreach($veiculos_disp as $v): ?> <option value="<?=$v['id']?>"><?=$v['modelo']?> (<?=$v['placa']?>)</option> <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" name="data_retirada" class="form-control" placeholder="Retirada" required>
        </div>
        <div class="col-md-3">
            <input type="date" name="data_prevista" class="form-control" placeholder="Previsão Devolução" required>
        </div>
        <div class="col-12 text-end">
            <button type="submit" name="alugar" class="btn btn-warning">Registrar Locação</button>
        </div>
    </form>

    <h2>📋 Histórico / Controle de Locações</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th>Cliente</th><th>Veículo</th><th>Retirada</th><th>Previsão</th><th>Devolução Real</th><th>Status</th><th>Ações</th></tr>
        </thead>
        <tbody>
            <?php foreach($locacoes as $l): ?>
            <tr>
                <td><?=htmlspecialchars($l['cliente'])?></td>
                <td><?=htmlspecialchars($l['veiculo'])?></td>
                <td><?=date('d/m/Y', strtotime($l['data_retirada']))?></td>
                <td><?=date('d/m/Y', strtotime($l['data_prevista']))?></td>
                <td><?=$l['data_devolucao'] ? date('d/m/Y', strtotime($l['data_devolucao'])) : '--'?></td>
                <td><span class="badge bg-secondary"><?=$l['status']?></span></td>
                <td>
                    <?php if(!$l['data_devolucao']): ?>
                    <form method="POST" action="locacoes.php" class="d-inline">
                        <input type="hidden" name="locacao_id" value="<?=$l['id']?>">
                        <input type="date" name="data_devolucao" required class="form-control form-control-sm d-inline w-auto">
                        <button type="submit" name="devolver" class="btn btn-sm btn-dark">Devolver</button>
                    </form>
                    <?php else: ?>
                    <span class="text-success">Concluído</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>