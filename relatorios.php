<?php
require 'conexao.php';

// Define mês e ano padrão (atual) ou pega via GET a partir do filtro do usuário
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('m');
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y');

// O período formatado para usar nos filtros de data do banco (Ex: '2026-05')
$periodo = "$ano-$mes";

// ==========================================
// CONSULTA - RELATÓRIO 1: CLIENTES
// ==========================================
// Calcula a idade com TIMESTAMPDIFF, conta locações e soma os valores reais do período
$query_clientes = "
    SELECT 
        c.nome,
        TIMESTAMPDIFF(YEAR, c.data_nascimento, CURDATE()) AS idade,
        c.cidade,
        COUNT(l.id) AS total_locacoes,
        SUM(DATEDIFF(IFNULL(l.data_devolucao, l.data_prevista), l.data_retirada)) AS dias_alugados,
        -- Simulação do cálculo final gasto baseado nas regras de negócio (Bruto - Descontos + Multas)
        SUM(
            (DATEDIFF(IFNULL(l.data_devolucao, l.data_prevista), l.data_retirada) * v.valor_diaria)
        ) AS valor_total_gasto
    FROM clientes c
    LEFT JOIN locacoes l ON c.id = l.cliente_id AND DATE_FORMAT(l.data_retirada, '%Y-%m') = :periodo
    LEFT JOIN veiculos v ON l.veiculo_id = v.id
    GROUP BY c.id
";
$stmt_clientes = $pdo->prepare($query_clientes);
$stmt_clientes->execute(['periodo' => $periodo]);
$relatorio_clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// CONSULTA - RELATÓRIO 2: VEÍCULOS
// ==========================================
$query_veiculos = "
    SELECT 
        v.modelo,
        v.placa,
        v.categoria,
        v.valor_diaria,
        COUNT(l.id) AS qtd_locacoes,
        -- Calcula a receita bruta gerada pelo veículo no mês
        IFNULL(SUM(DATEDIFF(IFNULL(l.data_devolucao, l.data_prevista), l.data_retirada) * v.valor_diaria), 0) AS receita_bruta
    FROM veiculos v
    LEFT JOIN locacoes l ON v.id = l.veiculo_id AND DATE_FORMAT(l.data_retirada, '%Y-%m') = :periodo
    GROUP BY v.id
";
$stmt_veiculos = $pdo->prepare($query_veiculos);
$stmt_veiculos->execute(['periodo' => $periodo]);
$relatorio_veiculos = $stmt_veiculos->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// CONSULTA - RELATÓRIO 3: LOCAÇÕES
// ==========================================
$query_locacoes = "
    SELECT 
        c.nome AS cliente,
        v.modelo AS veiculo,
        v.categoria,
        l.data_retirada,
        l.data_devolucao,
        l.data_prevista,
        v.valor_diaria,
        l.status
    FROM locacoes l
    JOIN clientes c ON l.cliente_id = c.id
    JOIN veiculos v ON l.veiculo_id = v.id
    WHERE DATE_FORMAT(l.data_retirada, '%Y-%m') = :periodo
