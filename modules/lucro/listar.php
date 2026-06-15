<?php
session_start();
require('../../config/db.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ====================================================================
// SISTEMA DE GERAÇÃO AUTOMÁTICA DE MENSALIDADES POR ÉPOCA
// ====================================================================

function gerarMensalidadesAutomaticas($conn, $club_id = null, $isAdminPrincipal = false) {
    $hoje = date('Y-m-d');
    $mesAtual = date('Y-m');
    $diaVencimento = 5;
    $dataVencimento = $mesAtual . '-' . str_pad($diaVencimento, 2, '0', STR_PAD_LEFT);
    
    $checkColumns = $conn->query("SHOW COLUMNS FROM seasons");
    $columns = [];
    while ($row = $checkColumns->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $nameColumn = in_array('name', $columns) ? 'name' : (in_array('nome', $columns) ? 'nome' : 'id');
    
    $stmtSeason = $conn->prepare("
        SELECT id, {$nameColumn} as season_name, start_date, end_date 
        FROM seasons 
        WHERE ? BETWEEN start_date AND end_date 
        ORDER BY start_date DESC 
        LIMIT 1
    ");
    $stmtSeason->bind_param("s", $hoje);
    $stmtSeason->execute();
    $resultSeason = $stmtSeason->get_result();
    
    if ($resultSeason->num_rows === 0) {
        $stmtSeason->close();
        
        $anoAtual = date('Y');
        $nomeSeason = "Época {$anoAtual}/" . ($anoAtual + 1);
        $startDate = "{$anoAtual}-07-01";
        $endDate = ($anoAtual + 1) . "-06-30";
        
        $checkSeason = $conn->prepare("SELECT id FROM seasons WHERE start_date = ? AND end_date = ?");
        $checkSeason->bind_param("ss", $startDate, $endDate);
        $checkSeason->execute();
        $existingSeason = $checkSeason->get_result();
        
        if ($existingSeason->num_rows === 0) {
            if (in_array('name', $columns)) {
                $stmtCreate = $conn->prepare("INSERT INTO seasons (name, start_date, end_date) VALUES (?, ?, ?)");
                $stmtCreate->bind_param("sss", $nomeSeason, $startDate, $endDate);
            } elseif (in_array('nome', $columns)) {
                $stmtCreate = $conn->prepare("INSERT INTO seasons (nome, start_date, end_date) VALUES (?, ?, ?)");
                $stmtCreate->bind_param("sss", $nomeSeason, $startDate, $endDate);
            } else {
                $stmtCreate = $conn->prepare("INSERT INTO seasons (start_date, end_date) VALUES (?, ?)");
                $stmtCreate->bind_param("ss", $startDate, $endDate);
            }
            
            $stmtCreate->execute();
            $season_id = $conn->insert_id;
            $stmtCreate->close();
            
            $seasonData = [
                'id' => $season_id,
                'season_name' => $nomeSeason,
                'start_date' => $startDate,
                'end_date' => $endDate
            ];
        } else {
            $season_id = $existingSeason->fetch_assoc()['id'];
            $seasonData = [
                'id' => $season_id,
                'season_name' => $nomeSeason,
                'start_date' => $startDate,
                'end_date' => $endDate
            ];
        }
        $checkSeason->close();
    } else {
        $seasonData = $resultSeason->fetch_assoc();
        $season_id = $seasonData['id'];
    }
    $stmtSeason->close();
    
    if ($isAdminPrincipal) {
        $query = "
            SELECT DISTINCT p.id as jogador_id, p.primeiro_nome, p.ultimo_nome, t.club_id
            FROM players p
            INNER JOIN teams t ON p.team_id = t.id
            WHERE p.id NOT IN (
                SELECT jogador_id 
                FROM mensalidades 
                WHERE season_id = ? 
                AND DATE_FORMAT(data_vencimento, '%Y-%m') = ?
            )
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $season_id, $mesAtual);
    } else {
        $query = "
            SELECT DISTINCT p.id as jogador_id, p.primeiro_nome, p.ultimo_nome, t.club_id
            FROM players p
            INNER JOIN teams t ON p.team_id = t.id
            WHERE t.club_id = ?
            AND p.id NOT IN (
                SELECT jogador_id 
                FROM mensalidades 
                WHERE season_id = ? 
                AND DATE_FORMAT(data_vencimento, '%Y-%m') = ?
            )
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iis", $club_id, $season_id, $mesAtual);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $jogadores = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $valorMensalidade = 5000.00;
    $mensalidadesCriadas = 0;
    $mesNumero = (int)date('m');
    
    foreach ($jogadores as $jogador) {
        $queryInsert = "
            INSERT INTO mensalidades 
            (jogador_id, valor, data_vencimento, mes_referencia, status, season_id, mes) 
            VALUES (?, ?, ?, ?, 'pendente', ?, ?)
        ";
        $stmtInsert = $conn->prepare($queryInsert);
        $stmtInsert->bind_param(
            "idssii", 
            $jogador['jogador_id'], 
            $valorMensalidade, 
            $dataVencimento, 
            $mesAtual,
            $season_id,
            $mesNumero
        );
        
        if ($stmtInsert->execute()) {
            $mensalidadesCriadas++;
        }
        $stmtInsert->close();
    }
    
    return [
        'criadas' => $mensalidadesCriadas, 
        'season' => $seasonData['season_name'],
        'mensagem' => $mensalidadesCriadas > 0 
            ? "Geradas {$mensalidadesCriadas} mensalidades para {$seasonData['season_name']}" 
            : "Todas as mensalidades já foram geradas para este mês"
    ];
}

function executarCronMensalidades($conn) {
    try {
        $mesAtual = date('Y-m');
        
        $conn->query("
            CREATE TABLE IF NOT EXISTS cron_execucoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(50),
                mes_referencia VARCHAR(7),
                data_execucao DATETIME,
                resultado TEXT,
                UNIQUE KEY unique_mes (tipo, mes_referencia)
            )
        ");
        
        $stmt = $conn->prepare("
            SELECT data_execucao 
            FROM cron_execucoes 
            WHERE tipo = 'mensalidades' 
            AND mes_referencia = ?
        ");
        $stmt->bind_param("s", $mesAtual);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            return ['executado' => false, 'mensagem' => 'Cron já executado este mês'];
        }
        $stmt->close();
        
        $resultado = gerarMensalidadesAutomaticas($conn, null, true);
        
        $dataExecucao = date('Y-m-d H:i:s');
        $resultadoJson = json_encode($resultado);
        
        $stmt = $conn->prepare("
            INSERT INTO cron_execucoes (tipo, mes_referencia, data_execucao, resultado) 
            VALUES ('mensalidades', ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                data_execucao = VALUES(data_execucao),
                resultado = VALUES(resultado)
        ");
        $stmt->bind_param("sss", $mesAtual, $dataExecucao, $resultadoJson);
        $stmt->execute();
        $stmt->close();
        
        return [
            'executado' => true, 
            'resultado' => $resultado
        ];
    } catch (Exception $e) {
        error_log("Erro no cron de mensalidades: " . $e->getMessage());
        return [
            'executado' => false, 
            'mensagem' => 'Erro ao executar cron: ' . $e->getMessage()
        ];
    }
}

function atualizarMensalidadesAtrasadas($conn) {
    $hoje = date('Y-m-d');
    $query = "UPDATE mensalidades 
              SET status = 'atrasado' 
              WHERE status = 'pendente' 
              AND data_vencimento < ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $stmt->close();
}

// ====================================================================
// EXECUTAR SISTEMAS AUTOMÁTICOS
// ====================================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($user_club_id <= 0 && $user_id != 7) {
    die("Erro: Utilizador sem clube associado. Contacte o administrador.");
}

$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

try {
    $cronResult = executarCronMensalidades($conn);
    
    if ($cronResult['executado'] && isset($cronResult['resultado']['criadas']) && $cronResult['resultado']['criadas'] > 0) {
        $_SESSION['info'] = "✅ " . $cronResult['resultado']['mensagem'];
    }
} catch (Exception $e) {
    error_log("Erro na geração automática: " . $e->getMessage());
}

try {
    $conn->query("
        DELETE m1 FROM mensalidades m1
        INNER JOIN mensalidades m2 
            ON m1.jogador_id = m2.jogador_id
            AND DATE_FORMAT(m1.data_vencimento, '%Y-%m') = DATE_FORMAT(m2.data_vencimento, '%Y-%m')
            AND m1.id < m2.id;
    ");
    
    $checkConstraint = $conn->query("
        SELECT COUNT(*) as total 
        FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'mensalidades' 
        AND index_name = 'unique_jogador_mes'
    ");
    
    $constraintExists = $checkConstraint->fetch_assoc()['total'] > 0;
    
    if (!$constraintExists) {
        $conn->query("
            ALTER TABLE mensalidades 
            ADD UNIQUE KEY unique_jogador_mes (jogador_id, mes_referencia)
        ");
    }
} catch (Exception $e) {
    error_log("Aviso ao processar mensalidades: " . $e->getMessage());
}

atualizarMensalidadesAtrasadas($conn);

try {
    $checkSeason = $conn->query("SELECT COUNT(*) as total FROM seasons");
    if ($checkSeason) {
        $seasonCount = $checkSeason->fetch_assoc()['total'];
        
        if ($seasonCount == 0) {
            $anoAtual = date('Y');
            $nomeSeason = "Época {$anoAtual}/" . ($anoAtual + 1);
            $startDate = "{$anoAtual}-07-01";
            $endDate = ($anoAtual + 1) . "-06-30";
            
            $stmtCreateSeason = $conn->prepare(
                "INSERT INTO seasons (name, start_date, end_date) 
                 VALUES (?, ?, ?)"
            );
            $stmtCreateSeason->bind_param("sss", $nomeSeason, $startDate, $endDate);
            $stmtCreateSeason->execute();
            $stmtCreateSeason->close();
        }
    }
} catch (Exception $e) {
    error_log("Erro ao verificar seasons: " . $e->getMessage());
}

$checkColumn = $conn->query("SHOW COLUMNS FROM receitas LIKE 'club_id'");
$hasClubColumn = ($checkColumn && $checkColumn->num_rows > 0);

// 🧹 LIMPAR DUPLICADOS AGORA (MANUAL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpar_duplicados_agora'])) {
    try {
        $queryContar = "
            SELECT COUNT(*) as total FROM mensalidades m1
            INNER JOIN mensalidades m2 
            WHERE m1.id < m2.id 
            AND m1.jogador_id = m2.jogador_id 
            AND DATE_FORMAT(m1.data_vencimento, '%Y-%m') = DATE_FORMAT(m2.data_vencimento, '%Y-%m')
        ";
        $resultContar = $conn->query($queryContar);
        $totalRemover = $resultContar->fetch_assoc()['total'];
        
        $queryLimpar = "
            DELETE m1 FROM mensalidades m1
            INNER JOIN mensalidades m2 
            WHERE m1.id < m2.id 
            AND m1.jogador_id = m2.jogador_id 
            AND DATE_FORMAT(m1.data_vencimento, '%Y-%m') = DATE_FORMAT(m2.data_vencimento, '%Y-%m')
        ";
        
        if ($conn->query($queryLimpar)) {
            $removidos = $conn->affected_rows;
            
            if ($removidos > 0) {
                $_SESSION['sucesso'] = "🗑️ <strong>{$removidos}</strong> mensalidades duplicadas foram eliminadas com sucesso!";
            } else {
                $_SESSION['info'] = "✅ Não foram encontradas mensalidades duplicadas. Tudo limpo!";
            }
        }
    } catch (Exception $e) {
        $_SESSION['erro'] = "Erro ao limpar duplicados: " . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ATUALIZAR STATUS DE MENSALIDADE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
    $mensalidade_id = intval($_POST['mensalidade_id']);
    $novo_status = $_POST['novo_status'];
    
    if (in_array($novo_status, ['pago', 'pendente', 'atrasado'])) {
        try {
            $data_pagamento = null;
            if ($novo_status === 'pago') {
                $data_pagamento = date('Y-m-d');
            }
            
            if ($data_pagamento) {
                $stmt = $conn->prepare("UPDATE mensalidades SET status = ?, data_pagamento = ? WHERE id = ?");
                $stmt->bind_param("ssi", $novo_status, $data_pagamento, $mensalidade_id);
            } else {
                $stmt = $conn->prepare("UPDATE mensalidades SET status = ?, data_pagamento = NULL WHERE id = ?");
                $stmt->bind_param("si", $novo_status, $mensalidade_id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['sucesso'] = "Status da mensalidade atualizado com sucesso!";
            } else {
                $_SESSION['erro'] = "Erro ao atualizar status da mensalidade.";
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['erro'] = "Erro ao atualizar status: " . $e->getMessage();
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?mes=' . ($_GET['mes'] ?? date('Y-m')));
    exit;
}

$categorias = ['Todas', 'Quotas Sócios', 'Patrocínios', 'Vendas', 'Eventos', 'Subsídios','Mensalidade', 'Outras'];

// ADICIONAR RECEITA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar'])) {
    $descricao = trim($_POST['descricao']);
    $valor = floatval($_POST['valor']);
    $categoria = trim($_POST['categoria']);
    $data = $_POST['data'];
    $tipo = $_POST['tipo'];

    if ($descricao && $valor > 0 && $categoria && $data) {
        try {
            if ($hasClubColumn) {
                $club_para_inserir = $isAdminPrincipal && isset($_POST['club_id']) ? intval($_POST['club_id']) : $user_club_id;
                $stmt = $conn->prepare("INSERT INTO receitas (descricao, valor, categoria, data, tipo, club_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sdsssi", $descricao, $valor, $categoria, $data, $tipo, $club_para_inserir);
            } else {
                $stmt = $conn->prepare("INSERT INTO receitas (descricao, valor, categoria, data, tipo) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sdsss", $descricao, $valor, $categoria, $data, $tipo);
            }
            
            if ($stmt->execute()) {
                $_SESSION['sucesso'] = "Receita adicionada com sucesso!";
            } else {
                $_SESSION['erro'] = "Erro ao adicionar receita: " . $conn->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['erro'] = "Erro ao adicionar receita: " . $e->getMessage();
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?mes=' . date('Y-m', strtotime($data)));
    exit;
}

// ELIMINAR RECEITA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    $id = intval($_POST['id']);
    $origem = $_POST['origem'] ?? 'receita';
    
    if ($origem === 'receita') {
        try {
            if ($hasClubColumn) {
                if ($isAdminPrincipal) {
                    $stmt = $conn->prepare("DELETE FROM receitas WHERE id = ?");
                    $stmt->bind_param("i", $id);
                } else {
                    $stmt = $conn->prepare("DELETE FROM receitas WHERE id = ? AND club_id = ?");
                    $stmt->bind_param("ii", $id, $user_club_id);
                }
            } else {
                $stmt = $conn->prepare("DELETE FROM receitas WHERE id = ?");
                $stmt->bind_param("i", $id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['sucesso'] = "Receita eliminada com sucesso!";
            } else {
                $_SESSION['erro'] = "Erro ao eliminar receita.";
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['erro'] = "Erro ao eliminar receita: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Não podes eliminar mensalidades por aqui!";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?mes=' . ($_GET['mes'] ?? date('Y-m')));
    exit;
}

// ================= FILTRAR POR MÊS E CATEGORIA =================
$mesAtual = $_GET['mes'] ?? date('Y-m');
$filtroCategoria = $_GET['categoria'] ?? 'Todas';
$verTodas = ($mesAtual === 'todas');

try {
    if (!$verTodas) {
        $primeiroDia = $mesAtual . '-01';
        $ultimoDia = date('Y-m-t', strtotime($primeiroDia));
    }
    
    if ($filtroCategoria === 'Todas') {
        if ($hasClubColumn) {
            if ($isAdminPrincipal) {
                if ($verTodas) {
                    $stmt = $conn->prepare("SELECT r.*, 'receita' as origem, c.nome as clube_nome FROM receitas r LEFT JOIN clubs c ON r.club_id = c.id ORDER BY r.data DESC");
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("SELECT r.*, 'receita' as origem, c.nome as clube_nome FROM receitas r LEFT JOIN clubs c ON r.club_id = c.id WHERE r.data BETWEEN ? AND ? ORDER BY r.data DESC");
                    $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
                    $stmt->execute();
                }
            } else {
                if ($verTodas) {
                    $stmt = $conn->prepare("SELECT r.*, 'receita' as origem, c.nome as clube_nome FROM receitas r LEFT JOIN clubs c ON r.club_id = c.id WHERE r.club_id = ? ORDER BY r.data DESC");
                    $stmt->bind_param("i", $user_club_id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("SELECT r.*, 'receita' as origem, c.nome as clube_nome FROM receitas r LEFT JOIN clubs c ON r.club_id = c.id WHERE r.club_id = ? AND r.data BETWEEN ? AND ? ORDER BY r.data DESC");
                    $stmt->bind_param("iss", $user_club_id, $primeiroDia, $ultimoDia);
                    $stmt->execute();
                }
            }
        } else {
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT *, 'receita' as origem FROM receitas ORDER BY data DESC");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT *, 'receita' as origem FROM receitas WHERE data BETWEEN ? AND ? ORDER BY data DESC");
                $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
        }
        $qReceitas = $stmt->get_result();
        $receitas = $qReceitas->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if ($isAdminPrincipal) {
            if ($verTodas) {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        CONCAT('Mensalidade - ', p.primeiro_nome, ' ', p.ultimo_nome) as descricao,
                        m.valor,
                        'Mensalidade' as categoria,
                        COALESCE(m.data_pagamento, m.data_vencimento) as data,
                        'Mensal' as tipo,
                        m.status,
                        'mensalidade' as origem,
                        c.nome as clube_nome
                    FROM mensalidades m
                    INNER JOIN players p ON m.jogador_id = p.id
                    INNER JOIN teams t ON p.team_id = t.id
                    LEFT JOIN clubs c ON t.club_id = c.id
                    ORDER BY COALESCE(m.data_pagamento, m.data_vencimento) DESC
                ");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        CONCAT('Mensalidade - ', p.primeiro_nome, ' ', p.ultimo_nome) as descricao,
                        m.valor,
                        'Mensalidade' as categoria,
                        COALESCE(m.data_pagamento, m.data_vencimento) as data,
                        'Mensal' as tipo,
                        m.status,
                        'mensalidade' as origem,
                        c.nome as clube_nome
                    FROM mensalidades m
                    INNER JOIN players p ON m.jogador_id = p.id
                    INNER JOIN teams t ON p.team_id = t.id
                    LEFT JOIN clubs c ON t.club_id = c.id
                    WHERE DATE_FORMAT(m.data_vencimento, '%Y-%m') = ?
                    ORDER BY COALESCE(m.data_pagamento, m.data_vencimento) DESC
                ");
                $stmt->bind_param("s", $mesAtual);
                $stmt->execute();
            }
        } else {
            if ($verTodas) {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        CONCAT('Mensalidade ', DATE_FORMAT(m.data_vencimento, '%m/%Y'), ' - ', p.primeiro_nome, ' ', p.ultimo_nome) AS descricao,
                        m.valor,
                        'Mensalidade' AS categoria,
                        COALESCE(m.data_pagamento, m.data_vencimento) AS data,
                        'Mensal' AS tipo,
                        m.status,
                        'mensalidade' AS origem
                    FROM mensalidades m
                    INNER JOIN (
                        SELECT MAX(id) AS max_id
                        FROM mensalidades
                        GROUP BY jogador_id, DATE_FORMAT(data_vencimento, '%Y-%m')
                    ) x ON x.max_id = m.id
                    INNER JOIN players p ON m.jogador_id = p.id
                    INNER JOIN teams t ON p.team_id = t.id
                    WHERE t.club_id = ?
                    ORDER BY m.data_vencimento DESC, p.primeiro_nome ASC
                ");
                $stmt->bind_param("i", $user_club_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        CONCAT('Mensalidade - ', p.primeiro_nome, ' ', p.ultimo_nome) as descricao,
                        m.valor,
                        'Mensalidade' as categoria,
                        COALESCE(m.data_pagamento, m.data_vencimento) as data,
                        'Mensal' as tipo,
                        m.status,
                        'mensalidade' as origem
                    FROM mensalidades m
                    INNER JOIN players p ON m.jogador_id = p.id
                    INNER JOIN teams t ON p.team_id = t.id
                    WHERE t.club_id = ? AND COALESCE(m.data_pagamento, m.data_vencimento) BETWEEN ? AND ?
                    ORDER BY COALESCE(m.data_pagamento, m.data_vencimento) DESC
                ");
                $stmt->bind_param("iss", $user_club_id, $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
        }
        $qMensalidades = $stmt->get_result();
        $mensalidades = $qMensalidades->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $receitasFiltradas = array_merge($receitas, $mensalidades);
        usort($receitasFiltradas, function($a, $b) {
            return strtotime($b['data']) - strtotime($a['data']);
        });
        
    } elseif ($filtroCategoria === 'Mensalidade') {
        if ($isAdminPrincipal) {
            if ($verTodas) {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        CONCAT('Mensalidade ', DATE_FORMAT(m.data_vencimento, '%m/%Y'), ' - ', p.primeiro_nome, ' ', p.ultimo_nome) as descricao,
                        m.valor,
                        'Mensalidade' as categoria,
                        COALESCE(m.data_pagamento, m.data_vencimento) as data,
                        'Mensal' as tipo,
                        m.status,
                        'mensalidade' as origem,
                        c.nome as clube_nome
                        FROM mensalidades m
                        INNER JOIN players p ON p.id = m.jogador_id
                        INNER JOIN teams t ON p.team_id = t.id
                        WHERE t.club_id = ?
                        AND DATE_FORMAT(m.data_vencimento, '%Y-%m') = ?
                        GROUP BY m.jogador_id
                ");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        CONCAT('Mensalidade ', DATE_FORMAT(m.data_vencimento, '%m/%Y'), ' - ', p.primeiro_nome, ' ', p.ultimo_nome) as descricao,
                        m.valor,
                        'Mensalidade' as categoria,
                        COALESCE(m.data_pagamento, m.data_vencimento) as data,
                        'Mensal' as tipo,
                        m.status,
                        'mensalidade' as origem,
                        c.nome as clube_nome
                    FROM mensalidades m
                    INNER JOIN players p ON m.jogador_id = p.id
                    INNER JOIN teams t ON p.team_id = t.id
                    LEFT JOIN clubs c ON t.club_id = c.id
                    WHERE DATE_FORMAT(m.data_vencimento, '%Y-%m') = ?
                    ORDER BY p.primeiro_nome ASC
                ");
                $stmt->bind_param("s", $mesAtual);
                $stmt->execute();
            }
        } else {
            if ($verTodas) {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        CONCAT('Mensalidade ', DATE_FORMAT(m.data_vencimento, '%m/%Y'), ' - ', p.primeiro_nome, ' ', p.ultimo_nome) as descricao,
                        m.valor,
                        'Mensalidade' as categoria,
                        COALESCE(m.data_pagamento, m.data_vencimento) as data,
                        'Mensal' as tipo,
                        m.status,
                        'mensalidade' as origem
                    FROM mensalidades m
                    INNER JOIN players p ON m.jogador_id = p.id
                    INNER JOIN teams t ON p.team_id = t.id
                    WHERE t.club_id = ?
                    ORDER BY m.data_vencimento DESC, p.primeiro_nome ASC
                ");
                $stmt->bind_param("i", $user_club_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("
                    SELECT 
                        m.id,
                        CONCAT('Mensalidade ', DATE_FORMAT(m.data_vencimento, '%m/%Y'), ' - ', p.primeiro_nome, ' ', p.ultimo_nome) as descricao,
                        m.valor,
                        'Mensalidade' as categoria,
                        COALESCE(m.data_pagamento, m.data_vencimento) as data,
                        'Mensal' as tipo,
                        m.status,
                        'mensalidade' as origem
                    FROM mensalidades m
                    INNER JOIN players p ON m.jogador_id = p.id
                    INNER JOIN teams t ON p.team_id = t.id
                    WHERE t.club_id = ? AND DATE_FORMAT(m.data_vencimento, '%Y-%m') = ?
                    ORDER BY p.primeiro_nome ASC
                ");
                $stmt->bind_param("is", $user_club_id, $mesAtual);
                $stmt->execute();
            }
        }
        $q = $stmt->get_result();
        $receitasFiltradas = $q->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        if ($hasClubColumn) {
            if ($isAdminPrincipal) {
                if ($verTodas) {
                    $stmt = $conn->prepare("SELECT r.*, 'receita' as origem, c.nome as clube_nome FROM receitas r LEFT JOIN clubs c ON r.club_id = c.id WHERE r.categoria = ? ORDER BY r.data DESC");
                    $stmt->bind_param("s", $filtroCategoria);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("SELECT r.*, 'receita' as origem, c.nome as clube_nome FROM receitas r LEFT JOIN clubs c ON r.club_id = c.id WHERE r.categoria = ? AND r.data BETWEEN ? AND ? ORDER BY r.data DESC");
                    $stmt->bind_param("sss", $filtroCategoria, $primeiroDia, $ultimoDia);
                    $stmt->execute();
                }
            } else {
                if ($verTodas) {
                    $stmt = $conn->prepare("SELECT r.*, 'receita' as origem FROM receitas r WHERE r.categoria = ? AND r.club_id = ? ORDER BY r.data DESC");
                    $stmt->bind_param("si", $filtroCategoria, $user_club_id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("SELECT r.*, 'receita' as origem FROM receitas r WHERE r.categoria = ? AND r.club_id = ? AND r.data BETWEEN ? AND ? ORDER BY r.data DESC");
                    $stmt->bind_param("siss", $filtroCategoria, $user_club_id, $primeiroDia, $ultimoDia);
                    $stmt->execute();
                }
            }
        } else {
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT *, 'receita' as origem FROM receitas WHERE categoria = ? ORDER BY data DESC");
                $stmt->bind_param("s", $filtroCategoria);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT *, 'receita' as origem FROM receitas WHERE categoria = ? AND data BETWEEN ? AND ? ORDER BY data DESC");
                $stmt->bind_param("sss", $filtroCategoria, $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
        }
        $q = $stmt->get_result();
        $receitasFiltradas = $q->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    $_SESSION['erro'] = "Erro ao carregar receitas: " . $e->getMessage();
    $receitasFiltradas = [];
}

// ================= CALCULAR TOTAIS DO MÊS SELECIONADO =================
try {
    if (!$verTodas) {
        $primeiroDia = $mesAtual . '-01';
        $ultimoDia = date('Y-m-t', strtotime($primeiroDia));
    }
    
    if ($hasClubColumn) {
        if ($isAdminPrincipal) {
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE data BETWEEN ? AND ?");
                $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $totalReceitas = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE tipo='Mensal'");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE tipo='Mensal' AND data BETWEEN ? AND ?");
                $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $receitasMensais = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as total FROM mensalidades m WHERE m.status = 'pago'");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as total FROM mensalidades m WHERE m.status = 'pago' AND COALESCE(m.data_pagamento, m.data_vencimento) BETWEEN ? AND ?");
                $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $totalMensalidadesPagas = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as total FROM mensalidades m WHERE m.status = 'pendente'");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as total FROM mensalidades m WHERE m.status = 'pendente' AND COALESCE(m.data_pagamento, m.data_vencimento) BETWEEN ? AND ?");
                $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $totalMensalidadesPendentes = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(m.valor), 0) as valor_total FROM mensalidades m WHERE m.status = 'atrasado'");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(m.valor), 0) as valor_total FROM mensalidades m WHERE m.status = 'atrasado' AND COALESCE(m.data_pagamento, m.data_vencimento) BETWEEN ? AND ?");
                $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $dadosAtrasadas = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE club_id = ?");
                $stmt->bind_param("i", $user_club_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE club_id = ? AND data BETWEEN ? AND ?");
                $stmt->bind_param("iss", $user_club_id, $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $totalReceitas = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE tipo='Mensal' AND club_id = ?");
                $stmt->bind_param("i", $user_club_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE tipo='Mensal' AND club_id = ? AND data BETWEEN ? AND ?");
                $stmt->bind_param("iss", $user_club_id, $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $receitasMensais = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as total FROM mensalidades m INNER JOIN players p ON m.jogador_id = p.id INNER JOIN teams t ON p.team_id = t.id WHERE m.status = 'pago' AND t.club_id = ?");
                $stmt->bind_param("i", $user_club_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as total FROM mensalidades m INNER JOIN players p ON m.jogador_id = p.id INNER JOIN teams t ON p.team_id = t.id WHERE m.status = 'pago' AND t.club_id = ? AND COALESCE(m.data_pagamento, m.data_vencimento) BETWEEN ? AND ?");
                $stmt->bind_param("iss", $user_club_id, $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $totalMensalidadesPagas = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as total FROM mensalidades m INNER JOIN players p ON m.jogador_id = p.id INNER JOIN teams t ON p.team_id = t.id WHERE m.status = 'pendente' AND t.club_id = ?");
                $stmt->bind_param("i", $user_club_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as total FROM mensalidades m INNER JOIN players p ON m.jogador_id = p.id INNER JOIN teams t ON p.team_id = t.id WHERE m.status = 'pendente' AND t.club_id = ? AND COALESCE(m.data_pagamento, m.data_vencimento) BETWEEN ? AND ?");
                $stmt->bind_param("iss", $user_club_id, $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $totalMensalidadesPendentes = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            if ($verTodas) {
                $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(m.valor), 0) as valor_total FROM mensalidades m INNER JOIN players p ON m.jogador_id = p.id INNER JOIN teams t ON p.team_id = t.id WHERE m.status = 'atrasado' AND t.club_id = ?");
                $stmt->bind_param("i", $user_club_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(m.valor), 0) as valor_total FROM mensalidades m INNER JOIN players p ON m.jogador_id = p.id INNER JOIN teams t ON p.team_id = t.id WHERE m.status = 'atrasado' AND t.club_id = ? AND COALESCE(m.data_pagamento, m.data_vencimento) BETWEEN ? AND ?");
                $stmt->bind_param("iss", $user_club_id, $primeiroDia, $ultimoDia);
                $stmt->execute();
            }
            $dadosAtrasadas = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } else {
        if ($verTodas) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE data BETWEEN ? AND ?");
            $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
            $stmt->execute();
        }
        $totalReceitas = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        if ($verTodas) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE tipo='Mensal'");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM receitas WHERE tipo='Mensal' AND data BETWEEN ? AND ?");
            $stmt->bind_param("ss", $primeiroDia, $ultimoDia);
            $stmt->execute();
        }
        $receitasMensais = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        $totalMensalidadesPagas = 0;
        $totalMensalidadesPendentes = 0;
        $dadosAtrasadas = ['total' => 0, 'valor_total' => 0];
    }
    
    $totalMensalidadesAtrasadas = $dadosAtrasadas['total'];
    $valorMensalidadesAtrasadas = $dadosAtrasadas['valor_total'];
    $totalReceitas += $totalMensalidadesPagas;
} catch (Exception $e) {
    $totalReceitas = 0;
    $receitasMensais = 0;
    $totalMensalidadesPagas = 0;
    $totalMensalidadesPendentes = 0;
    $totalMensalidadesAtrasadas = 0;
    $valorMensalidadesAtrasadas = 0;
}

$mesesPortugues = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

$partes = explode('-', $mesAtual);
$ano = $partes[0] ?? date('Y');
$mes = $partes[1] ?? date('m');
$mesExibicao = $mesesPortugues[$mes] . ' ' . $ano;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Receitas - SportGes</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    overflow-x: hidden;
    max-width: 100%;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
    background: #f8fafc;
    min-height: 100vh;
    width: 100%;
}

.main-content {
    margin-left: 260px;
    margin-top: 0;
    padding: 24px;
    min-height: 100vh;
    transition: margin-left 0.3s ease;
    width: calc(100% - 260px);
    box-sizing: border-box;
    overflow-x: hidden;
}

.alert { 
    padding: 20px; 
    border-radius: 16px; 
    margin-bottom: 24px; 
    display: flex; 
    align-items: flex-start; 
    gap: 16px; 
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.alert-success { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #16a34a; border: 2px solid #86efac; }
.alert-error { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626; border: 2px solid #fca5a5; }
.alert-info { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af; border: 2px solid #93c5fd; }

.mensalidades-alert {
    border-left: 6px solid #dc2626;
    background: linear-gradient(135deg, #fff1f2 0%, #fee2e2 100%);
}

.alert-icon-wrapper {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(220, 38, 38, 0.1);
    border-radius: 12px;
}

.alert-icon-wrapper i { font-size: 28px; color: #dc2626; }
.alert-content { flex: 1; }
.alert-content strong { display: block; font-size: 16px; margin-bottom: 8px; color: #991b1b; }
.alert-content p { margin: 0; font-size: 14px; line-height: 1.6; color: #7f1d1d; }

.page-header {
    background: #fff;
    padding: 24px;
    border-radius: 16px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    width: 100%;
    box-sizing: border-box;
}

.page-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 24px;
}

.page-title h1 {
    font-size: 28px;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.page-title h1 i { color: #22c55e; }
.page-title p { color: #64748b; font-size: 14px; margin-top: 8px; }

.header-buttons { display: flex; gap: 12px; flex-wrap: wrap; }

.btn-primary {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    white-space: nowrap;
}

.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4); }
.btn-relatorios { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); box-shadow: 0 4px 12px rgba(139,92,246,0.3); }

.header-actions {
    display: flex;
    flex-direction: column;
    gap: 16px;
    align-items: flex-end;
    flex-shrink: 0;
}

.mes-selector-wrapper { margin-top: 20px; }

.mes-selector {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
}

.mes-selector label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #475569;
    font-size: 14px;
    white-space: nowrap;
}

.mes-selector label i { color: #3b82f6; font-size: 18px; }

.mes-selector input[type="month"] {
    padding: 10px 14px;
    font-size: 14px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    min-width: 160px;
    background: white;
    transition: all 0.2s ease;
}

.mes-selector input[type="month"]:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.mes-atual {
    padding: 10px 16px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border-radius: 10px;
    color: #475569;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    font-size: 14px;
}

.mes-atual i { color: #64748b; }

.btn-todas-despesas {
    padding: 10px 16px;
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    border-radius: 10px;
    font-weight: 600;
    color: #334155;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    border: 2px solid transparent;
}

.btn-todas-despesas:hover { background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%); transform: translateY(-1px); }

.btn-todas-despesas.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    border-color: #2563eb;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-top: 20px;
    width: 100%;
}

.stat-card {
    padding: 20px;
    border-radius: 12px;
    color: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.16); }
.stat-card.green { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
.stat-card.emerald { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-card.teal { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); }
.stat-card.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.stat-card.red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

.stat-label {
    font-size: 13px;
    opacity: 0.95;
    margin-bottom: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 26px;
    font-weight: 800;
    word-break: break-word;
    line-height: 1.3;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-top: 4px;
}

/* Formulário */
.form-section {
    background: white;
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
    display: none;
}

.form-section.active { display: block; }
.form-section h2 { font-size: 20px; font-weight: 600; color: #0f172a; margin-bottom: 20px; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.form-group { display: flex; flex-direction: column; }
.form-group.full { grid-column: 1 / -1; }
.form-label { font-size: 14px; font-weight: 500; color: #475569; margin-bottom: 8px; }

.form-input,
.form-select {
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    transition: all 0.2s ease;
    width: 100%;
}

.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: #22c55e;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
}

.form-buttons { display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; }

.btn-submit {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4); }

.btn-cancel {
    background: #f1f5f9;
    color: #64748b;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-cancel:hover { background: #e2e8f0; }

/* Filtros */
.filter-section {
    background: white;
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 24px;
}

.filter-label { font-size: 14px; font-weight: 500; color: #475569; margin-bottom: 12px; }
.filter-buttons { display: flex; flex-wrap: wrap; gap: 8px; }

.filter-btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.filter-btn.active { background: #22c55e; color: white; }
.filter-btn:not(.active) { background: #f1f5f9; color: #475569; }
.filter-btn:hover { transform: translateY(-1px); }

/* Tabela */
.table-section {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table-container {
    overflow-x: auto;
    width: 100%;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead tr { background: #f8fafc; }

th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

td {
    padding: 14px 16px;
    font-size: 14px;
    color: #374151;
    border-bottom: 1px solid #f1f5f9;
    white-space: nowrap;
}

tbody tr:last-child td { border-bottom: none; }

tbody tr:hover { background: #f8fafc; }

/* Badges */
.badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.badge-blue    { background: #dbeafe; color: #2563eb; }
.badge-green   { background: #dcfce7; color: #16a34a; }
.badge-yellow  { background: #fef3c7; color: #d97706; }
.badge-red     { background: #fee2e2; color: #dc2626; }
.badge-orange  { background: #fed7aa; color: #c2410c; }
.badge-purple  { background: #ede9fe; color: #7c3aed; }
.badge-gray    { background: #f1f5f9; color: #64748b; }

/* Valor receita */
.valor-receita {
    font-weight: 700;
    color: #16a34a;
    font-size: 14px;
}

.valor-pendente  { color: #d97706; font-weight: 700; }
.valor-atrasado  { color: #dc2626; font-weight: 700; }

/* Botões Ação */
.btn-edit {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 7px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-edit:hover { background: #2563eb; transform: translateY(-1px); }

.btn-delete {
    background: #ef4444;
    color: white;
    border: none;
    padding: 7px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-delete:hover { background: #dc2626; transform: translateY(-1px); }

.acoes-cell { display: flex; gap: 6px; align-items: center; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}

.empty-state i { font-size: 48px; margin-bottom: 12px; display: block; }
.empty-state p { font-size: 15px; }

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    padding: 20px;
}

.modal.active { display: flex; align-items: center; justify-content: center; }

.modal-content {
    background: white;
    padding: 32px;
    border-radius: 16px;
    max-width: 450px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    font-size: 20px;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-options { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }

.status-option {
    padding: 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-option input[type="radio"] { width: 20px; height: 20px; cursor: pointer; flex-shrink: 0; }
.status-option.pendente { border-color: #fef3c7; background: #fffbeb; }
.status-option.pendente:hover { border-color: #f59e0b; background: #fef3c7; }
.status-option.atrasado { border-color: #fee2e2; background: #fef2f2; }
.status-option.atrasado:hover { border-color: #ef4444; background: #fee2e2; }
.status-option.pago { border-color: #dcfce7; background: #f0fdf4; }
.status-option.pago:hover { border-color: #22c55e; background: #dcfce7; }

.modal-buttons { display: flex; gap: 12px; flex-wrap: wrap; }

/* Responsive */
@media (max-width: 1024px) {
    .main-content { margin-left: 0; padding: 16px; width: 100%; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-header-top { flex-direction: column; align-items: stretch; }
    .page-title h1 { font-size: 22px; }
    .header-actions { width: 100%; align-items: stretch; }
    .header-buttons { width: 100%; }
    .header-buttons a, .header-buttons button { flex: 1; justify-content: center; }
    .mes-selector { flex-direction: row; flex-wrap: wrap; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }

    .table-container thead { display: none; }
    .table-container tbody tr {
        display: block;
        margin-bottom: 12px;
        border-radius: 10px;
        padding: 12px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .table-container td {
        display: block;
        padding: 6px 0;
        white-space: normal;
        border: none;
    }
    .table-container td::before {
        content: attr(data-label) ": ";
        font-weight: 700;
        color: #475569;
        display: inline-block;
        min-width: 90px;
    }
    .acoes-cell { justify-content: flex-start; }
}

@media (max-width: 600px) {
    .stats-grid { grid-template-columns: 1fr; }
    .header-buttons { flex-direction: column; }
}

@media (max-width: 480px) {
    .main-content { padding: 10px; margin-top: 50px; }
    .page-title h1 { font-size: 18px; }
    .stat-value { font-size: 20px; }
}

@media (min-width: 1400px) {
    .stats-grid { grid-template-columns: repeat(5, 1fr); }
}

@media print {
    .main-content { margin-left: 0; padding: 0; }
    .page-header-top, .filter-section, .form-section, .btn-primary, .btn-edit, .btn-delete, .modal { display: none !important; }
}
    </style>
</head>
<body>

<?php require('../../includes/sidebar.php'); ?>

<div class="main-content">

    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success">
            <i class='bx bx-check-circle' style="font-size: 20px;"></i>
            <?= $_SESSION['sucesso'] ?>
        </div>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-error">
            <i class='bx bx-error-circle' style="font-size: 20px;"></i>
            <?= $_SESSION['erro'] ?>
        </div>
        <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info">
            <i class='bx bx-info-circle' style="font-size: 20px;"></i>
            <?= $_SESSION['info'] ?>
        </div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>

    <?php if ($totalMensalidadesAtrasadas > 0): ?>
        <div class="alert alert-error mensalidades-alert">
            <div class="alert-icon-wrapper">
                <i class='bx bx-error-circle'></i>
            </div>
            <div class="alert-content">
                <strong>⚠️ ATENÇÃO: Mensalidades Atrasadas!</strong>
                <p>
                    Existem <strong><?= $totalMensalidadesAtrasadas ?></strong> mensalidade(s) atrasada(s) 
                    no valor total de <strong><?= number_format($valorMensalidadesAtrasadas, 2, ',', '.') ?> €</strong>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$hasClubColumn): ?>
        <div class="alert alert-error">
            <i class='bx bx-error-circle' style="font-size: 20px;"></i>
            AVISO: A tabela 'receitas' não tem a coluna 'club_id'. Execute: ALTER TABLE receitas ADD COLUMN club_id INT NULL;
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <div class="page-title">
                <h1>
                    <i class='bx bx-dollar-circle'></i>
                    Gestão de Receitas - Meu Clube
                </h1>
                <p>Controla todas as receitas do seu clube</p>
            </div>

            <div class="header-actions">
                <div class="header-buttons">
                    <a href="relatorios.php" class="btn-primary btn-relatorios">
                        <i class='bx bx-bar-chart-alt-2'></i>
                        Ver Relatórios
                    </a>
                    <button onclick="toggleForm()" class="btn-primary">
                        <i class='bx bx-plus'></i> Nova Receita
                    </button>
                </div>
            </div>
        </div>

        <div class="mes-selector-wrapper">
            <div class="mes-selector">
                <label>
                    <i class='bx bx-calendar'></i> Mês:
                </label>
                <input type="month"
                       id="mesSelecionado"
                       value="<?= $mesAtual ?>"
                       onchange="mudarMes()">
                <div class="mes-atual">
                    <i class='bx bx-time-five'></i>
                    <?= $mesExibicao ?>
                </div>
                <a href="?mes=todas<?= isset($_GET['categoria']) ? '&categoria=' . urlencode($_GET['categoria']) : '' ?>"
                   class="btn-todas-despesas <?= (isset($_GET['mes']) && $_GET['mes'] === 'todas') ? 'active' : '' ?>">
                    <i class='bx bx-list-ul'></i>
                    Todas as Receitas
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card green">
                <p class="stat-label">Total de Receitas</p>
                <p class="stat-value"><?= number_format($totalReceitas, 2, ',', '.') ?> €</p>
            </div>
            <div class="stat-card emerald">
                <p class="stat-label">Receitas Mensais</p>
                <p class="stat-value"><?= number_format($receitasMensais, 2, ',', '.') ?> €</p>
            </div>
            <div class="stat-card teal">
                <p class="stat-label">Mensalidades Pagas</p>
                <p class="stat-value"><?= number_format($totalMensalidadesPagas, 2, ',', '.') ?> €</p>
            </div>
            <div class="stat-card orange">
                <p class="stat-label">Mensalidades Pendentes</p>
                <p class="stat-value"><?= number_format($totalMensalidadesPendentes, 2, ',', '.') ?> €</p>
            </div>
            <div class="stat-card red">
                <p class="stat-label">Mensalidades Atrasadas</p>
                <p class="stat-value"><?= number_format($valorMensalidadesAtrasadas, 2, ',', '.') ?> €</p>
                <p style="font-size: 12px; opacity: 0.9; margin-top: 4px;">
                    <?= $totalMensalidadesAtrasadas ?> mensalidade(s)
                </p>
            </div>
        </div>
    </div>

    <!-- Formulário Nova Receita -->
    <div id="formNovaReceita" class="form-section">
        <h2>Adicionar Nova Receita</h2>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label">Descrição</label>
                    <input type="text" name="descricao" class="form-input" placeholder="Ex: Quotas Sócios, Patrocínio, etc." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Valor (€)</label>
                    <input type="number" name="valor" step="0.01" class="form-input" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Data</label>
                    <input type="date" name="data" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria</label>
                    <select name="categoria" class="form-select" required>
                        <option value="Quotas Sócios">Quotas Sócios</option>
                        <option value="Patrocínios">Patrocínios</option>
                        <option value="Vendas">Vendas</option>
                        <option value="Eventos">Eventos</option>
                        <option value="Subsídios">Subsídios</option>
                        <option value="Mensalidade">Mensalidade</option>
                        <option value="Outras">Outras</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select" required>
                        <option value="Única">Única</option>
                        <option value="Mensal">Mensal</option>
                    </select>
                </div>
            </div>
            <div class="form-buttons">
                <button type="submit" name="adicionar" class="btn-submit">Adicionar Receita</button>
                <button type="button" onclick="toggleForm()" class="btn-cancel">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- Filtros -->
    <div class="filter-section">
        <p class="filter-label">Filtrar por categoria:</p>
        <div class="filter-buttons">
            <?php foreach ($categorias as $cat): ?>
                <a href="?categoria=<?= urlencode($cat) ?>&mes=<?= urlencode($mesAtual) ?>" 
                   class="filter-btn <?= $filtroCategoria === $cat ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-section">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Tipo / Status</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receitasFiltradas)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class='bx bx-receipt'></i>
                                    <p>Nenhuma receita encontrada</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($receitasFiltradas as $r):
                            $isMensalidade = ($r['origem'] ?? 'receita') === 'mensalidade';
                            $status = $r['status'] ?? null;

                            // Badge estado
                            if ($isMensalidade) {
                                switch ($status) {
                                    case 'pago':
                                        $estadoBadgeClass = 'badge-green';
                                        $estadoIcon       = 'bx-check-circle';
                                        $estadoLabel      = 'Pago';
                                        $valorClass       = 'valor-receita';
                                        break;
                                    case 'atrasado':
                                        $estadoBadgeClass = 'badge-red';
                                        $estadoIcon       = 'bx-error-circle';
                                        $estadoLabel      = 'Atrasado';
                                        $valorClass       = 'valor-atrasado';
                                        break;
                                    default:
                                        $estadoBadgeClass = 'badge-yellow';
                                        $estadoIcon       = 'bx-time-five';
                                        $estadoLabel      = 'Pendente';
                                        $valorClass       = 'valor-pendente';
                                }
                            } else {
                                $estadoBadgeClass = 'badge-green';
                                $estadoIcon       = 'bx-check-circle';
                                $estadoLabel      = 'Pago';
                                $valorClass       = 'valor-receita';
                            }

                            // Badge tipo
                            $tipo = $r['tipo'] ?? 'Única';
                            $tipoBadgeClass = ($tipo === 'Mensal') ? 'badge-purple' : 'badge-gray';
                        ?>
                        <tr>
                            <td data-label="Descrição"><?= htmlspecialchars($r['descricao']) ?></td>
                            <td data-label="Categoria">
                                <span class="badge badge-blue"><?= htmlspecialchars($r['categoria']) ?></span>
                            </td>
                            <td data-label="Tipo / Status">
                                <span class="badge <?= $tipoBadgeClass ?>"><?= htmlspecialchars($tipo) ?></span>
                            </td>
                            <td data-label="Data"><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                            <td data-label="Valor">
                                <span class="<?= $valorClass ?>"><?= number_format($r['valor'], 2, ',', '.') ?> €</span>
                            </td>
                            <td data-label="Estado">
                                <span class="badge <?= $estadoBadgeClass ?>">
                                    <i class='bx <?= $estadoIcon ?>'></i>
                                    <?= $estadoLabel ?>
                                </span>
                            </td>
                            <td data-label="Ações">
                                <div class="acoes-cell">
                                    <?php if ($isMensalidade): ?>
                                        <button type="button" class="btn-edit"
                                                onclick="openEditModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['descricao'], ENT_QUOTES) ?>', '<?= $status ?? 'pendente' ?>')"
                                                title="Editar Status">
                                            <i class='bx bx-edit-alt'></i>
                                        </button>
                                        <button type="button" class="btn-delete"
                                                onclick="alert('Mensalidades não podem ser eliminadas diretamente!')"
                                                title="Protegido">
                                            <i class='bx bx-lock-alt'></i>
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="display: contents;" class="form-eliminar-receita">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="origem" value="<?= $r['origem'] ?? 'receita' ?>">
                                            <input type="hidden" name="descricao" value="<?= htmlspecialchars($r['descricao']) ?>">
                                            <button type="button" class="btn-delete" title="Eliminar">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Editar Status -->
<div id="modalEditStatus" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <i class='bx bx-edit-alt'></i>
            <span>Editar Status da Mensalidade</span>
        </div>
        <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;" id="modalDescricao"></p>
        
        <form method="POST" id="formEditStatus">
            <input type="hidden" name="mensalidade_id" id="mensalidade_id">
            
            <div class="status-options">
                <label class="status-option pendente">
                    <input type="radio" name="novo_status" value="pendente" required>
                    <div>
                        <div style="font-weight: 600; color: #d97706;">
                            <i class='bx bx-time-five'></i> PENDENTE
                        </div>
                        <div style="font-size: 12px; color: #78716c;">Aguardando pagamento</div>
                    </div>
                </label>
                
                <label class="status-option atrasado">
                    <input type="radio" name="novo_status" value="atrasado" required>
                    <div>
                        <div style="font-weight: 600; color: #dc2626;">
                            <i class='bx bx-error-circle'></i> ATRASADO
                        </div>
                        <div style="font-size: 12px; color: #78716c;">Pagamento vencido</div>
                    </div>
                </label>
                
                <label class="status-option pago">
                    <input type="radio" name="novo_status" value="pago" required>
                    <div>
                        <div style="font-weight: 600; color: #16a34a;">
                            <i class='bx bx-check-circle'></i> PAGO
                        </div>
                        <div style="font-size: 12px; color: #78716c;">Pagamento confirmado</div>
                    </div>
                </label>
            </div>
            
            <div class="modal-buttons">
                <button type="submit" name="atualizar_status" class="btn-submit">
                    <i class='bx bx-save'></i> Salvar
                </button>
                <button type="button" onclick="closeEditModal()" class="btn-cancel">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleForm() {
        document.getElementById('formNovaReceita').classList.toggle('active');
    }

    function mudarMes() {
        const mes = document.getElementById('mesSelecionado').value;
        window.location = '?mes=' + mes + '<?= isset($_GET['categoria']) ? '&categoria=' . urlencode($_GET['categoria']) : '' ?>';
    }

    function openEditModal(id, descricao, statusAtual) {
        document.getElementById('mensalidade_id').value = id;
        document.getElementById('modalDescricao').textContent = descricao;

        document.querySelectorAll('input[name="novo_status"]').forEach(radio => {
            radio.checked = (radio.value === statusAtual);
        });

        document.getElementById('modalEditStatus').classList.add('active');
    }

    function closeEditModal() {
        document.getElementById('modalEditStatus').classList.remove('active');
    }

    document.getElementById('modalEditStatus').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            const form = this.closest('form');
            if (!form || !form.classList.contains('form-eliminar-receita')) return;

            const receitaId = form.querySelector('input[name="id"]').value;
            const receitaDescricao = form.querySelector('input[name="descricao"]').value;

            toast.confirm({
                type: 'warning',
                title: 'Eliminar Receita?',
                message: `Tem certeza que deseja eliminar "${receitaDescricao}"? Esta ação não pode ser desfeita.`,
                confirmText: 'Eliminar',
                cancelText: 'Cancelar',
                onConfirm: () => {
                    toast.info('A eliminar...', 'Aguarde um momento');
                    const fd = new FormData(form);
                    fd.append('eliminar', '1');
                    fetch(window.location.pathname, { method: 'POST', body: fd })
                        .then(r => { if (!r.ok) throw new Error(); return r.text(); })
                        .then(() => {
                            toast.success('Eliminado!', `"${receitaDescricao}" foi eliminada`);
                            setTimeout(() => location.reload(), 1500);
                        })
                        .catch(() => toast.error('Erro!', 'Erro ao eliminar. Tente novamente.'));
                }
            });
        });
    });
</script>
<script src="../../toast.js"></script>
</body>
</html>