<?php
// Inicia a sessão
session_start();

// --- CONTROLE DE ACESSO ---
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo_acesso'] !== 'admin') {
    session_destroy();
    header('Location: login.php?erro=acesso_negado');
    exit;
}

// Inclui a conexão com o banco de dados
require_once 'conexao.php';

// --- LÓGICA DE NAVEGAÇÃO E CRUD ---
$nome_usuario = $_SESSION['usuario_nome'];
$id_usuario_logado = $_SESSION['usuario_id'];
$tabela_selecionada = $_GET['tabela'] ?? 'predios'; // Padrão para prédios se nada for selecionado
$mensagem = []; // Para feedback ao usuário

// --- LÓGICA DE LOGOUT ---
if (isset($_GET['acao']) && $_GET['acao'] === 'sair') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Recupera mensagem da sessão (após redirect)
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    unset($_SESSION['mensagem']);
}

// --- LÓGICA PARA GERENCIAR PRÉDIOS ---
if ($tabela_selecionada === 'predios') {
    // ... (código de gerenciamento de prédios já existente e funcional) ...
    // PROCESSAR FORMULÁRIO (ADICIONAR/EDITAR)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_predio'])) {
        $nome_predio = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING));
        $id_predio = filter_input(INPUT_POST, 'id_predio', FILTER_VALIDATE_INT);

        if (empty($nome_predio)) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'O nome do prédio não pode ser vazio.'];
        } else {
            try {
                if ($id_predio) { // Atualizar
                    $stmt = $pdo->prepare("UPDATE predios SET nome = ? WHERE id_predio = ?");
                    $stmt->execute([$nome_predio, $id_predio]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Prédio atualizado!'];
                } else { // Inserir
                    $stmt = $pdo->prepare("INSERT INTO predios (nome) VALUES (?)");
                    $stmt->execute([$nome_predio]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Prédio cadastrado!'];
                }
            } catch (PDOException $e) {
                $mensagem = ['tipo' => 'erro', 'texto' => ($e->getCode() == 23000) ? 'Já existe um prédio com este nome.' : 'Erro ao salvar.'];
            }
        }
    }
    // PROCESSAR EXCLUSÃO
    if (($_GET['acao'] ?? '') === 'excluir' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM predios WHERE id_predio = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Prédio e seus locais associados foram excluídos.'];
        } catch (PDOException $e) {
            $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => 'Erro ao excluir. Verifique as dependências.'];
        }
        header('Location: admin.php?tabela=predios');
        exit;
    }
    // BUSCAR DADOS
    $predios = $pdo->query("SELECT * FROM predios ORDER BY nome")->fetchAll();
    if (($_GET['acao'] ?? '') === 'editar' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM predios WHERE id_predio = ?");
        $stmt->execute([$_GET['id']]);
        $predio_para_editar = $stmt->fetch();
    }
}

// --- LÓGICA PARA GERENCIAR LOCAIS ---
if ($tabela_selecionada === 'locais') {
    // PROCESSAR FORMULÁRIO
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_local'])) {
        $id_local = filter_input(INPUT_POST, 'id_local', FILTER_VALIDATE_INT);
        $id_predio = filter_input(INPUT_POST, 'id_predio', FILTER_VALIDATE_INT);
        $nome_local = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING));

        if (empty($nome_local) || !$id_predio) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'Todos os campos são obrigatórios.'];
        } else {
            try {
                if ($id_local) { // Atualizar
                    $stmt = $pdo->prepare("UPDATE locais SET id_predio = ?, nome = ? WHERE id_local = ?");
                    $stmt->execute([$id_predio, $nome_local, $id_local]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Local atualizado!'];
                } else { // Inserir
                    $stmt = $pdo->prepare("INSERT INTO locais (id_predio, nome) VALUES (?, ?)");
                    $stmt->execute([$id_predio, $nome_local]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Local cadastrado!'];
                }
            } catch (PDOException $e) {
                $mensagem = ['tipo' => 'erro', 'texto' => 'Erro ao salvar o local.'];
            }
        }
    }
    // PROCESSAR EXCLUSÃO
    if (($_GET['acao'] ?? '') === 'excluir' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("DELETE FROM locais WHERE id_local = ?");
        $stmt->execute([$_GET['id']]);
        $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Local excluído.'];
        header('Location: admin.php?tabela=locais');
        exit;
    }
    // BUSCAR DADOS
    $locais = $pdo->query("SELECT l.*, p.nome as nome_predio FROM locais l JOIN predios p ON l.id_predio = p.id_predio ORDER BY p.nome, l.nome")->fetchAll();
    $predios_select = $pdo->query("SELECT id_predio, nome FROM predios ORDER BY nome")->fetchAll();
    if (($_GET['acao'] ?? '') === 'editar' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM locais WHERE id_local = ?");
        $stmt->execute([$_GET['id']]);
        $local_para_editar = $stmt->fetch();
    }
}

