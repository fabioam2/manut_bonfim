<?php
require_once 'seguranca.php';
iniciar_sessao();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo_acesso'] !== 'admin') {
    session_destroy();
    header('Location: login.php?erro=acesso_negado');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_GET['acao'] ?? '') === 'excluir') {
    csrf_verificar();
}

$nome_usuario = $_SESSION['usuario_nome'];
$id_usuario_logado = $_SESSION['usuario_id'];
$tabela_selecionada = $_GET['tabela'] ?? 'sla'; // Padrão para SLA
$mensagem = [];

if (isset($_GET['acao']) && $_GET['acao'] === 'sair') {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
}

// --- GERENCIAR SLA ---
if ($tabela_selecionada === 'sla') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_sla'])) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE sla_config SET dias_inicio = ?, dias_fim = ? WHERE nivel = ?");
            
            $sla_niveis = ['Normal', 'Atenção', 'Estourado'];
            foreach ($sla_niveis as $nivel) {
                $chave_nivel = str_replace(['ç', 'ã'], ['c', 'a'], strtolower($nivel));
                $dias_inicio = filter_input(INPUT_POST, $chave_nivel . '_inicio', FILTER_VALIDATE_INT);
                $dias_fim = filter_input(INPUT_POST, $chave_nivel . '_fim', FILTER_VALIDATE_INT);
                
                if ($dias_inicio !== null && $dias_fim !== null) {
                    $stmt->execute([$dias_inicio, $dias_fim, $nivel]);
                }
            }
            $pdo->commit();
            $mensagem = ['tipo' => 'sucesso', 'texto' => 'Regras de SLA atualizadas com sucesso!'];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = ['tipo' => 'erro', 'texto' => 'Erro ao atualizar as regras de SLA.'];
        }
    }
    $stmt_sla = $pdo->query("SELECT * FROM sla_config");
    $sla_configs_raw = $stmt_sla->fetchAll();
    $sla_config = [];
    foreach ($sla_configs_raw as $config) {
        $sla_config[$config['nivel']] = $config;
    }
}

// --- GERENCIAR GRUPOS ---
if ($tabela_selecionada === 'grupos') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_grupo'])) {
        $id_grupo = filter_input(INPUT_POST, 'id_grupo', FILTER_VALIDATE_INT);
        $nome_grupo = trim((string) filter_input(INPUT_POST, 'nome'));
        if (empty($nome_grupo)) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'O nome do grupo é obrigatório.'];
        } else {
            try {
                if ($id_grupo) {
                    $stmt = $pdo->prepare("UPDATE grupos SET nome = ? WHERE id_grupo = ?");
                    $stmt->execute([$nome_grupo, $id_grupo]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Grupo atualizado!'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO grupos (nome) VALUES (?)");
                    $stmt->execute([$nome_grupo]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Grupo cadastrado!'];
                }
            } catch (PDOException $e) { $mensagem = ['tipo' => 'erro', 'texto' => 'Erro: este nome de grupo já existe.']; }
        }
    }
    if (($_GET['acao'] ?? '') === 'excluir' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM grupos WHERE id_grupo = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Grupo excluído.'];
        } catch (PDOException $e) {
            $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => 'Erro ao excluir. O grupo pode estar em uso.'];
        }
        header('Location: admin.php?tabela=grupos');
        exit;
    }
    $grupos = $pdo->query("SELECT * FROM grupos ORDER BY nome")->fetchAll();
    if (($_GET['acao'] ?? '') === 'editar' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM grupos WHERE id_grupo = ?");
        $stmt->execute([$_GET['id']]);
        $grupo_para_editar = $stmt->fetch();
    }
}

