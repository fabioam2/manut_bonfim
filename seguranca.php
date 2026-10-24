<?php
/**
 * Funções de segurança compartilhadas: sessão protegida e tokens CSRF.
 * Incluir no topo de toda página que usa sessão, no lugar de session_start().
 */

/**
 * Inicia a sessão com cookie protegido (HttpOnly, SameSite e Secure quando em HTTPS).
 */
function iniciar_sessao() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,      // JavaScript não consegue ler o cookie de sessão
        'samesite' => 'Lax',     // Navegador não envia o cookie em POSTs vindos de outros sites
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/**
 * Retorna o token CSRF da sessão, criando-o se necessário.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto com o token CSRF, para colocar dentro de todo <form method="POST">.
 */
function csrf_campo() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Confere o token enviado (POST ou, para links de ação, GET). Encerra a requisição se for inválido.
 */
function csrf_verificar() {
    $enviado = $_POST['csrf_token'] ?? $_GET['token'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        die('Requisição inválida ou expirada. Volte, recarregue a página e tente novamente.');
    }
}
