<?php
// Inicia a sessão. É fundamental que esta seja a primeira coisa no script.
session_start();

// Se o usuário já estiver logado, redireciona para a página de admin
if (isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_tipo_acesso']) && $_SESSION['usuario_tipo_acesso'] === 'admin') {
    header('Location: admin.php');
    exit;
}

require_once 'conexao.php';

$erro_login = '';

// Verifica se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim(filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING));
    $senha = $_POST['senha']; // Pega a senha sem sanitização para verificação

    if (empty($login) || empty($senha)) {
        $erro_login = 'Por favor, preencha o login e a senha.';
    } else {
        try {
            // Busca o usuário no banco de dados pelo login
            $sql = "SELECT id_usuario, nome_completo, login, senha, tipo_acesso FROM usuarios WHERE login = ? AND ativo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$login]);
            $usuario = $stmt->fetch();

            // Verifica se o usuário foi encontrado e se a senha está correta
            // password_verify() compara a senha digitada com o hash salvo no banco
           // $hashArmazenado = '$2y$10$At.aKcdueeccj5mvYvUabet7MEMZS9M.KPlvnV/kB4tlJRWtLA3.S'; // Substitua pelo hash real do seu banco de dados
           // $usuario['senha']
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                
                // Verifica se o usuário tem permissão de 'admin'
                if ($usuario['tipo_acesso'] === 'admin') {
                    // Login bem-sucedido! Armazena os dados na sessão
                    session_regenerate_id(true); // Previne fixação de sessão
                    $_SESSION['usuario_id'] = $usuario['id_usuario'];
                    $_SESSION['usuario_nome'] = $usuario['nome_completo'];
                    $_SESSION['usuario_login'] = $usuario['login'];
                    $_SESSION['usuario_tipo_acesso'] = $usuario['tipo_acesso'];

                    // Redireciona para a página de administração
                    header('Location: admin.php');
                    exit;
                } else {
                    // Usuário existe, mas não é administrador
                    $erro_login = 'Acesso negado. Você não tem permissão de administrador.';
                }

            } else {
                // Usuário não encontrado ou senha incorreta
                $erro_login = 'Login ou senha inválidos.';
            }

        } catch (PDOException $e) {
            $erro_login = 'Erro no sistema. Tente novamente mais tarde.';
            // Para depuração: error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Administração</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; box-sizing: border-box; }
        h1 { text-align: center; color: #0056b3; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em;
        }
        .btn {
            width: 100%; padding: 12px; background-color: #28a745; color: white;
            border: none; border-radius: 5px; font-size: 1.1em; font-weight: bold; cursor: pointer;
        }
        .btn:hover { background-color: #218838; }
        .mensagem-erro {
            color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb;
            padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Acesso Admin</h1>

        <?php if (!empty($erro_login)): ?>
            <div class="mensagem-erro"><?= htmlspecialchars($erro_login) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
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

