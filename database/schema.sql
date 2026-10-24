USE u154867272_manut;

-- Tabela para armazenar os usuários do sistema (Admin e Gerência)
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` INT AUTO_INCREMENT PRIMARY KEY,
  `nome_completo` VARCHAR(150) NOT NULL,
  `login` VARCHAR(50) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL COMMENT 'Armazenar a senha com hash (ex: password_hash do PHP)',
  `tipo_acesso` ENUM('admin', 'gerencia') NOT NULL COMMENT 'Define o nível de permissão do usuário',
  `ativo` BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para os prédios
CREATE TABLE IF NOT EXISTS `predios` (
  `id_predio` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para os locais dentro de um prédio (ex: Andar, Sala, Setor)
CREATE TABLE IF NOT EXISTS `locais` (
  `id_local` INT AUTO_INCREMENT PRIMARY KEY,
  `id_predio` INT NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`id_predio`) REFERENCES `predios`(`id_predio`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para os tipos de serviços disponíveis
CREATE TABLE IF NOT EXISTS `servicos` (
  `id_servico` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de associação para vincular quais serviços estão disponíveis em quais locais
-- Isso permite uma configuração flexível (ex: "Ar Condicionado" só em salas, "Lâmpada" em todos os locais)
CREATE TABLE IF NOT EXISTS `locais_servicos` (
  `id_local` INT NOT NULL,
  `id_servico` INT NOT NULL,
  PRIMARY KEY (`id_local`, `id_servico`),
  FOREIGN KEY (`id_local`) REFERENCES `locais`(`id_local`) ON DELETE CASCADE,
  FOREIGN KEY (`id_servico`) REFERENCES `servicos`(`id_servico`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela principal para registrar os chamados de manutenção
CREATE TABLE IF NOT EXISTS `chamados` (
  `id_chamado` INT AUTO_INCREMENT PRIMARY KEY,
  `solicitante_nome` VARCHAR(150) NOT NULL,
  `solicitante_telefone` VARCHAR(20) NOT NULL,
  `id_predio` INT NOT NULL,
  `id_local` INT NOT NULL,
  `id_servico` INT NOT NULL,
  `descricao_problema` TEXT,
  `foto_path` VARCHAR(255) NULL COMMENT 'Caminho para a foto salva no servidor',
  `data_abertura` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('Aberto', 'Em Atendimento', 'Aguardando Peça', 'Finalizado', 'Cancelado') NOT NULL DEFAULT 'Aberto',
  `id_usuario_atribuido` INT NULL COMMENT 'ID do usuário da manutenção responsável',
  FOREIGN KEY (`id_predio`) REFERENCES `predios`(`id_predio`),
  FOREIGN KEY (`id_local`) REFERENCES `locais`(`id_local`),
  FOREIGN KEY (`id_servico`) REFERENCES `servicos`(`id_servico`),
  FOREIGN KEY (`id_usuario_atribuido`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para registrar todo o histórico de interações de um chamado
CREATE TABLE IF NOT EXISTS `chamado_historico` (
  `id_historico` INT AUTO_INCREMENT PRIMARY KEY,
  `id_chamado` INT NOT NULL,
  `id_usuario` INT NULL COMMENT 'Usuário que realizou a ação (pode ser nulo se for sistema)',
  `data_ocorrencia` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `descricao_acao` TEXT NOT NULL COMMENT 'Ex: "Chamado aberto", "Status alterado para Em Atendimento", "Atribuído a João Silva"',
  FOREIGN KEY (`id_chamado`) REFERENCES `chamados`(`id_chamado`) ON DELETE CASCADE,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- --- DADOS INICIAIS ---

-- Inserir o usuário administrador padrão
-- IMPORTANTE: A senha 'Manut123!' está aqui como um hash gerado pelo PHP com password_hash('Manut123!', PASSWORD_DEFAULT)
-- Você deve usar a função password_verify() no seu código PHP para validar o login.
INSERT INTO `usuarios` (`nome_completo`, `login`, `senha`, `tipo_acesso`, `ativo`) VALUES
('Administrador Padrão', 'admin', '$2y$10$TjG2rL5vH.Xl/jE/lI3hPOc1j9vsoj2oJvh2VpLg9QxXmQz.a.v8W', 'admin', 1)
ON DUPLICATE KEY UPDATE `nome_completo` = `nome_completo`; -- Não faz nada se o login 'admin' já existir

-- Inserir alguns dados de exemplo para facilitar os testes
INSERT INTO `predios` (`id_predio`, `nome`) VALUES (1, 'Sede Administrativa'), (2, 'Galpão de Produção')
ON DUPLICATE KEY UPDATE `nome` = `nome`;

INSERT INTO `locais` (`id_predio`, `id_local`, `nome`) VALUES
(1, 1, '1º Andar - RH'),
(1, 2, '2º Andar - Diretoria'),
(1, 3, 'Térreo - Recepção'),
(2, 4, 'Setor A'),
(2, 5, 'Setor B - Embalagem')
ON DUPLICATE KEY UPDATE `nome` = `nome`;

INSERT INTO `servicos` (`id_servico`, `nome`) VALUES
(1, 'Troca de Lâmpada'),
(2, 'Reparo em Ar Condicionado'),
(3, 'Vazamento de Água'),
(4, 'Problema na Tomada Elétrica'),
(5, 'Manutenção de Computador')
ON DUPLICATE KEY UPDATE `nome` = `nome`;

-- Associando serviços a locais (exemplo)
-- Na Sede Administrativa, todos os serviços estão disponíveis em todos os locais
INSERT INTO `locais_servicos` (`id_local`, `id_servico`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5),
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5),
(3, 1), (3, 3), (3, 4), (3, 5),
-- No Galpão, não tem ar condicionado nem manutenção de computador (exemplo)
(4, 1), (4, 3), (4, 4),
(5, 1), (5, 3), (5, 4)
ON DUPLICATE KEY UPDATE `id_local` = `id_local`; -- Não faz nada se a combinação já existir