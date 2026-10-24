<?php
require_once 'conexao.php';

$telefone_consulta = '';
$chamados = [];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefone_consulta = trim((string) filter_input(INPUT_POST, 'telefone'));
} elseif (isset($_GET['telefone'])) {
    $telefone_consulta = trim((string) filter_input(INPUT_GET, 'telefone'));
}

if (!empty($telefone_consulta)) {
    try {
        $sql_chamados = "SELECT c.*, l.nome as nome_local, s.nome as nome_servico
                         FROM chamados c
                         JOIN locais l ON c.id_local = l.id_local
                         JOIN servicos s ON c.id_servico = s.id_servico
                         WHERE c.solicitante_telefone = ?
                         ORDER BY c.data_abertura DESC";
        $stmt_chamados = $pdo->prepare($sql_chamados);
        $stmt_chamados->execute([$telefone_consulta]);
        $chamados_encontrados = $stmt_chamados->fetchAll();

        if (empty($chamados_encontrados)) {
            $erro = "Nenhum chamado encontrado para este número de telefone.";
        } else {
            $sql_historico = "SELECT * FROM chamado_historico WHERE id_chamado = ? ORDER BY data_ocorrencia ASC";
            $stmt_historico = $pdo->prepare($sql_historico);
            foreach($chamados_encontrados as $key => $chamado) {
                $stmt_historico->execute([$chamado['id_chamado']]);
                $chamados_encontrados[$key]['historico'] = $stmt_historico->fetchAll();
            }
            $chamados = $chamados_encontrados;
        }
    } catch (PDOException $e) { $erro = "Ocorreu um erro ao consultar os chamados."; }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erro = "Por favor, informe um número de telefone.";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Meus Chamados</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; background-color: #f4f4f9; color: #333; margin: 0; padding: 15px; }
        .container { max-width: 800px; margin: 20px auto; }
        .form-container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0056b3; text-align: center; }
        .btn { display: block; width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 5px; font-size: 1.1em; font-weight: bold; cursor: pointer; text-align: center; }
        .back-link { display: inline-block; margin-top: 15px; color: #007bff; text-decoration: none; }
        .mensagem-erro { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin-top: 20px; border-radius: 5px; }
        .chamado-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 20px; }
        .chamado-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .status { padding: 5px 10px; border-radius: 15px; color: white; font-weight: 500; font-size: 0.9em; }
        .status-Aberto { background-color: #007bff; }
        .status-Em-Atendimento { background-color: #ffc107; color: #212529; }
        .status-Finalizado { background-color: #28a745; }
        .status-Cancelado { background-color: #6c757d; }
        .status-Aguardando-Peça { background-color: #17a2b8; }
        .historico-lista { list-style: none; padding-left: 20px; margin-top: 15px; border-left: 3px solid #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h1>Consultar Meus Chamados</h1>
            <p>Digite o número de telefone usado na abertura do chamado para ver o andamento.</p>
            <form action="consulta_chamado.php" method="POST">
                <div class="form-group"><label for="telefone">Seu Telefone</label><input type="tel" id="telefone" name="telefone" value="<?= htmlspecialchars($telefone_consulta) ?>" required></div>
                <button type="submit" class="btn">Consultar</button>
            </form>
            <a href="index.php" class="back-link">← Voltar para abrir um chamado</a>
        </div>
        <?php if ($erro): ?><div class="mensagem-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
        <div id="resultados">
            <?php foreach ($chamados as $chamado): ?>
                <div class="chamado-card">
                    <div class="chamado-header"><h2>Chamado #<?= htmlspecialchars($chamado['id_chamado']) ?></h2><span class="status status-<?= str_replace(' ', '-', htmlspecialchars($chamado['status'])) ?>"><?= htmlspecialchars($chamado['status']) ?></span></div>
                    <div class="chamado-body">
                        <p><strong>Aberto em:</strong> <?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></p>
                        <p><strong>Local:</strong> <?= htmlspecialchars($chamado['nome_local']) ?></p>
                        <p><strong>Serviço:</strong> <?= htmlspecialchars($chamado['nome_servico']) ?></p>
                        <p><strong>Prioridade:</strong> <?= htmlspecialchars($chamado['prioridade']) ?></p>
                        <h4>Histórico do Atendimento:</h4>
                        <ul class="historico-lista">
                            <?php foreach($chamado['historico'] as $item): ?><li><strong><?= date('d/m/Y H:i', strtotime($item['data_ocorrencia'])) ?>:</strong> <?= htmlspecialchars($item['descricao_acao']) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
