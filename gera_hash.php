<?php
$hash_gerado = '';
$senha_digitada = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    $senha_digitada = $_POST['senha'];
    if (!empty($senha_digitada)) {
        // Gera o hash da senha usando o algoritmo padrão e seguro do PHP
        $hash_gerado = password_hash($senha_digitada, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Hash de Senha</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh;}
        .container { max-width: 600px; width: 100%; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0056b3; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 5px; font-size: 1em; cursor: pointer; }
        .resultado { margin-top: 20px; background-color: #e9ecef; padding: 15px; border-radius: 5px; border: 1px solid #ced4da; word-wrap: break-word; }
        .resultado strong { display: block; margin-bottom: 10px; color: #495057; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerador de Hash Seguro para Senha</h1>
        <p>Use esta ferramenta para criar um hash seguro para a senha do seu usuário admin.</p>
        <form action="" method="POST">
            <div class="form-group">
                <label for="senha">Digite a nova senha:</label>
                <input type="text" id="senha" name="senha" value="<?= htmlspecialchars($senha_digitada) ?>" required autofocus>
            </div>
            <button type="submit" class="btn">Gerar Hash</button>
        </form>

        <?php if ($hash_gerado): ?>
            <div class="resultado">
                <strong>Hash Gerado:</strong>
                <code><?= htmlspecialchars($hash_gerado) ?></code>
                <p style="margin-top: 15px;"><strong>Instrução:</strong> Copie este código e cole no campo <strong>`senha`</strong> da tabela <strong>`usuarios`</strong> para o seu usuário `admin`.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
