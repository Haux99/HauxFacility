<?php
// send-mail.php
header('Content-Type: application/json; charset=utf-8');

// === config ===
$to = 'info@hauxfacility.de';
$max_length = 10000; // safety
// ==============

// helper: sanitize and prevent header injection
function clean($value) {
    $v = trim($value);
    // strip tags
    $v = strip_tags($v);
    // remove typical header injection attempts
    $v = preg_replace("/[\r\n]+/", " ", $v);
    // limit length
    return mb_substr($v, 0, 10000);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false, 'message'=>'Method not allowed']);
    exit;
}

// Read form_type to distinguish forms
$form_type = isset($_POST['form_type']) ? clean($_POST['form_type']) : 'unknown';

try {
    // Build subject and body depending on form_type
    if ($form_type === 'contact') {
        $name = isset($_POST['name']) ? clean($_POST['name']) : '';
        $email = isset($_POST['email']) ? clean($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? clean($_POST['phone']) : '';
        $package = isset($_POST['package']) ? clean($_POST['package']) : '';
        $message = isset($_POST['message']) ? clean($_POST['message']) : '';

        $subject = "Kontaktanfrage: " . ($name ?: 'Unbekannt');
        $body = "Neue Kontaktanfrage via Kontaktformular\n\n";
        $body .= "Name: $name\n";
        $body .= "E-Mail: $email\n";
        $body .= "Telefon: $phone\n";
        $body .= "Interesse an Paket: $package\n\n";
        $body .= "Nachricht:\n$message\n";

        $replyTo = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

    } elseif ($form_type === 'booking') {
        $name = isset($_POST['b_name']) ? clean($_POST['b_name']) : '';
        $email = isset($_POST['b_email']) ? clean($_POST['b_email']) : '';
        $phone = isset($_POST['b_phone']) ? clean($_POST['b_phone']) : '';
        $package = isset($_POST['b_package']) ? clean($_POST['b_package']) : '';
        $note = isset($_POST['b_note']) ? clean($_POST['b_note']) : '';

        $subject = "Buchungsanfrage: " . ($name ?: 'Unbekannt');
        $body = "Neue Buchungsanfrage via Beratungs-Modal\n\n";
        $body .= "Name: $name\n";
        $body .= "E-Mail: $email\n";
        $body .= "Telefon: $phone\n";
        $body .= "Interesse an Paket: $package\n\n";
        $body .= "Notiz:\n$note\n";

        $replyTo = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    } else {
        // Generic fallback: include all posted fields
        $subject = "Formular-Anfrage";
        $body = "Es wurde ein Formular abgeschickt:\n\n";
        foreach ($_POST as $k => $v) {
            $body .= "$k: " . clean($v) . "\n";
        }
        $replyTo = null;
    }

    // Prepare headers
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/plain; charset=utf-8';
    // From: use a neutral noreply from your domain to avoid spoofing issues
    $serverName = $_SERVER['SERVER_NAME'] ?? 'hauxfacility.de';
    $from = "no-reply@" . preg_replace('/^www\./', '', $serverName);
    $headers[] = 'From: HauxFacility <' . $from . '>';
    if ($replyTo) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    // Wordwrap body for safety
    $body = wordwrap($body, 70, "\r\n");

    // Send
    $sent = @mail($to, $subject, $body, implode("\r\n", $headers));

    if ($sent) {
        echo json_encode(['success'=>true]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'Mail konnte nicht gesendet werden (mail()).']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'Server-Fehler: '.$e->getMessage()]);
    exit;
}