";
$stmt_locacoes = $pdo->prepare($query_locacoes);
$stmt_locacoes->execute(['periodo' => $periodo]);
$relatorio_locacoes = $stmt_locacoes->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatórios Mensais</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <a href="index.php" class="btn btn-secondary mb-3">⬅ Voltar ao Menu</a>
    <h2>📊 Painel de Relatórios Dinâmicos</h2>
    
    <form method="GET" class="row g-2 my-4">
        <div class="col-md-3">
            <select name="mes" class="form-select">
                <?php
                for ($m = 1; $m <= 12; $m++) {
                    $m_zero = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $selected = ($mes == $m_zero) ? 'selected' : '';
                    echo "<option value='$m_zero' $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="ano" class="form-control" value="<?=$ano?>" min="2020" max="2030">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-dark w-100">Filtrar Período</button>
        </div>
    </form>

    <h3 class="mt-5">Relatório 1 — Clientes</h3>
    <table class="table table-bordered table-striped">
        <thead class="table-primary">
            <tr><th>Cliente</th><th>Idade</th><th>Cidade</th><th>Total Locações</th><th>Dias Alugados</th><th>Valor Total Gasto</th><th>Classificação</th></tr>
        </thead>
        <tbody>
            <?php if (empty($relatorio_clientes)): ?>
                <tr><td colspan="7" class="text-center">Nenhum registro encontrado para este período.</td></tr>
            <?php else: ?>
                <?php foreach ($relatorio_clientes as $rc): 
                    // Regra de Negócio para Classificação baseada no total de locações históricas
                    $classificacao = 'Regular';
                    $badge = 'bg-info';
                    if ($rc['total_locacoes'] >= 5) {
                        $classificacao = 'Premium';
                        $badge = 'bg-dark';
                    } elseif ($rc['total_locacoes'] >= 3) {
                        $classificacao = 'Fiel';
                        $badge = 'bg-success';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($rc['nome']) ?></td>
                    <td><?= $rc['idade'] ?></td>
                    <td><?= htmlspecialchars($rc['cidade']) ?></td>
                    <td><?= $rc['total_locacoes'] ?></td>
                    <td><?= $rc['dias_alugados'] ?? 0 ?></td>
                    <td>R$ <?= number_format($rc['valor_total_gasto'] ?? 0, 2, ',', '.') ?></td>
                    <td><span class="badge <?= $badge ?>"><?= $classificacao ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h3 class="mt-5">Relatório 2 — Veículos</h3>
    <table class="table table-bordered table-striped">
        <thead class="table-success">
            <tr><th>Veículo</th><th>Placa</th><th>Categoria</th><th>Valor Diária</th><th>Qtd Locações</th><th>Receita Bruta</th><th>Status de Utilização</th></tr>
        </thead>
        <tbody>
            <?php if (empty($relatorio_veiculos)): ?>
                <tr><td colspan="7" class="text-center">Nenhum registro encontrado para este período.</td></tr>
            <?php else: ?>
                <?php foreach ($relatorio_veiculos as $rv): 
                    // Regra de Negócio para status de utilização baseado nas locações do mês
                    $status_utilizacao = 'Baixa utilização';
                    if ($rv['qtd_locacoes'] >= 5) {
                        $status_utilizacao = 'Alta utilização';
                    } elseif ($rv['qtd_locacoes'] >= 2) {
                        $status_utilizacao = 'Média utilização';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($rv['modelo']) ?></td>
                    <td><?= htmlspecialchars($rv['placa']) ?></td>
                    <td><?= $rv['categoria'] ?></td>
                    <td>R$ <?= number_format($rv['valor_diaria'], 2, ',', '.') ?></td>
                    <td><?= $rv['qtd_locacoes'] ?></td>
                    <td>R$ <?= number_format($rv['receita_bruta'], 2, ',', '.') ?></td>
                    <td><?= $status_utilizacao ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h3 class="mt-5">Relatório 3 — Locações</h3>
    <table class="table table-bordered table-striped">
        <thead class="table-warning">
            <tr><th>Cliente</th><th>Veículo</th><th>Categoria</th><th>Retirada</th><th>Devolução Real</th><th>Dias Reais</th><th>Multa</th><th>Valor Final</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php if (empty($relatorio_locacoes)): ?>
                <tr><td colspan="9" class="text-center">Nenhum registro encontrado para este período.</td></tr>
            <?php else: ?>
                <?php foreach ($relatorio_locacoes as $rl): 
                    // Cálculos finos em tempo de execução para exibição detalhada das regras de negócio
                    $data_fim = $rl['data_devolucao'] ? $rl['data_devolucao'] : $rl['data_prevista'];
                    $dias_reais = (strtotime($data_fim) - strtotime($rl['data_retirada'])) / 86400;
                    if ($dias_reais <= 0) $dias_reais = 1;

                    $valor_bruto = $dias_reais * $rl['valor_diaria'];
                    
                    // Cálculo de Multa caso o status gravado na devolução seja 'Atrasado'
                    $multa = 0;
                    if ($rl['status'] == 'Atrasado' && $rl['data_devolucao']) {
                        $dias_atraso = (strtotime($rl['data_devolucao']) - strtotime($rl['data_prevista'])) / 86400;
                        $multa = $dias_atraso * $rl['valor_diaria'] * 1.2;
                    }
                    $valor_final = $valor_bruto + $multa;

                    // Cor do Badge do Status
                    $bg_status = 'bg-secondary';
                    if ($rl['status'] == 'Atrasado') $bg_status = 'bg-danger';
                    elseif (strpos($rl['status'], 'Entregue') !== false) $bg_status = 'bg-success';
                ?>
                <tr>
                    <td><?= htmlspecialchars($rl['cliente']) ?></td>
                    <td><?= htmlspecialchars($rl['veiculo']) ?></td>
                    <td><?= $rl['categoria'] ?></td>
                    <td><?= date('d/m/Y', strtotime($rl['data_retirada'])) ?></td>
                    <td><?= $rl['data_devolucao'] ? date('d/m/Y', strtotime($rl['data_devolucao'])) : 'Em aberto' ?></td>
                    <td><?= $dias_reais ?></td>
                    <td>R$ <?= number_format($multa, 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($valor_final, 2, ',', '.') ?></td>
                    <td><span class="badge <?= $bg_status ?>"><?= $rl['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>