<?php
session_start();
require_once 'conexao.php';

$mensagem_sucesso = '';
$mensagem_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {
    $nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING));
    $telefone = trim(filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING));
    $id_local = filter_input(INPUT_POST, 'local', FILTER_VALIDATE_INT);
    $id_servico = filter_input(INPUT_POST, 'servico', FILTER_VALIDATE_INT);
    $prioridade = filter_input(INPUT_POST, 'prioridade', FILTER_SANITIZE_STRING);
    $descricao = trim(filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_STRING));
    $foto_path = null;

    if (empty($nome) || empty($telefone) || !$id_local || !$id_servico || empty($prioridade) || empty($descricao)) {
        $mensagem_erro = "Por favor, preencha todos os campos obrigatórios.";
    } else {
        $stmt_grupo = $pdo->prepare("SELECT id_grupo FROM servicos WHERE id_servico = ?");
        $stmt_grupo->execute([$id_servico]);
        $id_grupo_destino = $stmt_grupo->fetchColumn();

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
            $foto_tmp = $_FILES['foto']['tmp_name'];
            $foto_nome_original = $_FILES['foto']['name'];
            $extensao = strtolower(pathinfo($foto_nome_original, PATHINFO_EXTENSION));
            if (in_array($extensao, ['jpg', 'jpeg', 'png', 'gif'])) {
                $diretorio_uploads = 'uploads/';
                if (!is_dir($diretorio_uploads)) { mkdir($diretorio_uploads, 0777, true); }
                $foto_nome_novo = uniqid('foto_', true) . '.' . $extensao;
                $foto_path_completo = $diretorio_uploads . $foto_nome_novo;
                list($largura, $altura) = getimagesize($foto_tmp);
                $max_largura = 800; $max_altura = 600;
                $ratio = $largura / $altura;
                if ($max_largura / $max_altura > $ratio) { $max_largura = $max_altura * $ratio; } else { $max_altura = $max_largura / $ratio; }
                $imagem_redimensionada = imagecreatetruecolor($max_largura, $max_altura);
                switch($extensao) {
                    case 'jpg': case 'jpeg': $imagem_origem = imagecreatefromjpeg($foto_tmp); break;
                    case 'png': $imagem_origem = imagecreatefrompng($foto_tmp); break;
                    case 'gif': $imagem_origem = imagecreatefromgif($foto_tmp); break;
                }
                imagecopyresampled($imagem_redimensionada, $imagem_origem, 0, 0, 0, 0, $max_largura, $max_altura, $largura, $altura);
                if (imagejpeg($imagem_redimensionada, $foto_path_completo, 85)) { $foto_path = $foto_path_completo; }
                imagedestroy($imagem_origem); imagedestroy($imagem_redimensionada);
            } else { $mensagem_erro = "Formato de arquivo de imagem inválido."; }
        }

        if (empty($mensagem_erro)) {
            $pdo->beginTransaction();
            try {
                $sql_chamado = "INSERT INTO chamados (solicitante_nome, solicitante_telefone, id_local, id_servico, id_grupo, prioridade, descricao_problema, foto_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_chamado = $pdo->prepare($sql_chamado);
                $stmt_chamado->execute([$nome, $telefone, $id_local, $id_servico, $id_grupo_destino, $prioridade, $descricao, $foto_path]);
                $id_chamado = $pdo->lastInsertId();
                $sql_historico = "INSERT INTO chamado_historico (id_chamado, descricao_acao) VALUES (?, ?)";
                $stmt_historico = $pdo->prepare($sql_historico);
                $stmt_historico->execute([$id_chamado, 'Chamado aberto pelo solicitante.']);
                $pdo->commit();
                $_SESSION['sucesso'] = "Chamado nº <strong>{$id_chamado}</strong> aberto com sucesso!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem_erro = "Erro ao registrar o chamado.";
            }
        }
    }
}

if (isset($_SESSION['sucesso'])) {
    $mensagem_sucesso = $_SESSION['sucesso'];
    unset($_SESSION['sucesso']);
}

