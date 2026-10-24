# Manutenção Bonfim — Sistema de Chamados

Sistema web em PHP + MySQL para abertura e acompanhamento de chamados de manutenção.

## Funcionalidades

- **Abertura de chamado** (`index.php`): solicitante informa nome, telefone, local, serviço, prioridade, descrição e foto (redimensionada automaticamente).
- **Consulta de chamado** (`consulta_chamado.php`, `chamado_detalhe.php`): acompanhamento do status e histórico.
- **Painel administrativo** (`login.php` → `admin.php`): gestão de chamados, usuários, locais, serviços, grupos e SLA.
- **Painel da gerência** (`login_gerencia.php` → `gerencia.php`).
- **API** (`api.php`): endpoints auxiliares usados pelas páginas.
- **Gerador de hash** (`gera_hash.php`): utilitário para gerar o hash de senha de um usuário. Remova-o do servidor de produção após o uso.

## Requisitos

- PHP 7.4+ com extensões `pdo_mysql` e `gd`
- MySQL / MariaDB

## Instalação

1. Copie os arquivos para a pasta pública do servidor (ex.: `public_html`).
2. Crie o banco e rode o script `database/schema.sql`.
3. Copie `config.example.php` para `config.php` e preencha as credenciais do banco.
4. Garanta que a pasta `uploads/` tenha permissão de escrita pelo servidor web.

> `config.php` e o conteúdo de `uploads/` não são versionados (veja `.gitignore`).
