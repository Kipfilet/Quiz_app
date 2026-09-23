<?php

require __DIR__ . '/bootstrap.php';

require_method('POST');

$body = json_body();
$email = trim((string) ($body['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond_error('Enter a valid email address.', 422);
}

// No mail server is configured for this app, so there's nothing to actually
// send. Respond with the same generic message whether or not the address is
// registered, so this endpoint can't be used to check who has an account.
respond(['ok' => true, 'message' => 'If an account exists for that email, password reset instructions have been sent.']);
