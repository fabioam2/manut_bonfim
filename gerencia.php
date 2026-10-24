<?php
require_once 'seguranca.php';
iniciar_sessao();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_tipo_acesso'], ['gerencia', 'admin'])) {
    session_destroy();
    header('Location: login_gerencia.php?erro=acesso_negado');
    exit;
}

$id_usuario_logado = $_SESSION['usuario_id'];
$tipo_acesso_logado = $_SESSION['usuario_tipo_acesso'];
$nome_usuario = $_SESSION['usuario_nome'];
$mensagem = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
}

$view_mode = ($_GET['view'] ?? '') === 'todos' ? 'todos' : 'meus_grupos';

if (isset($_GET['acao']) && $_GET['acao'] === 'sair') {
    session_destroy();
    header('Location: login_gerencia.php');
    exit;
}

// --- PROCESSAR FORMULÁRIO DE TROCA DE SENHA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trocar_senha'])) {
    $senha_antiga = $_POST['senha_antiga'];
    $nova_senha = $_POST['nova_senha'];
    $confirma_senha = $_POST['confirma_senha'];

    if (empty($senha_antiga) || empty($nova_senha) || empty($confirma_senha)) {
        $mensagem = ['tipo' => 'erro', 'texto' => 'Todos os campos da senha são obrigatórios.'];
    } elseif ($nova_senha !== $confirma_senha) {
        $mensagem = ['tipo' => 'erro', 'texto' => 'A nova senha e a confirmação não correspondem.'];
    } else {
        try {
            $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_usuario_logado]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha_antiga, $usuario['senha'])) {
                $hash_nova_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id_usuario = ?");
                $stmt_update->execute([$hash_nova_senha, $id_usuario_logado]);
                $mensagem = ['tipo' => 'sucesso', 'texto' => 'Senha alterada com sucesso!'];
            } else {
                $mensagem = ['tipo' => 'erro', 'texto' => 'A senha antiga está incorreta.'];
            }
        } catch (PDOException $e) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'Erro ao alterar a senha.'];
        }
    }
}

// --- PROCESSAR FORMULÁRIO DE ATUALIZAÇÃO RÁPIDA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_chamado'])) {
    $id_chamado = filter_input(INPUT_POST, 'id_chamado', FILTER_VALIDATE_INT);
    $novo_status = filter_input(INPUT_POST, 'status');
    $id_grupo = filter_input(INPUT_POST, 'id_grupo', FILTER_VALIDATE_INT);
    $id_usuario_atribuido = filter_input(INPUT_POST, 'usuario_atribuido', FILTER_VALIDATE_INT);

    if ($id_chamado && in_array($novo_status, ['Aberto', 'Em Atendimento', 'Aguardando Peça', 'Finalizado', 'Cancelado'], true)) {
        $pdo->beginTransaction();
        try {
            $sql_update = "UPDATE chamados SET status = ?, id_grupo = ?, id_usuario_atribuido = ? WHERE id_chamado = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$novo_status, ($id_grupo ?: null), ($id_usuario_atribuido ?: null), $id_chamado]);
            $descricao_historico = "Chamado atualizado via painel de gerência por {$nome_usuario}.";
            $sql_hist = "INSERT INTO chamado_historico (id_chamado, id_usuario, descricao_acao) VALUES (?, ?, ?)";
            $stmt_hist = $pdo->prepare($sql_hist);
            $stmt_hist->execute([$id_chamado, $id_usuario_logado, $descricao_historico]);
            $pdo->commit();
            $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => "Chamado #{$id_chamado} atualizado com sucesso!"];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => "Erro ao atualizar o chamado #{$id_chamado}."];
        }
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
}

