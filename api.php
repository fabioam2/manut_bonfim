<?php
/**
 * api.php
 * Este script funciona como um endpoint para requisições AJAX (fetch) do front-end.
 * Ele busca dados no banco de dados e os retorna em formato JSON.
 */

// Inclui o arquivo de conexão com o banco de dados.
// O @ suprime erros caso o arquivo não seja encontrado, mas o die() logo abaixo trata isso.
@require_once 'conexao.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha crítica: não foi possível conectar ao banco de dados.']);
    exit;
}

// Define o cabeçalho da resposta para indicar que o conteúdo é JSON.
header('Content-Type: application/json');

// --- ROTEAMENTO SIMPLES BASEADO EM PARÂMETROS GET ---

// Rota para buscar os LOCAIS de um determinado PRÉDIO
if (isset($_GET['get_locais'])) {
    $id_predio = filter_input(INPUT_GET, 'get_locais', FILTER_VALIDATE_INT);

    if ($id_predio === false || $id_predio <= 0) {
        http_response_code(400); // Bad Request
        echo json_encode(['erro' => 'ID de prédio inválido.']);
        exit;
    }

    try {
        $sql = "SELECT id_local, nome FROM locais WHERE id_predio = ? ORDER BY nome ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_predio]);
        $locais = $stmt->fetchAll();
        
        // Retorna os dados dos locais em formato JSON
        echo json_encode($locais);

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['erro' => 'Erro ao buscar locais no banco de dados.']);
        // Em ambiente de desenvolvimento, logar o erro: error_log($e->getMessage());
    }
    exit; // Encerra o script após a execução da rota
}

// Rota para buscar os SERVIÇOS de um determinado LOCAL
if (isset($_GET['get_servicos'])) {
    $id_local = filter_input(INPUT_GET, 'get_servicos', FILTER_VALIDATE_INT);

    if ($id_local === false || $id_local <= 0) {
        http_response_code(400); // Bad Request
        echo json_encode(['erro' => 'ID de local inválido.']);
        exit;
    }

    try {
        // A consulta usa JOIN para buscar os nomes dos serviços associados ao local
        $sql = "SELECT s.id_servico, s.nome 
                FROM servicos s
                INNER JOIN locais_servicos ls ON s.id_servico = ls.id_servico
                WHERE ls.id_local = ?
                ORDER BY s.nome ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_local]);
        $servicos = $stmt->fetchAll();

        // Retorna os dados dos serviços em formato JSON
        echo json_encode($servicos);

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['erro' => 'Erro ao buscar serviços no banco de dados.']);
        // Em ambiente de desenvolvimento, logar o erro: error_log($e->getMessage());
    }
    exit; // Encerra o script após a execução da rota
}


// --- Resposta Padrão ---
// Se nenhuma rota válida foi acionada, retorna um erro de requisição inválida.
http_response_code(400);
echo json_encode(['erro' => 'Requisição inválida. Nenhum parâmetro válido foi fornecido.']);

?>