// --- LÓGICA PARA GERENCIAR SERVIÇOS ---
if ($tabela_selecionada === 'servicos') {
    // PROCESSAR FORMULÁRIO
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_servico'])) {
        $id_servico = filter_input(INPUT_POST, 'id_servico', FILTER_VALIDATE_INT);
        $nome_servico = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING));
        $locais_associados = $_POST['locais'] ?? [];

        if (empty($nome_servico)) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'O nome do serviço é obrigatório.'];
        } else {
            $pdo->beginTransaction();
            try {
                if ($id_servico) { // Atualizar
                    $stmt = $pdo->prepare("UPDATE servicos SET nome = ? WHERE id_servico = ?");
                    $stmt->execute([$nome_servico, $id_servico]);
                } else { // Inserir
                    $stmt = $pdo->prepare("INSERT INTO servicos (nome) VALUES (?)");
                    $stmt->execute([$nome_servico]);
                    $id_servico = $pdo->lastInsertId();
                }
                // Gerenciar associações
                $stmt_delete = $pdo->prepare("DELETE FROM locais_servicos WHERE id_servico = ?");
                $stmt_delete->execute([$id_servico]);
                if (!empty($locais_associados)) {
                    $stmt_insert = $pdo->prepare("INSERT INTO locais_servicos (id_local, id_servico) VALUES (?, ?)");
                    foreach ($locais_associados as $id_local) {
                        $stmt_insert->execute([$id_local, $id_servico]);
                    }
                }
                $pdo->commit();
                $mensagem = ['tipo' => 'sucesso', 'texto' => 'Serviço salvo com sucesso!'];
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = ['tipo' => 'erro', 'texto' => ($e->getCode() == 23000) ? 'Já existe um serviço com este nome.' : 'Erro ao salvar o serviço.'];
            }
        }
    }
    // PROCESSAR EXCLUSÃO
    if (($_GET['acao'] ?? '') === 'excluir' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("DELETE FROM servicos WHERE id_servico = ?");
        $stmt->execute([$_GET['id']]);
        $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Serviço excluído.'];
        header('Location: admin.php?tabela=servicos');
        exit;
    }
    // BUSCAR DADOS
    $servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome")->fetchAll();
    if (($_GET['acao'] ?? '') === 'editar' && isset($_GET['id'])) {
        $id_servico_edit = $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id_servico = ?");
        $stmt->execute([$id_servico_edit]);
        $servico_para_editar = $stmt->fetch();
        // Buscar locais já associados a este serviço
        $stmt_locais = $pdo->prepare("SELECT id_local FROM locais_servicos WHERE id_servico = ?");
        $stmt_locais->execute([$id_servico_edit]);
        $locais_ja_associados = $stmt_locais->fetchAll(PDO::FETCH_COLUMN);
    }
    $locais_select = $pdo->query("SELECT l.id_local, l.nome, p.nome as nome_predio FROM locais l JOIN predios p ON l.id_predio = p.id_predio ORDER BY p.nome, l.nome")->fetchAll();
}