try {
    $todos_grupos = $pdo->query("SELECT id_grupo, nome FROM grupos ORDER BY nome")->fetchAll();
    $todos_usuarios = $pdo->query("SELECT id_usuario, nome_completo FROM usuarios WHERE tipo_acesso IN ('admin', 'gerencia', 'Geral') AND ativo = 1 ORDER BY nome_completo")->fetchAll();
    $status_permitidos = ['Aberto', 'Em Atendimento', 'Aguardando Peça', 'Finalizado', 'Cancelado'];
    $prioridades_permitidas = ['Baixa', 'Média', 'Alta'];
    $sla_regras = $pdo->query("SELECT * FROM sla_config")->fetchAll();

    $filtros = [
        'data' => $_GET['filtro_data'] ?? '', 'prioridade' => $_GET['filtro_prioridade'] ?? '', 'local' => $_GET['filtro_local'] ?? '',
        'servico' => $_GET['filtro_servico'] ?? '', 'solicitante' => $_GET['filtro_solicitante'] ?? '', 'status' => $_GET['filtro_status'] ?? '',
        'grupo' => $_GET['filtro_grupo'] ?? '', 'usuario' => $_GET['filtro_usuario'] ?? ''
    ];

    $params = [];
    $sql_conditions = [];

    if ($view_mode !== 'todos' && $tipo_acesso_logado !== 'admin') {
        $stmt_grupos_usuario = $pdo->prepare("SELECT id_grupo FROM usuario_grupos WHERE id_usuario = ?");
        $stmt_grupos_usuario->execute([$id_usuario_logado]);
        $grupos_usuario = $stmt_grupos_usuario->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($grupos_usuario)) {
            $in_clause = implode(',', array_fill(0, count($grupos_usuario), '?'));
            $sql_conditions[] = "c.id_grupo IN ($in_clause)";
            $params = array_merge($params, $grupos_usuario);
        } else { $sql_conditions[] = "1 = 0"; }
    }

    if (!empty($filtros['data'])) { $sql_conditions[] = "DATE(c.data_abertura) = ?"; $params[] = $filtros['data']; }
    if (!empty($filtros['prioridade'])) { $sql_conditions[] = "c.prioridade = ?"; $params[] = $filtros['prioridade']; }
    if (!empty($filtros['local'])) { $sql_conditions[] = "l.nome LIKE ?"; $params[] = '%' . $filtros['local'] . '%'; }
    if (!empty($filtros['servico'])) { $sql_conditions[] = "s.nome LIKE ?"; $params[] = '%' . $filtros['servico'] . '%'; }
    if (!empty($filtros['solicitante'])) { $sql_conditions[] = "c.solicitante_nome LIKE ?"; $params[] = '%' . $filtros['solicitante'] . '%'; }
    if (!empty($filtros['status'])) { $sql_conditions[] = "c.status = ?"; $params[] = $filtros['status']; } 
    else { $sql_conditions[] = "c.status NOT IN ('Finalizado', 'Cancelado')"; }
    if (!empty($filtros['grupo'])) { $sql_conditions[] = "c.id_grupo = ?"; $params[] = $filtros['grupo']; }
    if (!empty($filtros['usuario'])) { $sql_conditions[] = "c.id_usuario_atribuido = ?"; $params[] = $filtros['usuario']; }

    $sql_where = count($sql_conditions) > 0 ? 'WHERE ' . implode(' AND ', $sql_conditions) : '';
    $sql = "SELECT c.*, l.nome as nome_local, s.nome as nome_servico, g.nome as nome_grupo FROM chamados c JOIN locais l ON c.id_local = l.id_local JOIN servicos s ON c.id_servico = s.id_servico LEFT JOIN grupos g ON c.id_grupo = g.id_grupo $sql_where ORDER BY FIELD(c.prioridade, 'Alta', 'Média', 'Baixa'), c.data_abertura DESC";
    
    $stmt_chamados = $pdo->prepare($sql);
    $stmt_chamados->execute($params);
    $chamados = $stmt_chamados->fetchAll();

    function getSlaColor($data_abertura, $status, $regras) {
        if (in_array($status, ['Finalizado', 'Cancelado'])) return '';
        $dias_aberto = (new DateTime())->diff(new DateTime($data_abertura))->days;
        foreach ($regras as $regra) {
            if ($dias_aberto >= $regra['dias_inicio'] && $dias_aberto <= $regra['dias_fim']) return $regra['cor'];
        }
        return '';
    }
} catch (PDOException $e) {
    $chamados = [];
    error_log($e->getMessage());
    $erro_sistema = "Erro ao carregar os dados. Tente novamente mais tarde.";
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
        .navbar a { color: #f8f9fa; text-decoration: none; background-color: #dc3545; padding: 8px 15px; border-radius: 5px; }
        .container { padding: 20px; max-width: 1800px; margin: auto; }
        .content-box { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header-section { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .view-toggle { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
        .view-toggle a { text-decoration: none; padding: 8px 15px; border-radius: 5px; font-weight: 500; }
        .view-toggle a.active { background-color: #007bff; color: white; }
        .view-toggle a:not(.active) { background-color: #e9ecef; color: #007bff; }
        .view-toggle a.admin-btn { background-color: #ffc107; color: #212529; }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { border: 1px solid #dee2e6; padding: 8px; text-align: left; vertical-align: middle; }
        .data-table thead tr:not(.filtro-header) th { background-color: #e9ecef; }
        .data-table th { font-size: 0.9em; }
        .data-table select, .data-table input, .data-table textarea { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .data-table textarea { resize: vertical; height: 3.5em; background-color: #f8f9fa; }
        .data-table .action-cell { display: flex; gap: 5px; align-items: center; }
        .data-table .btn-save { padding: 6px 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .data-table .btn-details { padding: 6px 10px; background-color: #6c757d; color: white; border-radius: 4px; text-decoration: none; font-size: 0.9em; }
        .tag { padding: 5px 10px; border-radius: 15px; color: white; font-weight: 500; font-size: 0.9em; text-align: center; }
        .prioridade-Alta { background-color: #dc3545; }
        .prioridade-Média { background-color: #fd7e14; }
        .prioridade-Baixa { background-color: #6c757d; }
        .mensagem { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
        .mensagem-sucesso { color: #155724; background-color: #d4edda; }
        .mensagem-erro { color: #721c24; background-color: #f8d7da; }
        .filtro-header th { padding: 10px; background-color: #f8f9fa; }
        .filtro-header .btn-filtro { padding: 8px 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .filtro-header .btn-limpar { padding: 8px 12px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; }
        .btn-trocar-senha { background-color: #6c757d; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: 500; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn-submit { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <nav class="navbar"><h1>Gerência de Chamados</h1><a href="gerencia.php?acao=sair">Sair</a></nav>
    <div class="container">
        <div class="content-box">
            <div class="header-section">
                <div><h2>Lista de Solicitações</h2><p>Bem-vindo, <?= htmlspecialchars($nome_usuario) ?>. <button id="btnAbrirModal" class="btn-trocar-senha">Trocar Senha</button></p></div>
                <div class="view-toggle">
                    <div>
                        <a href="?view=meus_grupos" class="<?= $view_mode === 'meus_grupos' ? 'active' : '' ?>">Meus Grupos</a>
                        <a href="?view=todos" class="<?= $view_mode === 'todos' ? 'active' : '' ?>">Ver Todos</a>
                    </div>
                    <?php if ($tipo_acesso_logado === 'admin'): ?>
                    <div style="margin-top: 16px;"><a href="admin.php" class="admin-btn">Admin</a></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($mensagem)): ?><div class="mensagem mensagem-<?= htmlspecialchars($mensagem['tipo']) ?>"><?= htmlspecialchars($mensagem['texto']) ?></div><?php endif; ?>
            <?php if (isset($erro_sistema)): ?><p style="color: red;"><?= htmlspecialchars($erro_sistema) ?></p><?php endif; ?>

            <div class="table-responsive">
                <form action="gerencia.php" method="GET">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view_mode) ?>">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th><th>Prioridade</th><th>Local</th><th>Serviço</th><th style="min-width: 200px;">Descrição</th><th>Solicitante</th><th style="min-width: 135px;">Status</th><th style="min-width: 135px;">Grupo</th><th style="min-width: 135px;">Atribuir a</th><th style="min-width: 150px;">Ação</th>
                            </tr>
                            <tr class="filtro-header">
                                <th><input type="date" name="filtro_data" value="<?= htmlspecialchars($filtros['data']) ?>"></th>
                                <th><select name="filtro_prioridade"><option value=""></option><?php foreach($prioridades_permitidas as $p): ?><option value="<?= $p ?>" <?= $filtros['prioridade'] == $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select></th>
                                <th><input type="text" name="filtro_local" value="<?= htmlspecialchars($filtros['local']) ?>"></th>
                                <th><input type="text" name="filtro_servico" value="<?= htmlspecialchars($filtros['servico']) ?>"></th>
                                <th></th>
                                <th><input type="text" name="filtro_solicitante" value="<?= htmlspecialchars($filtros['solicitante']) ?>"></th>
                                <th><select name="filtro_status"><option value=""></option><?php foreach($status_permitidos as $s): ?><option value="<?= $s ?>" <?= $filtros['status'] == $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></th>
                                <th><select name="filtro_grupo"><option value=""></option><?php foreach($todos_grupos as $g): ?><option value="<?= $g['id_grupo'] ?>" <?= $filtros['grupo'] == $g['id_grupo'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nome']) ?></option><?php endforeach; ?></select></th>
                                <th><select name="filtro_usuario"><option value=""></option><?php foreach($todos_usuarios as $u): ?><option value="<?= $u['id_usuario'] ?>" <?= $filtros['usuario'] == $u['id_usuario'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nome_completo']) ?></option><?php endforeach; ?></select></th>
                                <th><button type="submit" class="btn-filtro">Filtrar</button> <a href="gerencia.php?view=<?= htmlspecialchars($view_mode) ?>" class="btn-limpar">Limpar</a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($chamados)): ?>
                                <tr><td colspan="10">Nenhum chamado encontrado para a sua visualização.</td></tr>
                            <?php else: ?>
                                <?php foreach ($chamados as $chamado): 
                                    $cor_sla = getSlaColor($chamado['data_abertura'], $chamado['status'], $sla_regras);
                                ?>
                                <tr style="background-color: <?= $cor_sla ?>;">
                                    <form action="gerencia.php?view=<?= htmlspecialchars($view_mode) ?>" method="POST"><?= csrf_campo() ?>
                                        <input type="hidden" name="id_chamado" value="<?= $chamado['id_chamado'] ?>">
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($chamado['data_abertura']))) ?></td>
                                        <td><span class="tag prioridade-<?= htmlspecialchars($chamado['prioridade']) ?>"><?= htmlspecialchars($chamado['prioridade']) ?></span></td>
                                        <td><?= htmlspecialchars($chamado['nome_local']) ?></td>
                                        <td><?= htmlspecialchars($chamado['nome_servico']) ?></td>
                                        <td><textarea readonly rows="2"><?= htmlspecialchars($chamado['descricao_problema']) ?></textarea></td>
                                        <td><?= htmlspecialchars($chamado['solicitante_nome']) ?></td>
                                        <td><select name="status"><?php foreach ($status_permitidos as $status): ?><option value="<?= $status ?>" <?= $chamado['status'] == $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></td>
                                        <td><select name="id_grupo"><option value="">-- Nenhum --</option><?php foreach ($todos_grupos as $grupo): ?><option value="<?= $grupo['id_grupo'] ?>" <?= $chamado['id_grupo'] == $grupo['id_grupo'] ? 'selected' : '' ?>><?= htmlspecialchars($grupo['nome']) ?></option><?php endforeach; ?></select></td>
                                        <td><select name="usuario_atribuido"><option value="">-- Ninguém --</option><?php foreach ($todos_usuarios as $usuario): ?><option value="<?= $usuario['id_usuario'] ?>" <?= $chamado['id_usuario_atribuido'] == $usuario['id_usuario'] ? 'selected' : '' ?>><?= htmlspecialchars($usuario['nome_completo']) ?></option><?php endforeach; ?></select></td>
                                        <td class="action-cell"><button type="submit" name="salvar_chamado" class="btn-save">Salvar</button><a href="chamado_detalhe.php?id=<?= $chamado['id_chamado'] ?>" class="btn-details">Detalhes</a></td>
                                    </form>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- O Modal de Troca de Senha -->
    <div id="modalSenha" class="modal">
      <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2>Alterar Senha</h2>
        <form action="gerencia.php" method="POST"><?= csrf_campo() ?>
            <div class="form-group">
                <label for="senha_antiga">Senha Antiga:</label>
                <input type="password" id="senha_antiga" name="senha_antiga" required>
            </div>
            <div class="form-group">
                <label for="nova_senha">Nova Senha:</label>
                <input type="password" id="nova_senha" name="nova_senha" required>
            </div>
            <div class="form-group">
                <label for="confirma_senha">Confirmar Nova Senha:</label>
                <input type="password" id="confirma_senha" name="confirma_senha" required>
            </div>
            <button type="submit" name="trocar_senha" class="btn-submit">Salvar Nova Senha</button>
        </form>
      </div>
    </div>

<script>
    // Script para controlar o modal
    var modal = document.getElementById("modalSenha");
    var btn = document.getElementById("btnAbrirModal");
    var span = document.getElementsByClassName("close-button")[0];

    btn.onclick = function() {
        modal.style.display = "block";
    }
    span.onclick = function() {
        modal.style.display = "none";
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

</body>
</html>
