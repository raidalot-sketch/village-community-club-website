<?php
/**
 * CSRF protection.
 *
 * Every admin form must include csrf_field() and every handler that
 * processes POST data must call csrf_verify() before doing anything else.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Echoes a hidden input field. Use inside <form>...</form>. */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the top of any POST handler. Stops the request with a 403 on mismatch. */
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Your session has expired or this form was submitted incorrectly. Please go back, refresh the page, and try again.');
    }
}
