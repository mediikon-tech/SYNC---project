<?php
declare(strict_types=1);

/**
 * Escape dynamic values before displaying them in HTML.
 * Use prepared statements for SQL values; this helper is for HTML output only.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
