<?php
/**
 * One-time "flash" messages shown after a redirect (e.g. "Article saved.").
 */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_success(string $message): void { flash_set('success', $message); }
function flash_error(string $message): void { flash_set('error', $message); }

/** Outputs and clears all pending flash messages. Call once near the top of a page template. */
function flash_render(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $flash) {
        $class = $flash['type'] === 'error' ? 'alert alert--error' : 'alert alert--success';
        echo '<div class="' . $class . '" role="alert">' . e($flash['message']) . '</div>';
    }
    unset($_SESSION['flash']);
}
