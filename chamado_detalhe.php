<?php
session_start();
require_once 'conexao.php';

// --- CONTROLE DE ACESSO ---
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_tipo_acesso'], ['gerencia', 'admin'])) {
    session_destroy();
    header('Location: login_gerencia.php?erro=acesso_negado');
    exit;
}

$id_chamado = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_chamado) {
    header('Location: gerencia.php');
    exit;
}

$id_usuario_logado = $_SESSION['usuario_id'];
$nome_usuario_logado = $_SESSION['usuario_nome'];
$mensagem = [];

// --- PROCESSAR FORMULÁRIO DE ATUALIZAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novo_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $nova_prioridade = filter_input(INPUT_POST, 'prioridade', FILTER_SANITIZE_STRING);
    $id_usuario_atribuido = filter_input(INPUT_POST, 'usuario_atribuido', FILTER_VALIDATE_INT);
    $comentario = trim(filter_input(INPUT_POST, 'comentario', FILTER_SANITIZE_STRING));

    $status_permitidos = ['Aberto', 'Em Atendimento', 'Aguardando Peça', 'Finalizado', 'Cancelado'];
    $prioridades_permitidas = ['Baixa', 'Média', 'Alta'];

    if (!in_array($novo_status, $status_permitidos) || !in_array($nova_prioridade, $prioridades_permitidas)) {
        $mensagem = ['tipo' => 'erro', 'texto' => 'Valores inválidos selecionados.'];
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Atualiza o chamado
            $sql_update = "UPDATE chamados SET status = ?, prioridade = ?, id_usuario_atribuido = ? WHERE id_chamado = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$novo_status, $nova_prioridade, ($id_usuario_atribuido ?: null), $id_chamado]);

            // 2. Cria a descrição para o histórico
            $descricao_historico = "Chamado atualizado por {$nome_usuario_logado}. Status: '{$novo_status}', Prioridade: '{$nova_prioridade}'.";
            if (!empty($comentario)) {
                $descricao_historico .= " Comentário: \"{$comentario}\"";
            }
            
            // 3. Insere no histórico
            $sql_hist = "INSERT INTO chamado_historico (id_chamado, id_usuario, descricao_acao) VALUES (?, ?, ?)";
            $stmt_hist = $pdo->prepare($sql_hist);
            $stmt_hist->execute([$id_chamado, $id_usuario_logado, $descricao_historico]);
            
            $pdo->commit();
            $mensagem = ['tipo' => 'sucesso', 'texto' => 'Chamado atualizado com sucesso!'];

        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = ['tipo' => 'erro', 'texto' => 'Erro ao atualizar o chamado.'];
        }
    }
}

