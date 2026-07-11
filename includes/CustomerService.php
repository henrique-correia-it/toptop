<?php
// includes/CustomerService.php

if (!function_exists('customer_clean_email')) {
    function customer_clean_email(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}

if (!function_exists('customer_find_by_email')) {
    function customer_find_by_email(mysqli $conn, string $email): ?array
    {
        $email = customer_clean_email($email);
        $stmt = $conn->prepare("SELECT * FROM clientes WHERE email = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $customer ?: null;
    }
}

if (!function_exists('customer_find_by_id')) {
    function customer_find_by_id(mysqli $conn, int $customerId): ?array
    {
        $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $customer ?: null;
    }
}

if (!function_exists('customer_create')) {
    function customer_create(mysqli $conn, string $name, string $email, string $password, ?string $phone = null, ?string $nif = null): int
    {
        $email = customer_clean_email($email);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "INSERT INTO clientes (nome, email, telefone, nif, password_hash, data_criacao)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException('Erro interno ao preparar cliente: ' . $conn->error);
        }
        $stmt->bind_param('ssssss', $name, $email, $phone, $nif, $hash, $now);
        $stmt->execute();
        $id = (int)$conn->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('customer_addresses')) {
    function customer_addresses(mysqli $conn, int $customerId): array
    {
        $stmt = $conn->prepare(
            "SELECT *
             FROM cliente_moradas
             WHERE cliente_id = ?
             ORDER BY principal DESC, id DESC"
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('customer_default_address')) {
    function customer_default_address(mysqli $conn, int $customerId): ?array
    {
        $addresses = customer_addresses($conn, $customerId);
        return $addresses[0] ?? null;
    }
}

if (!function_exists('customer_save_address')) {
    function customer_save_address(mysqli $conn, int $customerId, array $data, bool $principal = false): int
    {
        if ($principal) {
            $stmtReset = $conn->prepare("UPDATE cliente_moradas SET principal = 0 WHERE cliente_id = ?");
            $stmtReset->bind_param('i', $customerId);
            $stmtReset->execute();
            $stmtReset->close();
        }

        $now = date('Y-m-d H:i:s');
        $name = trim($data['nome_morada'] ?? $data['nome'] ?? '');
        $phone = trim($data['telefone'] ?? '');
        $nif = trim($data['nif'] ?? '');
        $country = strtoupper(trim($data['pais_regiao'] ?? 'PT'));
        $street = trim($data['rua'] ?? '');
        $postal = trim($data['codigo_postal'] ?? '');
        $city = trim($data['localidade'] ?? '');
        $state = trim($data['provincia'] ?? '');
        $principalValue = $principal ? 1 : 0;

        if ($street === '' || $postal === '' || $city === '') {
            throw new InvalidArgumentException('Preencha a rua, codigo postal e localidade da morada.');
        }

        $stmt = $conn->prepare(
            "INSERT INTO cliente_moradas
                (cliente_id, nome, telefone, nif, pais_regiao, rua, codigo_postal, localidade, provincia, principal, data_criacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException('Erro interno ao guardar morada: ' . $conn->error);
        }
        $stmt->bind_param(
            'issssssssis',
            $customerId,
            $name,
            $phone,
            $nif,
            $country,
            $street,
            $postal,
            $city,
            $state,
            $principalValue,
            $now
        );
        $stmt->execute();
        $id = (int)$conn->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('customer_orders')) {
    function customer_orders(mysqli $conn, int $customerId, int $limit = 50): array
    {
        $stmt = $conn->prepare(
            "SELECT id, token, data_encomenda, estado, total, portes_envio, metodo_entrega, metodo_pagamento, codigo_tracking
             FROM encomendas
             WHERE cliente_id = ? AND estado != 'incompleta'
             ORDER BY data_encomenda DESC
             LIMIT ?"
        );
        if (!$stmt) return [];
        $stmt->bind_param('ii', $customerId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('customer_create_reset_token')) {
    function customer_create_reset_token(mysqli $conn, int $customerId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $now = date('Y-m-d H:i:s');

        $stmtDelete = $conn->prepare("DELETE FROM cliente_password_resets WHERE cliente_id = ? AND usado_em IS NULL");
        $stmtDelete->bind_param('i', $customerId);
        $stmtDelete->execute();
        $stmtDelete->close();

        $stmt = $conn->prepare(
            "INSERT INTO cliente_password_resets (cliente_id, token_hash, expira_em, data_criacao)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('isss', $customerId, $hash, $expires, $now);
        $stmt->execute();
        $stmt->close();

        return $token;
    }
}

if (!function_exists('customer_create_email_verification_token')) {
    function customer_create_email_verification_token(mysqli $conn, int $customerId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 86400); // 24 horas
        $now = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("DELETE FROM cliente_email_verifications WHERE cliente_id = ?");
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO cliente_email_verifications (cliente_id, token_hash, expira_em, data_criacao)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('isss', $customerId, $hash, $expires, $now);
        $stmt->execute();
        $stmt->close();

        return $token;
    }
}

if (!function_exists('customer_send_email')) {
    function customer_send_email(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
    {
        require_once __DIR__ . '/../phpmailer/src/Exception.php';
        require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../phpmailer/src/SMTP.php';

        $envPath = __DIR__ . '/../.env';
        $env = file_exists($envPath) ? parse_ini_file($envPath) : [];

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $env['MAIL_HOST'] ?? 'mail.toptop.pt';
        $mail->SMTPAuth = true;
        $mail->Username = $env['MAIL_USER'] ?? '';
        $mail->Password = $env['MAIL_PASS'] ?? '';
        $mail->Port = (int)($env['MAIL_PORT'] ?? 465);
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 20;

        $enc = strtolower($env['MAIL_ENCRYPTION'] ?? 'ssl');
        if ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($enc === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        $from = $env['MAIL_FROM_ADDRESS'] ?? 'noreply@toptop.pt';
        $fromName = $env['MAIL_FROM_NAME'] ?? 'TopTop';
        $replyTo = $env['MAIL_REPLY_TO_ADDRESS'] ?? $from;
        $replyToName = $env['MAIL_REPLY_TO_NAME'] ?? $fromName;

        $mail->setFrom($from, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($replyTo, $replyToName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        $mail->send();
    }
}