// --- LÓGICA PARA GERENCIAR USUÁRIOS ---
if ($tabela_selecionada === 'usuarios') {
    // PROCESSAR FORMULÁRIO
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_usuario'])) {
        $id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
        $nome_completo = trim(filter_input(INPUT_POST, 'nome_completo', FILTER_SANITIZE_STRING));
        $login = trim(filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING));
        $senha = $_POST['senha'];
        $tipo_acesso = $_POST['tipo_acesso'];
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (empty($nome_completo) || empty($login) || (empty($senha) && !$id_usuario)) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'Nome, login e senha (para novos usuários) são obrigatórios.'];
        } else {
            try {
                if ($id_usuario) { // Atualizar
                    if (empty($senha)) { // Não atualiza a senha
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome_completo = ?, login = ?, tipo_acesso = ?, ativo = ? WHERE id_usuario = ?");
                        $stmt->execute([$nome_completo, $login, $tipo_acesso, $ativo, $id_usuario]);
                    } else { // Atualiza a senha
                        $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome_completo = ?, login = ?, senha = ?, tipo_acesso = ?, ativo = ? WHERE id_usuario = ?");
                        $stmt->execute([$nome_completo, $login, $hash_senha, $tipo_acesso, $ativo, $id_usuario]);
                    }
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Usuário atualizado!'];
                } else { // Inserir
                    $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome_completo, login, senha, tipo_acesso, ativo) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$nome_completo, $login, $hash_senha, $tipo_acesso, $ativo]);
                    $mensagem = ['tipo' => 'sucesso', 'texto' => 'Usuário cadastrado!'];
                }
            } catch (PDOException $e) {
                $mensagem = ['tipo' => 'erro', 'texto' => ($e->getCode() == 23000) ? 'O login informado já existe.' : 'Erro ao salvar o usuário.'];
            }
        }
    }
    // PROCESSAR EXCLUSÃO (DESATIVAÇÃO)
    if (($_GET['acao'] ?? '') === 'excluir' && isset($_GET['id'])) {
        $id_usuario_del = $_GET['id'];
        if ($id_usuario_del == $id_usuario_logado) {
            $_SESSION['mensagem'] = ['tipo' => 'erro', 'texto' => 'Você não pode excluir seu próprio usuário.'];
        } else {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_usuario_del]);
            $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Usuário excluído.'];
        }
        header('Location: admin.php?tabela=usuarios');
        exit;
    }
    // BUSCAR DADOS
    $usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nome_completo")->fetchAll();
    if (($_GET['acao'] ?? '') === 'editar' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$_GET['id']]);
        $usuario_para_editar = $stmt->fetch();
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
        .navbar { background-color: #343a40; padding: 15px 30px; color: white; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .navbar h1 { margin: 0; font-size: 1.5em; }
        .navbar a.logout-btn { color: #f8f9fa; text-decoration: none; background-color: #dc3545; padding: 8px 15px; border-radius: 5px; font-weight: 500;}
        .navbar a.logout-btn:hover { background-color: #c82333; }
        .main-layout { display: flex; flex-grow: 1; }
        .sidebar { width: 220px; background-color: #fff; border-right: 1px solid #dee2e6; padding: 20px; flex-shrink: 0; }
        .sidebar h3 { margin-top: 0; color: #495057; }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar ul li a { display: block; padding: 10px 15px; color: #007bff; text-decoration: none; border-radius: 5px; font-weight: 500;}
        .sidebar ul li a:hover { background-color: #e9ecef; }
        .sidebar ul li a.active { background-color: #007bff; color: white; }
        .content { flex-grow: 1; padding: 30px; }
        .content-box { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        h2 { color: #333; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .mensagem { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
        .mensagem-sucesso { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .mensagem-erro { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="password"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
        .locais-list { max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; border-radius: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; font-size: 1em; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        .data-table th { background-color: #f8f9fa; }
        .action-links a { color: #007bff; text-decoration: none; margin-right: 15px; }
        .action-links a.delete { color: #dc3545; }
    </style>
</head>
<body>

    <nav class="navbar">
        <h1>Painel Admin</h1>
        <a href="admin.php?acao=sair" class="logout-btn">Sair</a>
    </nav>

    <div class="main-layout">
        <aside class="sidebar">
            <h3>Gerenciar</h3>
            <ul>
                <li><a href="admin.php?tabela=predios" class="<?= $tabela_selecionada === 'predios' ? 'active' : '' ?>">Prédios</a></li>
                <li><a href="admin.php?tabela=locais" class="<?= $tabela_selecionada === 'locais' ? 'active' : '' ?>">Locais</a></li>
                <li><a href="admin.php?tabela=servicos" class="<?= $tabela_selecionada === 'servicos' ? 'active' : '' ?>">Serviços</a></li>
                <li><a href="admin.php?tabela=usuarios" class="<?= $tabela_selecionada === 'usuarios' ? 'active' : '' ?>">Usuários</a></li>
            </ul>
        </aside>

        <main class="content">
            <?php if (!empty($mensagem)): ?>
                <div class="mensagem mensagem-<?= htmlspecialchars($mensagem['tipo']) ?>"><?= htmlspecialchars($mensagem['texto']) ?></div>
            <?php endif; ?>

            <!-- SEÇÃO PRÉDIOS -->
            <?php if ($tabela_selecionada === 'predios'): ?>
                <div class="content-box">
                    <h2>Gerenciar Prédios</h2>
                    <form action="admin.php?tabela=predios" method="POST">
                        <input type="hidden" name="id_predio" value="<?= $predio_para_editar['id_predio'] ?? '' ?>">
                        <div class="form-group">
                            <label for="nome"><?= isset($predio_para_editar) ? 'Editar Nome do Prédio' : 'Novo Prédio' ?></label>
                            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($predio_para_editar['nome'] ?? '') ?>" required autofocus>
                        </div>
                        <button type="submit" name="salvar_predio" class="btn btn-primary"><?= isset($predio_para_editar) ? 'Salvar' : 'Adicionar' ?></button>
                        <?php if (isset($predio_para_editar)): ?>
                            <a href="admin.php?tabela=predios" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </form>
                    <hr style="margin: 30px 0;">
                    <h3>Prédios Cadastrados</h3>
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Nome</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($predios as $predio): ?>
                            <tr>
                                <td><?= $predio['id_predio'] ?></td>
                                <td><?= htmlspecialchars($predio['nome']) ?></td>
                                <td class="action-links">
                                    <a href="admin.php?tabela=predios&acao=editar&id=<?= $predio['id_predio'] ?>">Editar</a>
                                    <a href="admin.php?tabela=predios&acao=excluir&id=<?= $predio['id_predio'] ?>" class="delete" onclick="return confirm('Atenção! Excluir um prédio também removerá todos os seus locais e associações. Deseja continuar?');">Excluir</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- SEÇÃO LOCAIS -->
            <?php if ($tabela_selecionada === 'locais'): ?>
                 <div class="content-box">
                    <h2>Gerenciar Locais</h2>
                    <form action="admin.php?tabela=locais" method="POST">
                        <input type="hidden" name="id_local" value="<?= $local_para_editar['id_local'] ?? '' ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome">Nome do Local</label>
                                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($local_para_editar['nome'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="id_predio">Prédio</label>
                                <select name="id_predio" id="id_predio" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach($predios_select as $predio): ?>
                                    <option value="<?= $predio['id_predio'] ?>" <?= (isset($local_para_editar) && $local_para_editar['id_predio'] == $predio['id_predio']) ? 'selected' : '' ?>><?= htmlspecialchars($predio['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="salvar_local" class="btn btn-primary"><?= isset($local_para_editar) ? 'Salvar' : 'Adicionar' ?></button>
                        <?php if (isset($local_para_editar)): ?><a href="admin.php?tabela=locais" class="btn btn-secondary">Cancelar</a><?php endif; ?>
                    </form>
                    <hr style="margin: 30px 0;">
                    <h3>Locais Cadastrados</h3>
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Nome</th><th>Prédio</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($locais as $local): ?>
                            <tr>
                                <td><?= $local['id_local'] ?></td>
                                <td><?= htmlspecialchars($local['nome']) ?></td>
                                <td><?= htmlspecialchars($local['nome_predio']) ?></td>
                                <td class="action-links">
                                    <a href="admin.php?tabela=locais&acao=editar&id=<?= $local['id_local'] ?>">Editar</a>
                                    <a href="admin.php?tabela=locais&acao=excluir&id=<?= $local['id_local'] ?>" class="delete" onclick="return confirm('Deseja realmente excluir este local?');">Excluir</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- SEÇÃO SERVIÇOS -->
            <?php if ($tabela_selecionada === 'servicos'): ?>
                 <div class="content-box">
                    <h2>Gerenciar Serviços</h2>
                    <form action="admin.php?tabela=servicos" method="POST">
                        <input type="hidden" name="id_servico" value="<?= $servico_para_editar['id_servico'] ?? '' ?>">
                        <div class="form-group">
                            <label for="nome">Nome do Serviço</label>
                            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($servico_para_editar['nome'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Disponível nos Locais (selecione um ou mais)</label>
                            <div class="locais-list">
                                <?php foreach($locais_select as $local): ?>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="locais[]" id="local_<?= $local['id_local'] ?>" value="<?= $local['id_local'] ?>" <?= (isset($locais_ja_associados) && in_array($local['id_local'], $locais_ja_associados)) ? 'checked' : '' ?>>
                                    <label for="local_<?= $local['id_local'] ?>"><?= htmlspecialchars($local['nome_predio']) ?> - <?= htmlspecialchars($local['nome']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" name="salvar_servico" class="btn btn-primary"><?= isset($servico_para_editar) ? 'Salvar' : 'Adicionar' ?></button>
                        <?php if (isset($servico_para_editar)): ?><a href="admin.php?tabela=servicos" class="btn btn-secondary">Cancelar</a><?php endif; ?>
                    </form>
                    <hr style="margin: 30px 0;">
                    <h3>Serviços Cadastrados</h3>
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Nome</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($servicos as $servico): ?>
                            <tr>
                                <td><?= $servico['id_servico'] ?></td>
                                <td><?= htmlspecialchars($servico['nome']) ?></td>
                                <td class="action-links">
                                    <a href="admin.php?tabela=servicos&acao=editar&id=<?= $servico['id_servico'] ?>">Editar</a>
                                    <a href="admin.php?tabela=servicos&acao=excluir&id=<?= $servico['id_servico'] ?>" class="delete" onclick="return confirm('Deseja realmente excluir este serviço?');">Excluir</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- SEÇÃO USUÁRIOS -->
            <?php if ($tabela_selecionada === 'usuarios'): ?>
                 <div class="content-box">
                    <h2>Gerenciar Usuários</h2>
                    <form action="admin.php?tabela=usuarios" method="POST">
                        <input type="hidden" name="id_usuario" value="<?= $usuario_para_editar['id_usuario'] ?? '' ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome_completo">Nome Completo</label>
                                <input type="text" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($usuario_para_editar['nome_completo'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="login">Login</label>
                                <input type="text" id="login" name="login" value="<?= htmlspecialchars($usuario_para_editar['login'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="senha">Senha</label>
                                <input type="password" id="senha" name="senha" placeholder="<?= isset($usuario_para_editar) ? 'Deixe em branco para não alterar' : '' ?>">
                            </div>
                            <div class="form-group">
                                <label for="tipo_acesso">Tipo de Acesso</label>
                                <select name="tipo_acesso" id="tipo_acesso" required>
                                    <option value="gerencia" <?= (isset($usuario_para_editar) && $usuario_para_editar['tipo_acesso'] == 'gerencia') ? 'selected' : '' ?>>Gerência</option>
                                    <option value="admin" <?= (isset($usuario_para_editar) && $usuario_para_editar['tipo_acesso'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                         <div class="form-group checkbox-group">
                            <input type="checkbox" name="ativo" id="ativo" value="1" <?= (isset($usuario_para_editar) && $usuario_para_editar['ativo']) || !isset($usuario_para_editar) ? 'checked' : '' ?>>
                            <label for="ativo">Usuário Ativo</label>
                        </div>
                        <button type="submit" name="salvar_usuario" class="btn btn-primary"><?= isset($usuario_para_editar) ? 'Salvar' : 'Adicionar' ?></button>
                        <?php if (isset($usuario_para_editar)): ?><a href="admin.php?tabela=usuarios" class="btn btn-secondary">Cancelar</a><?php endif; ?>
                    </form>
                    <hr style="margin: 30px 0;">
                    <h3>Usuários Cadastrados</h3>
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Nome</th><th>Login</th><th>Tipo</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?= $usuario['id_usuario'] ?></td>
                                <td><?= htmlspecialchars($usuario['nome_completo']) ?></td>
                                <td><?= htmlspecialchars($usuario['login']) ?></td>
                                <td><?= ucfirst($usuario['tipo_acesso']) ?></td>
                                <td><?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?></td>
                                <td class="action-links">
                                    <a href="admin.php?tabela=usuarios&acao=editar&id=<?= $usuario['id_usuario'] ?>">Editar</a>
                                    <?php if ($usuario['id_usuario'] != $id_usuario_logado): ?>
                                    <a href="admin.php?tabela=usuarios&acao=excluir&id=<?= $usuario['id_usuario'] ?>" class="delete" onclick="return confirm('Deseja realmente excluir este usuário?');">Excluir</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </main>
    </div>

</body>
</html>
