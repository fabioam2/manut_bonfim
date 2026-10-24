<?php
session_start();
require_once 'conexao.php';

// --- CONTROLE DE ACESSO ---
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_tipo_acesso'], ['gerencia', 'admin'])) {
    session_destroy();
    header('Location: login_gerencia.php?erro=acesso_negado');
    exit;
}

$nome_usuario = $_SESSION['usuario_nome'];

// --- LÓGICA DE LOGOUT ---
if (isset($_GET['acao']) && $_GET['acao'] === 'sair') {
    session_destroy();
    header('Location: login_gerencia.php');
    exit;
}

// --- BUSCAR CHAMADOS NO BANCO ---
try {
    $sql = "SELECT 
                c.id_chamado, c.solicitante_nome, c.data_abertura, c.status, c.prioridade,
                p.nome as nome_predio,
                l.nome as nome_local,
                s.nome as nome_servico
            FROM chamados c
            JOIN predios p ON c.id_predio = p.id_predio
            JOIN locais l ON c.id_local = l.id_local
            JOIN servicos s ON c.id_servico = s.id_servico
            ORDER BY FIELD(c.prioridade, 'Alta', 'Média', 'Baixa'), c.data_abertura DESC";
    $chamados = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    $chamados = [];
    $erro_sistema = "Erro ao carregar chamados: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerência de Chamados</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; }
        .navbar { background-color: #343a40; padding: 15px 30px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .navbar h1 { margin: 0; font-size: 1.5em; }
        .navbar a { color: #f8f9fa; text-decoration: none; background-color: #dc3545; padding: 8px 15px; border-radius: 5px; }
        .navbar a:hover { background-color: #c82333; }
        .container { padding: 20px; max-width: 1200px; margin: auto; }
        .content-box { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { color: #333; margin-top: 0; }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        .data-table th { background-color: #f8f9fa; }
        .data-table tr:nth-child(even) { background-color: #f2f2f2; }
        .tag { padding: 5px 10px; border-radius: 15px; color: white; font-weight: 500; font-size: 0.9em; text-align: center; }
        .status-Aberto { background-color: #007bff; }
        .status-Em-Atendimento { background-color: #ffc107; color: #212529; }
        .status-Finalizado { background-color: #28a745; }
        .status-Cancelado { background-color: #6c757d; }
        .status-Aguardando-Peça { background-color: #17a2b8; }
        .prioridade-Alta { background-color: #dc3545; }
        .prioridade-Média { background-color: #fd7e14; }
        .prioridade-Baixa { background-color: #6c757d; }
        .action-link { display: inline-block; padding: 8px 12px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>

    <nav class="navbar">
        <h1>Gerência de Chamados</h1>
        <a href="gerencia.php?acao=sair">Sair</a>
    </nav>

    <div class="container">
        <div class="content-box">
            <h2>Lista de Solicitações</h2>
            <p>Bem-vindo, <?= htmlspecialchars($nome_usuario) ?>. Aqui estão os chamados de manutenção.</p>

            <?php if (isset($erro_sistema)): ?>
                <p style="color: red;"><?= htmlspecialchars($erro_sistema) ?></p>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Data Abertura</th>
                            <th>Solicitante</th>
                            <th>Local</th>
                            <th>Serviço</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($chamados)): ?>
                            <tr><td colspan="8">Nenhum chamado encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($chamados as $chamado): ?>
                            <tr>
                                <td><?= htmlspecialchars($chamado['id_chamado']) ?></td>
                                <td><span class="tag status-<?= str_replace(' ', '-', htmlspecialchars($chamado['status'])) ?>"><?= htmlspecialchars($chamado['status']) ?></span></td>
                                <td><span class="tag prioridade-<?= htmlspecialchars($chamado['prioridade']) ?>"><?= htmlspecialchars($chamado['prioridade']) ?></span></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($chamado['data_abertura']))) ?></td>
                                <td><?= htmlspecialchars($chamado['solicitante_nome']) ?></td>
                                <td><?= htmlspecialchars($chamado['nome_predio']) ?> - <?= htmlspecialchars($chamado['nome_local']) ?></td>
                                <td><?= htmlspecialchars($chamado['nome_servico']) ?></td>
                                <td><a href="chamado_detalhe.php?id=<?= $chamado['id_chamado'] ?>" class="action-link">Gerenciar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