try {
    $locais = $pdo->query("SELECT id_local, nome FROM locais ORDER BY nome")->fetchAll();
    $servicos = $pdo->query("SELECT id_servico, nome FROM servicos ORDER BY nome")->fetchAll();
} catch (PDOException $e) {
    $locais = []; $servicos = [];
    $mensagem_erro = "Não foi possível carregar os dados do sistema.";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrir Chamado de Manutenção</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; background-color: #f4f4f9; color: #333; margin: 0; padding: 15px; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        h1 { color: #0056b3; font-size: 1.8em; margin: 0; }
        .btn-consultar { padding: 10px 15px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 5px; font-size: 0.9em; font-weight: bold; text-align: center; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="tel"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        textarea { resize: vertical; min-height: 120px; }
        .btn { display: block; width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 5px; font-size: 1.1em; font-weight: bold; cursor: pointer; text-align: center; }
        .mensagem { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
        .mensagem-sucesso { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .mensagem-erro { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .info-solicitante { background-color: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-actions">
            <h1>Abrir Solicitação</h1>
            <a id="btn-consultar" class="btn-consultar">Consultar Meus Chamados</a>
        </div>
        <?php if ($mensagem_sucesso): ?><div class="mensagem mensagem-sucesso"><?= $mensagem_sucesso ?></div><?php endif; ?>
        <?php if ($mensagem_erro): ?><div class="mensagem mensagem-erro"><?= $mensagem_erro ?></div><?php endif; ?>
        <form id="form-chamado" action="index.php" method="POST" enctype="multipart/form-data">

            <div class="info-solicitante">
                <div class="form-group">
                    <label for="nome">Seu Nome Completo *</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Seu Telefone (apenas números) *</label>
                    <input type="tel" id="telefone" name="telefone" required placeholder="11987654321">
                </div>
            </div>

            <div class="form-group">
                <label for="local">Local *</label>
                <select id="local" name="local" required>
                    <option value="">-- Selecione o Local --</option>
                    <?php foreach ($locais as $local): ?>
                        <option value="<?= htmlspecialchars($local['id_local']) ?>"><?= htmlspecialchars($local['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="servico">Serviço *</label>
                <select id="servico" name="servico" required>
                    <option value="">-- Selecione o Serviço --</option>
                    <?php foreach ($servicos as $servico): ?>
                        <option value="<?= htmlspecialchars($servico['id_servico']) ?>"><?= htmlspecialchars($servico['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="prioridade">Prioridade do Chamado *</label>
                <select id="prioridade" name="prioridade" required>
                    <option value="Baixa" selected>Baixa</option>
                    <option value="Média">Média</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>
            <div class="form-group">
                <label for="descricao">Descreva o problema *</label>
                <textarea id="descricao" name="descricao" required placeholder="Descreva com o máximo de detalhes o problema que você encontrou..."></textarea>
            </div>
            <div class="form-group">
                <label for="foto">Enviar uma foto (opcional)</label>
                <input type="file" id="foto" name="foto" accept="image/*">
            </div>
            <button type="submit" class="btn">Registrar Chamado</button>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('form-chamado');
            const nomeInput = document.getElementById('nome');
            const telefoneInput = document.getElementById('telefone');
            const consultarBtn = document.getElementById('btn-consultar');
            if (localStorage.getItem('solicitante_nome')) { nomeInput.value = localStorage.getItem('solicitante_nome'); }
            if (localStorage.getItem('solicitante_telefone')) { telefoneInput.value = localStorage.getItem('solicitante_telefone'); }
            form.addEventListener('submit', function() {
                localStorage.setItem('solicitante_nome', nomeInput.value);
                localStorage.setItem('solicitante_telefone', telefoneInput.value);
            });
            telefoneInput.addEventListener('input', function(e) { e.target.value = e.target.value.replace(/\D/g, ''); });
            consultarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const telefone = telefoneInput.value;
                let url = 'consulta_chamado.php';
                if (telefone.trim() !== '') { url += `?telefone=${encodeURIComponent(telefone.trim())}`; }
                window.location.href = url;
            });
        });
    </script>
</body>
</html>
