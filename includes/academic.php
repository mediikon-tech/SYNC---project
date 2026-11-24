<?php
declare(strict_types=1);

/** Return the academic session and semester selected by the administrator. */
function sync_current_academic_term(PDO $pdo): array
{
    $query = $pdo->query('SELECT academic_session, semester FROM academic_settings WHERE id = 1 LIMIT 1');
    $term = $query->fetch();
    if (!$term) {
        $pdo->exec("INSERT IGNORE INTO academic_settings (id, academic_session, semester) VALUES (1, '2026/2027', 1)");
        $term = $pdo->query('SELECT academic_session, semester FROM academic_settings WHERE id = 1 LIMIT 1')->fetch();
    }
    return [
        'academic_session' => (string) ($term['academic_session'] ?? '2026/2027'),
        'semester' => (int) ($term['semester'] ?? 1),
    ];
}
