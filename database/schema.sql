-- Script para criação da estrutura simplificada do banco de dados
-- Banco de Dados: u154867272_manut2

-- Garante que estamos usando o banco de dados correto.
USE u154867272_manut2;

-- --- APAGUE AS TABELAS ANTIGAS ANTES DE EXECUTAR ---
-- DROP TABLE IF EXISTS chamado_historico, chamados, servicos, locais, usuarios;


-- Tabela para armazenar os usuários do sistema (Admin e Gerência)
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` INT AUTO_INCREMENT PRIMARY KEY,
  `nome_completo` VARCHAR(150) NOT NULL,
  `login` VARCHAR(50) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `tipo_acesso` ENUM('admin', 'gerencia') NOT NULL,
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para os locais (ex: Sala, Setor, Andar)
CREATE TABLE IF NOT EXISTS `locais` (
  `id_local` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para os tipos de serviços disponíveis
CREATE TABLE IF NOT EXISTS `servicos` (
  `id_servico` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela principal para registrar os chamados de manutenção
CREATE TABLE IF NOT EXISTS `chamados` (
  `id_chamado` INT AUTO_INCREMENT PRIMARY KEY,
  `solicitante_nome` VARCHAR(150) NOT NULL,
  `solicitante_telefone` VARCHAR(20) NOT NULL,
  `id_local` INT NOT NULL,
  `id_servico` INT NOT NULL,
  `data_abertura` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('Aberto', 'Em Atendimento', 'Aguardando Peça', 'Finalizado', 'Cancelado') NOT NULL DEFAULT 'Aberto',
  `prioridade` ENUM('Baixa', 'Média', 'Alta') NOT NULL DEFAULT 'Baixa',
  `id_usuario_atribuido` INT NULL,
  `descricao_problema` TEXT,
  `foto_path` VARCHAR(255) NULL,
  FOREIGN KEY (`id_local`) REFERENCES `locais`(`id_local`),
  FOREIGN KEY (`id_servico`) REFERENCES `servicos`(`id_servico`),
  FOREIGN KEY (`id_usuario_atribuido`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para registrar todo o histórico de interações de um chamado
CREATE TABLE IF NOT EXISTS `chamado_historico` (
  `id_historico` INT AUTO_INCREMENT PRIMARY KEY,
  `id_chamado` INT NOT NULL,
  `id_usuario` INT NULL,
  `data_ocorrencia` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `descricao_acao` TEXT NOT NULL,
  FOREIGN KEY (`id_chamado`) REFERENCES `chamados`(`id_chamado`) ON DELETE CASCADE,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- --- DADOS INICIAIS ---
INSERT INTO `usuarios` (`nome_completo`, `login`, `senha`, `tipo_acesso`, `ativo`) VALUES
('Administrador Padrão', 'admin', '$2y$10$TjG2rL5vH.Xl/jE/lI3hPOc1j9vsoj2oJvh2VpLg9QxXmQz.a.v8W', 'admin', 1)
ON DUPLICATE KEY UPDATE `nome_completo` = `nome_completo`;

INSERT INTO `locais` (`nome`) VALUES ('1º Andar - RH'), ('2º Andar - Diretoria'), ('Térreo - Recepção'), ('Setor A - Produção'), ('Setor B - Embalagem')
ON DUPLICATE KEY UPDATE `nome` = `nome`;

INSERT INTO `servicos` (`nome`) VALUES ('Troca de Lâmpada'), ('Reparo em Ar Condicionado'), ('Vazamento de Água'), ('Problema na Tomada Elétrica'), ('Manutenção de Computador')
ON DUPLICATE KEY UPDATE `nome` = `nome`;




-- Garante que estamos usando o banco de dados correto.
USE u154867272_manut2;

-- 1. Cria a nova tabela para os grupos de trabalho
CREATE TABLE IF NOT EXISTS `grupos` (
  `id_grupo` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Cria a tabela de associação entre usuários e grupos (relação muitos-para-muitos)
CREATE TABLE IF NOT EXISTS `usuario_grupos` (
  `id_usuario` INT NOT NULL,
  `id_grupo` INT NOT NULL,
  PRIMARY KEY (`id_usuario`, `id_grupo`),
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`) ON DELETE CASCADE,
  FOREIGN KEY (`id_grupo`) REFERENCES `grupos`(`id_grupo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Adiciona a coluna 'id_grupo' na tabela de serviços para definir para qual grupo um serviço é roteado
ALTER TABLE `servicos`
ADD COLUMN `id_grupo` INT NULL DEFAULT NULL AFTER `nome`,
ADD CONSTRAINT `fk_servico_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupos`(`id_grupo`) ON DELETE SET NULL;

-- 4. Adiciona a coluna 'id_grupo' na tabela de chamados para armazenar o grupo de destino
ALTER TABLE `chamados`
ADD COLUMN `id_grupo` INT NULL DEFAULT NULL AFTER `id_servico`,
ADD CONSTRAINT `fk_chamado_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupos`(`id_grupo`) ON DELETE SET NULL;

-- Insere alguns grupos de trabalho como exemplo inicial
INSERT INTO `grupos` (`nome`) VALUES ('Hidráulica'), ('Elétrica'), ('Geral'), ('Informática'), ('Marcenaria')
ON DUPLICATE KEY UPDATE `nome` = `nome`;



USE u154867272_manut2;

-- Altera a coluna 'tipo_acesso' na tabela 'usuarios' para incluir o novo tipo 'Geral'
ALTER TABLE `usuarios`
MODIFY COLUMN `tipo_acesso` ENUM('admin', 'gerencia', 'Geral') NOT NULL;


-- Garante que estamos usando o banco de dados correto.
USE u154867272_manut2;

-- 1. Cria a nova tabela para configurar as regras de SLA
CREATE TABLE IF NOT EXISTS `sla_config` (
  `id_sla` INT AUTO_INCREMENT PRIMARY KEY,
  `nivel` VARCHAR(50) NOT NULL UNIQUE,
  `dias_inicio` INT NOT NULL,
  `dias_fim` INT NOT NULL,
  `cor` VARCHAR(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Insere os dados iniciais para os níveis de SLA
INSERT INTO `sla_config` (`nivel`, `dias_inicio`, `dias_fim`, `cor`) VALUES
('Normal', 0, 14, '#d4edda'),
('Atenção', 15, 30, '#fff3cd'),
('Estourado', 31, 999, '#f8d7da')
ON DUPLICATE KEY UPDATE `nivel` = `nivel`; -- Não faz nada se os níveis já existirem