// --- BUSCAR DADOS COMPLETOS DO CHAMADO PARA EXIBIÇÃO ---
try {
    $sql = "SELECT c.*, p.nome as nome_predio, l.nome as nome_local, s.nome as nome_servico, u.nome_completo as nome_usuario_atribuido
            FROM chamados c
            JOIN predios p ON c.id_predio = p.id_predio
            JOIN locais l ON c.id_local = l.id_local
            JOIN servicos s ON c.id_servico = s.id_servico
            LEFT JOIN usuarios u ON c.id_usuario_atribuido = u.id_usuario
            WHERE c.id_chamado = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_chamado]);
    $chamado = $stmt->fetch();

    if (!$chamado) {
        header('Location: gerencia.php');
        exit;
    }

    $sql_hist = "SELECT h.*, u.nome_completo as nome_usuario 
                 FROM chamado_historico h 
                 LEFT JOIN usuarios u ON h.id_usuario = u.id_usuario
                 WHERE h.id_chamado = ? ORDER BY h.data_ocorrencia DESC";
    $stmt_hist = $pdo->prepare($sql_hist);
    $stmt_hist->execute([$id_chamado]);
    $historico = $stmt_hist->fetchAll();

    $usuarios_manutencao = $pdo->query("SELECT id_usuario, nome_completo FROM usuarios WHERE tipo_acesso IN ('admin', 'gerencia') AND ativo = 1 ORDER BY nome_completo")->fetchAll();

} catch (PDOException $e) {
    die("Erro fatal ao carregar dados do chamado.");
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Chamado #<?= htmlspecialchars($chamado['id_chamado']) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; }
        .navbar { background-color: #343a40; padding: 15px 30px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .navbar h1 { margin: 0; font-size: 1.5em; }
        .navbar .nav-links a { color: #f8f9fa; text-decoration: none; padding: 8px 15px; border-radius: 5px; margin-left: 10px; }
        .navbar .nav-links a.back-btn { background-color: #6c757d; }
        .navbar .nav-links a.logout-btn { background-color: #dc3545; }
        .container { max-width: 1100px; margin: 20px auto; padding: 20px; }
        .grid-container { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .main-content, .sidebar { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        h2 { color: #333; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .detail-item { margin-bottom: 15px; }
        .detail-item strong { display: block; color: #495057; margin-bottom: 3px; }
        .detail-item span { color: #212529; }
        .chamado-foto { max-width: 100%; border-radius: 5px; margin-top: 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        .btn { display: block; width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 5px; font-size: 1.1em; font-weight: bold; cursor: pointer; text-align: center; }
        .historico-lista { list-style: none; padding: 0; margin-top: 20px; }
        .historico-item { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid #007bff; }
        .historico-item p { margin: 0; }
        .historico-meta { font-size: 0.9em; color: #6c757d; }
        .mensagem { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
        .mensagem-sucesso { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .mensagem-erro { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        @media (max-width: 900px) { .grid-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <nav class="navbar">
        <h1>Chamado #<?= htmlspecialchars($chamado['id_chamado']) ?></h1>
        <div class="nav-links">
            <a href="gerencia.php" class="back-btn">Voltar à Lista</a>
            <a href="gerencia.php?acao=sair" class="logout-btn">Sair</a>
        </div>
    </nav>

    <div class="container">
        <?php if (!empty($mensagem)): ?>
            <div class="mensagem mensagem-<?= htmlspecialchars($mensagem['tipo']) ?>"><?= htmlspecialchars($mensagem['texto']) ?></div>
        <?php endif; ?>

        <div class="grid-container">
            <div class="main-content">
                <h2>Detalhes da Solicitação</h2>
                <div class="detail-item"><strong>Solicitante:</strong> <span><?= htmlspecialchars($chamado['solicitante_nome']) ?></span></div>
                <div class="detail-item"><strong>Telefone:</strong> <span><?= htmlspecialchars($chamado['solicitante_telefone']) ?></span></div>
                <div class="detail-item"><strong>Data de Abertura:</strong> <span><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></span></div>
                <div class="detail-item"><strong>Prioridade:</strong> <span><?= htmlspecialchars($chamado['prioridade']) ?></span></div>
                <div class="detail-item"><strong>Prédio:</strong> <span><?= htmlspecialchars($chamado['nome_predio']) ?></span></div>
                <div class="detail-item"><strong>Local:</strong> <span><?= htmlspecialchars($chamado['nome_local']) ?></span></div>
                <div class="detail-item"><strong>Serviço:</strong> <span><?= htmlspecialchars($chamado['nome_servico']) ?></span></div>
                <div class="detail-item"><strong>Descrição do Problema:</strong> <span><?= nl2br(htmlspecialchars($chamado['descricao_problema'] ?: 'Nenhuma descrição fornecida.')) ?></span></div>
                
                <?php if ($chamado['foto_path']): ?>
                <div class="detail-item">
                    <strong>Foto:</strong>
                    <a href="<?= htmlspecialchars($chamado['foto_path']) ?>" target="_blank">
                        <img src="<?= htmlspecialchars($chamado['foto_path']) ?>" alt="Foto do problema" class="chamado-foto">
                    </a>
                </div>
                <?php endif; ?>

                <h2 style="margin-top: 30px;">Histórico de Interações</h2>
                <ul class="historico-lista">
                    <?php foreach ($historico as $item): ?>
                    <li class="historico-item">
                        <p><?= htmlspecialchars($item['descricao_acao']) ?></p>
                        <div class="historico-meta">
                            <span><?= date('d/m/Y H:i', strtotime($item['data_ocorrencia'])) ?></span>
                            <?php if ($item['nome_usuario']): ?>
                                por <strong><?= htmlspecialchars($item['nome_usuario']) ?></strong>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <aside class="sidebar">
                <h2>Gerenciar Chamado</h2>
                <form action="chamado_detalhe.php?id=<?= $id_chamado ?>" method="POST">
                    <div class="form-group">
                        <label for="status">Alterar Status</label>
                        <select name="status" id="status">
                            <?php foreach (['Aberto', 'Em Atendimento', 'Aguardando Peça', 'Finalizado', 'Cancelado'] as $status_opcao): ?>
                                <option value="<?= $status_opcao ?>" <?= $chamado['status'] == $status_opcao ? 'selected' : '' ?>><?= $status_opcao ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="prioridade">Alterar Prioridade</label>
                        <select name="prioridade" id="prioridade">
                            <?php foreach (['Baixa', 'Média', 'Alta'] as $prioridade_opcao): ?>
                                <option value="<?= $prioridade_opcao ?>" <?= $chamado['prioridade'] == $prioridade_opcao ? 'selected' : '' ?>><?= $prioridade_opcao ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="usuario_atribuido">Atribuir a</label>
                        <select name="usuario_atribuido" id="usuario_atribuido">
                            <option value="0">-- Ninguém --</option>
                            <?php foreach ($usuarios_manutencao as $usuario): ?>
                                <option value="<?= $usuario['id_usuario'] ?>" <?= $chamado['id_usuario_atribuido'] == $usuario['id_usuario'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usuario['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="comentario">Adicionar Comentário (opcional)</label>
                        <textarea name="comentario" id="comentario" rows="4" placeholder="Adicione uma observação sobre a atualização..."></textarea>
                    </div>
                    <button type="submit" class="btn">Salvar Alterações</button>
                </form>
            </aside>
        </div>
    </div>

</body>
</html>
