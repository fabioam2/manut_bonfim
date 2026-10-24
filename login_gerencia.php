<?php
require_once 'seguranca.php';
iniciar_sessao();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
}

// Se o usuário já estiver logado, redireciona para a página de gerência
if (isset($_SESSION['usuario_id']) && in_array($_SESSION['usuario_tipo_acesso'], ['gerencia', 'admin'])) {
    header('Location: gerencia.php');
    exit;
}

require_once 'conexao.php';

$erro_login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string) filter_input(INPUT_POST, 'login'));
    $senha = $_POST['senha'];

    if (empty($login) || empty($senha)) {
        $erro_login = 'Por favor, preencha o login e a senha.';
    } else {
        try {
            $sql = "SELECT id_usuario, nome_completo, login, senha, tipo_acesso FROM usuarios WHERE login = ? AND ativo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$login]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Verifica se o usuário tem permissão de 'gerencia' ou 'admin'
                if (in_array($usuario['tipo_acesso'], ['gerencia', 'admin'])) {
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id_usuario'];
                    $_SESSION['usuario_nome'] = $usuario['nome_completo'];
                    $_SESSION['usuario_login'] = $usuario['login'];
                    $_SESSION['usuario_tipo_acesso'] = $usuario['tipo_acesso'];

                    header('Location: gerencia.php');
                    exit;
                } else {
                    $erro_login = 'Acesso negado. Você não tem permissão para acessar esta área.';
                }
            } else {
                $erro_login = 'Login ou senha inválidos.';
            }
        } catch (PDOException $e) {
            $erro_login = 'Erro no sistema. Tente novamente mais tarde.';
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gerência de Chamados</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; box-sizing: border-box; }
        h1 { text-align: center; color: #0056b3; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        .btn { width: 100%; padding: 12px; background-color: #ffc107; color: #212529; border: none; border-radius: 5px; font-size: 1.1em; font-weight: bold; cursor: pointer; }
        .btn:hover { background-color: #e0a800; }
        .mensagem-erro { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Gerência de Chamados</h1>
        <?php if (!empty($erro_login)): ?>
            <div class="mensagem-erro"><?= htmlspecialchars($erro_login) ?></div>
        <?php endif; ?>
        <form action="login_gerencia.php" method="POST"><?= csrf_campo() ?>
            <div class="form-group">
                <label for="login">Usuário</label>
                <input type="text" id="login" name="login" required autofocus>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn">Entrar</button>
        </form>
    </div>
</body>
</html>
