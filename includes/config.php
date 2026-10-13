<?php
// Fuso horário do Brasil — forçado independente do php.ini
date_default_timezone_set('America/Sao_Paulo');

function getConfig(string $chave, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = getPDO()->query('SELECT chave, valor FROM configuracoes')->fetchAll();
            foreach ($rows as $r) $cache[$r['chave']] = $r['valor'];
        } catch (Exception $e) {}
    }
    return $cache[$chave] ?? $default;
}

function setConfig(string $chave, string $valor): void {
    $stmt = getPDO()->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
    $stmt->execute([$chave, $valor]);
}

// Converte "HH:MM" em minutos desde meia-noite (00:00 = 1440 quando usado como fechamento)
function horaParaMinutos(string $hhmm, bool $fechamento = false): int {
    $partes = explode(':', $hhmm);
    $min = (int)($partes[0] ?? 0) * 60 + (int)($partes[1] ?? 0);
    // 00:00 como hora de fechamento significa "meia-noite do fim do dia" = 1440 min
    if ($fechamento && $min === 0) $min = 1440;
    return $min;
}

function estaAberto(): bool {
    // Override manual: admin fechou a loja na força
    if (getConfig('pizzaria_aberta', '1') === '0') return false;

    $horariosSemana = getConfig('horarios_semana', '');
    if ($horariosSemana) {
        $dias = json_decode($horariosSemana, true);
        $diaSemana = (int) date('w'); // 0=Dom … 6=Sáb
        $hoje = $dias[$diaSemana] ?? null;
        if (!$hoje || empty($hoje['ativo'])) return false;

        $minAbre  = horaParaMinutos($hoje['abre'],  false);
        $minFecha = horaParaMinutos($hoje['fecha'],  true);
        $minAgora = horaParaMinutos(date('H:i'),     false);

        if ($minAbre < $minFecha) return $minAgora >= $minAbre && $minAgora < $minFecha;
        return $minAgora >= $minAbre || $minAgora < $minFecha;
    }

    // Fallback legado (hora_abertura / hora_fechamento globais)
    $minAbre  = horaParaMinutos(getConfig('hora_abertura',   '18:00'), false);
    $minFecha = horaParaMinutos(getConfig('hora_fechamento', '23:00'), true);
    $minAgora = horaParaMinutos(date('H:i'), false);

    if ($minAbre < $minFecha) return $minAgora >= $minAbre && $minAgora < $minFecha;
    return $minAgora >= $minAbre || $minAgora < $minFecha;
}

// Retorna a hora atual do servidor (para debug no admin)
function horaAtualServidor(): string {
    return date('H:i') . ' (' . date_default_timezone_get() . ')';
}