// --- GERENCIAR LOCAIS ---
if ($tabela_selecionada === 'locais') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_local'])) {
        $id_local = filter_input(INPUT_POST, 'id_local', FILTER_VALIDATE_INT);
        $nome_local = trim((string) filter_input(INPUT_POST, 'nome'));
        if (empty($nome_local)) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'O nome do local é obrigatório.'];
        } else {
            try {
                if ($id_local) {
                    $stmt = $pdo->prepare("UPDATE locais SET nome = ? WHERE id_local = ?");
                    $stmt->execute([$nome_local, $id_local]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Local atualizado!'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO locais (nome) VALUES (?)");
                    $stmt->execute([$nome_local]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Local cadastrado!'];
                }
            } catch (PDOException $e) { $mensagem = ['tipo' => 'erro', 'texto' => 'Erro: este nome de local já existe.']; }
        }
    }
    if (($_GET['acao'] ?? '') === 'excluir' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM locais WHERE id_local = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Local excluído.'];
        } catch (PDOException $e) { $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => 'Erro ao excluir. O local pode estar em uso em um chamado.']; }
        header('Location: admin.php?tabela=locais');
        exit;
    }
    $locais = $pdo->query("SELECT * FROM locais ORDER BY nome")->fetchAll();
    if (($_GET['acao'] ?? '') === 'editar' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM locais WHERE id_local = ?");
        $stmt->execute([$_GET['id']]);
        $local_para_editar = $stmt->fetch();
    }
}

// --- GERENCIAR SERVIÇOS ---
if ($tabela_selecionada === 'servicos') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_servico'])) {
        $id_servico = filter_input(INPUT_POST, 'id_servico', FILTER_VALIDATE_INT);
        $nome_servico = trim((string) filter_input(INPUT_POST, 'nome'));
        $id_grupo = filter_input(INPUT_POST, 'id_grupo', FILTER_VALIDATE_INT);
        if (empty($nome_servico)) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'O nome do serviço é obrigatório.'];
        } else {
            try {
                if ($id_servico) {
                    $stmt = $pdo->prepare("UPDATE servicos SET nome = ?, id_grupo = ? WHERE id_servico = ?");
                    $stmt->execute([$nome_servico, ($id_grupo ?: null), $id_servico]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Serviço atualizado!'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO servicos (nome, id_grupo) VALUES (?, ?)");
                    $stmt->execute([$nome_servico, ($id_grupo ?: null)]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Serviço cadastrado!'];
                }
            } catch (PDOException $e) { $mensagem = ['tipo' => 'erro', 'texto' => 'Erro: este nome de serviço já existe.']; }
        }
    }
    if (($_GET['acao'] ?? '') === 'excluir' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM servicos WHERE id_servico = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Serviço excluído.'];
        } catch (PDOException $e) { $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => 'Erro ao excluir. O serviço pode estar em uso em um chamado.']; }
        header('Location: admin.php?tabela=servicos');
        exit;
    }
    $servicos = $pdo->query("SELECT s.*, g.nome as nome_grupo FROM servicos s LEFT JOIN grupos g ON s.id_grupo = g.id_grupo ORDER BY s.nome")->fetchAll();
    $grupos_select = $pdo->query("SELECT * FROM grupos ORDER BY nome")->fetchAll();
    if (($_GET['acao'] ?? '') === 'editar' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id_servico = ?");
        $stmt->execute([$_GET['id']]);
        $servico_para_editar = $stmt->fetch();
    }
}

