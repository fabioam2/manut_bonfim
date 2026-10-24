<?php
/**
 * Arquivo de conexão com o banco de dados utilizando PDO.
 * Este arquivo deve ser incluído em todas as páginas que precisam de acesso ao banco.
 *
 * As credenciais ficam em config.php (fora do controle de versão).
 * Copie config.example.php para config.php e preencha com os dados do seu servidor.
 */

$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    die("Arquivo config.php não encontrado. Copie config.example.php para config.php e configure o banco de dados.");
}
$config = require $config_file;

// --- Configurações do Banco de Dados ---
$db_host = $config['db_host'];
$db_name = $config['db_name'];
$db_user = $config['db_user'];
$db_pass = $config['db_pass'];
$db_charset = $config['db_charset'] ?? 'utf8mb4';

// --- DSN (Data Source Name) ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// --- Opções do PDO ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erros
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna os resultados como arrays associativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desativa a emulação de prepared statements para segurança
];

// --- Tentativa de Conexão ---
try {
    // Cria a instância do PDO para conectar ao banco de dados.
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // Se a conexão falhar, exibe uma mensagem de erro genérica e encerra o script.
    error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
    die("Erro ao conectar com o banco de dados. Por favor, tente novamente mais tarde.");
}

// A variável $pdo está disponível e contém a conexão ativa.
?>