// --- GERENCIAR USUÁRIOS ---
if ($tabela_selecionada === 'usuarios') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_usuario'])) {
        $id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
        $nome_completo = trim((string) filter_input(INPUT_POST, 'nome_completo'));
        $login = trim((string) filter_input(INPUT_POST, 'login'));
        $senha = $_POST['senha'];
        $tipo_acesso = in_array($_POST['tipo_acesso'] ?? '', ['admin', 'gerencia', 'Geral'], true) ? $_POST['tipo_acesso'] : 'gerencia';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $grupos_usuario = $_POST['grupos'] ?? [];

        if (empty($nome_completo) || empty($login) || (empty($senha) && !$id_usuario)) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'Nome, login e senha (para novos) são obrigatórios.'];
        } else {
            $pdo->beginTransaction();
            try {
                if ($id_usuario) { // Atualizar
                    if (empty($senha)) {
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome_completo = ?, login = ?, tipo_acesso = ?, ativo = ? WHERE id_usuario = ?");
                        $stmt->execute([$nome_completo, $login, $tipo_acesso, $ativo, $id_usuario]);
                    } else {
                        $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome_completo = ?, login = ?, senha = ?, tipo_acesso = ?, ativo = ? WHERE id_usuario = ?");
                        $stmt->execute([$nome_completo, $login, $hash_senha, $tipo_acesso, $ativo, $id_usuario]);
                    }
                } else { // Inserir
                    $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome_completo, login, senha, tipo_acesso, ativo) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$nome_completo, $login, $hash_senha, $tipo_acesso, $ativo]);
                    $id_usuario = $pdo->lastInsertId();
                }

                $stmt_delete_grupos = $pdo->prepare("DELETE FROM usuario_grupos WHERE id_usuario = ?");
                $stmt_delete_grupos->execute([$id_usuario]);
                if (!empty($grupos_usuario)) {
                    $stmt_insert_grupos = $pdo->prepare("INSERT INTO usuario_grupos (id_usuario, id_grupo) VALUES (?, ?)");
                    foreach ($grupos_usuario as $id_grupo) {
                        $stmt_insert_grupos->execute([$id_usuario, $id_grupo]);
                    }
                }
                $pdo->commit();
                $mensagem = ['tipo' => 'sucesso', 'texto' => 'Usuário salvo com sucesso!'];
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = ['tipo' => 'erro', 'texto' => 'Erro: o login informado já existe.'];
            }
        }
    }
    if (($_GET['acao'] ?? '') === 'excluir' && isset($_GET['id'])) {
        if ($_GET['id'] == $id_usuario_logado) {
            $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => 'Você não pode excluir seu próprio usuário.'];
        } else {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Usuário excluído.'];
        }
        header('Location: admin.php?tabela=usuarios');
        exit;
    }
    $usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nome_completo")->fetchAll();
    $grupos_select = $pdo->query("SELECT * FROM grupos ORDER BY nome")->fetchAll();
    if (($_GET['acao'] ?? '') === 'editar' && isset($_GET['id'])) {
        $stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
        $stmt_user->execute([$_GET['id']]);
        $usuario_para_editar = $stmt_user->fetch();
        $stmt_user_grupos = $pdo->prepare("SELECT id_grupo FROM usuario_grupos WHERE id_usuario = ?");
        $stmt_user_grupos->execute([$_GET['id']]);
        $grupos_do_usuario = $stmt_user_grupos->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .navbar { background-color: #343a40; padding: 15px 30px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .main-layout { display: flex; flex-grow: 1; }
        .sidebar { width: 220px; background-color: #fff; border-right: 1px solid #dee2e6; padding: 20px; }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar ul li a { display: block; padding: 10px 15px; color: #007bff; text-decoration: none; border-radius: 5px; font-weight: 500;}
        .sidebar ul li a.active { background-color: #007bff; color: white; }
        .content { flex-grow: 1; padding: 30px; }
        .content-box { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .mensagem { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
        .mensagem-sucesso { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .mensagem-erro { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="password"], input[type="number"], select { width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .sla-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: center; margin-bottom: 15px; }
        .sla-grid strong { font-size: 1em; color: #343a40; }
        .sla-grid label { font-size: 1.1em; font-weight: 500; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        .action-links a { margin-right: 15px; }
        .action-links a.delete { color: #dc3545; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
    </style>
</head>
<body>
    <nav class="navbar"><h1>Painel Admin</h1><a href="admin.php?acao=sair" class="btn-secondary">Sair</a></nav>
    <div class="main-layout">
        <aside class="sidebar">
            <h3>Gerenciar</h3>
            <ul>
                <li><a href="admin.php?tabela=sla" class="<?= $tabela_selecionada === 'sla' ? 'active' : '' ?>">SLA</a></li>
                <li><a href="admin.php?tabela=grupos" class="<?= $tabela_selecionada === 'grupos' ? 'active' : '' ?>">Grupos</a></li>
                <li><a href="admin.php?tabela=locais" class="<?= $tabela_selecionada === 'locais' ? 'active' : '' ?>">Locais</a></li>
                <li><a href="admin.php?tabela=servicos" class="<?= $tabela_selecionada === 'servicos' ? 'active' : '' ?>">Serviços</a></li>
                <li><a href="admin.php?tabela=usuarios" class="<?= $tabela_selecionada === 'usuarios' ? 'active' : '' ?>">Usuários</a></li>
            </ul>
        </aside>
        <main class="content">
            <?php if (!empty($mensagem)): ?><div class="mensagem mensagem-<?= htmlspecialchars($mensagem['tipo']) ?>"><?= htmlspecialchars($mensagem['texto']) ?></div><?php endif; ?>
            
            <?php if ($tabela_selecionada === 'sla'): ?>
            <div class="content-box">
                <h2>Configuração de SLA</h2>
                <p>Defina os intervalos de dias para cada nível de SLA.</p>
                <form action="admin.php?tabela=sla" method="POST"><?= csrf_campo() ?>
                    <div class="sla-grid"><strong>Nível</strong><strong>De (dias)</strong><strong>Até (dias)</strong></div><hr style="margin-bottom: 20px;">
                    <div class="sla-grid"><label>Normal</label><input type="number" name="normal_inicio" value="<?= $sla_config['Normal']['dias_inicio'] ?? 0 ?>" required><input type="number" name="normal_fim" value="<?= $sla_config['Normal']['dias_fim'] ?? 14 ?>" required></div>
                    <div class="sla-grid"><label>Atenção</label><input type="number" name="atencao_inicio" value="<?= $sla_config['Atenção']['dias_inicio'] ?? 15 ?>" required><input type="number" name="atencao_fim" value="<?= $sla_config['Atenção']['dias_fim'] ?? 30 ?>" required></div>
                    <div class="sla-grid"><label>Estourado</label><input type="number" name="estourado_inicio" value="<?= $sla_config['Estourado']['dias_inicio'] ?? 31 ?>" required><input type="number" name="estourado_fim" value="<?= $sla_config['Estourado']['dias_fim'] ?? 999 ?>" required></div>
                    <br><button type="submit" name="salvar_sla" class="btn btn-primary">Salvar Regras de SLA</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($tabela_selecionada === 'grupos'): ?>
            <div class="content-box">
                <h2>Gerenciar Grupos</h2>
                <form action="admin.php?tabela=grupos" method="POST"><?= csrf_campo() ?><input type="hidden" name="id_grupo" value="<?= $grupo_para_editar['id_grupo'] ?? '' ?>"><div class="form-group"><label><?= isset($grupo_para_editar) ? 'Editar Nome do Grupo' : 'Novo Grupo' ?></label><input type="text" name="nome" value="<?= htmlspecialchars($grupo_para_editar['nome'] ?? '') ?>" required autofocus></div><button type="submit" name="salvar_grupo" class="btn btn-primary">Salvar</button><?php if (isset($grupo_para_editar)): ?><a href="admin.php?tabela=grupos" class="btn btn-secondary">Cancelar</a><?php endif; ?></form>
                <hr style="margin: 30px 0;"><h3>Grupos Cadastrados</h3><table class="data-table"><thead><tr><th>ID</th><th>Nome</th><th>Ações</th></tr></thead><tbody><?php foreach ($grupos as $grupo): ?><tr><td><?= $grupo['id_grupo'] ?></td><td><?= htmlspecialchars($grupo['nome']) ?></td><td class="action-links"><a href="admin.php?tabela=grupos&acao=editar&id=<?= $grupo['id_grupo'] ?>">Editar</a><a href="admin.php?tabela=grupos&acao=excluir&id=<?= $grupo['id_grupo'] ?>&token=<?= csrf_token() ?>" class="delete" onclick="return confirm('Deseja excluir este grupo?');">Excluir</a></td></tr><?php endforeach; ?></tbody></table>
            </div>
            <?php endif; ?>

            <?php if ($tabela_selecionada === 'locais'): ?>
            <div class="content-box">
                <h2>Gerenciar Locais</h2>
                <form action="admin.php?tabela=locais" method="POST"><?= csrf_campo() ?><input type="hidden" name="id_local" value="<?= $local_para_editar['id_local'] ?? '' ?>"><div class="form-group"><label><?= isset($local_para_editar) ? 'Editar Nome do Local' : 'Novo Local' ?></label><input type="text" name="nome" value="<?= htmlspecialchars($local_para_editar['nome'] ?? '') ?>" required autofocus></div><button type="submit" name="salvar_local" class="btn btn-primary">Salvar</button><?php if (isset($local_para_editar)): ?><a href="admin.php?tabela=locais" class="btn btn-secondary">Cancelar</a><?php endif; ?></form>
                <hr style="margin: 30px 0;"><h3>Locais Cadastrados</h3><table class="data-table"><thead><tr><th>ID</th><th>Nome</th><th>Ações</th></tr></thead><tbody><?php foreach ($locais as $local): ?><tr><td><?= $local['id_local'] ?></td><td><?= htmlspecialchars($local['nome']) ?></td><td class="action-links"><a href="admin.php?tabela=locais&acao=editar&id=<?= $local['id_local'] ?>">Editar</a><a href="admin.php?tabela=locais&acao=excluir&id=<?= $local['id_local'] ?>&token=<?= csrf_token() ?>" class="delete" onclick="return confirm('Deseja excluir este local?');">Excluir</a></td></tr><?php endforeach; ?></tbody></table>
            </div>
            <?php endif; ?>

            <?php if ($tabela_selecionada === 'servicos'): ?>
            <div class="content-box">
                <h2>Gerenciar Serviços</h2>
                <form action="admin.php?tabela=servicos" method="POST"><?= csrf_campo() ?><input type="hidden" name="id_servico" value="<?= $servico_para_editar['id_servico'] ?? '' ?>"><div class="form-group"><label>Nome do Serviço</label><input type="text" name="nome" value="<?= htmlspecialchars($servico_para_editar['nome'] ?? '') ?>" required></div><div class="form-group"><label>Grupo de Trabalho Responsável</label><select name="id_grupo"><option value="">-- Nenhum --</option><?php foreach($grupos_select as $grupo): ?><option value="<?= $grupo['id_grupo'] ?>" <?= (isset($servico_para_editar) && $servico_para_editar['id_grupo'] == $grupo['id_grupo']) ? 'selected' : '' ?>><?= htmlspecialchars($grupo['nome']) ?></option><?php endforeach; ?></select></div><button type="submit" name="salvar_servico" class="btn btn-primary">Salvar</button><?php if (isset($servico_para_editar)): ?><a href="admin.php?tabela=servicos" class="btn btn-secondary">Cancelar</a><?php endif; ?></form>
                <hr style="margin: 30px 0;"><h3>Serviços Cadastrados</h3><table class="data-table"><thead><tr><th>ID</th><th>Nome</th><th>Grupo Responsável</th><th>Ações</th></tr></thead><tbody><?php foreach ($servicos as $servico): ?><tr><td><?= $servico['id_servico'] ?></td><td><?= htmlspecialchars($servico['nome']) ?></td><td><?= htmlspecialchars($servico['nome_grupo'] ?? 'N/A') ?></td><td class="action-links"><a href="admin.php?tabela=servicos&acao=editar&id=<?= $servico['id_servico'] ?>">Editar</a><a href="admin.php?tabela=servicos&acao=excluir&id=<?= $servico['id_servico'] ?>&token=<?= csrf_token() ?>" class="delete" onclick="return confirm('Deseja excluir este serviço?');">Excluir</a></td></tr><?php endforeach; ?></tbody></table>
            </div>
            <?php endif; ?>

            <?php if ($tabela_selecionada === 'usuarios'): ?>
            <div class="content-box">
                <h2>Gerenciar Usuários</h2>
                <form action="admin.php?tabela=usuarios" method="POST"><?= csrf_campo() ?><input type="hidden" name="id_usuario" value="<?= $usuario_para_editar['id_usuario'] ?? '' ?>"><div class="form-grid"><div class="form-group"><label>Nome Completo</label><input type="text" name="nome_completo" value="<?= htmlspecialchars($usuario_para_editar['nome_completo'] ?? '') ?>" required></div><div class="form-group"><label>Login</label><input type="text" name="login" value="<?= htmlspecialchars($usuario_para_editar['login'] ?? '') ?>" required></div><div class="form-group"><label>Senha</label><input type="password" name="senha" placeholder="<?= isset($usuario_para_editar) ? 'Deixe em branco para não alterar' : '' ?>"></div><div class="form-group"><label>Tipo de Acesso</label><select name="tipo_acesso" required><option value="admin" <?= (isset($usuario_para_editar) && $usuario_para_editar['tipo_acesso'] == 'admin') ? 'selected' : '' ?>>Admin</option><option value="gerencia" <?= (isset($usuario_para_editar) && $usuario_para_editar['tipo_acesso'] == 'gerencia') ? 'selected' : '' ?>>Gerência</option><option value="Geral" <?= (isset($usuario_para_editar) && $usuario_para_editar['tipo_acesso'] == 'Geral') ? 'selected' : '' ?>>Geral</option></select></div></div><div class="form-group"><label>Grupos de Trabalho</label><?php foreach($grupos_select as $grupo): ?><div class="checkbox-group"><input type="checkbox" name="grupos[]" id="grupo_<?= $grupo['id_grupo'] ?>" value="<?= $grupo['id_grupo'] ?>" <?= (isset($grupos_do_usuario) && in_array($grupo['id_grupo'], $grupos_do_usuario)) ? 'checked' : '' ?>><label for="grupo_<?= $grupo['id_grupo'] ?>"><?= htmlspecialchars($grupo['nome']) ?></label></div><?php endforeach; ?></div><div class="form-group checkbox-group"><input type="checkbox" name="ativo" id="ativo" value="1" <?= (!isset($usuario_para_editar) || $usuario_para_editar['ativo']) ? 'checked' : '' ?>><label for="ativo">Usuário Ativo</label></div><button type="submit" name="salvar_usuario" class="btn btn-primary">Salvar</button><?php if (isset($usuario_para_editar)): ?><a href="admin.php?tabela=usuarios" class="btn btn-secondary">Cancelar</a><?php endif; ?></form>
                <hr style="margin: 30px 0;"><h3>Usuários Cadastrados</h3><table class="data-table"><thead><tr><th>ID</th><th>Nome</th><th>Login</th><th>Tipo</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php foreach ($usuarios as $usuario): ?><tr><td><?= $usuario['id_usuario'] ?></td><td><?= htmlspecialchars($usuario['nome_completo']) ?></td><td><?= htmlspecialchars($usuario['login']) ?></td><td><?= ucfirst($usuario['tipo_acesso']) ?></td><td><?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?></td><td class="action-links"><a href="admin.php?tabela=usuarios&acao=editar&id=<?= $usuario['id_usuario'] ?>">Editar</a><?php if ($usuario['id_usuario'] != $id_usuario_logado): ?><a href="admin.php?tabela=usuarios&acao=excluir&id=<?= $usuario['id_usuario'] ?>&token=<?= csrf_token() ?>" class="delete" onclick="return confirm('Deseja excluir este usuário?');">Excluir</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
            </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>
