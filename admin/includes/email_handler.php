<?php
// admin/includes/email_handler.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../phpmailer/src/Exception.php';
require_once __DIR__ . '/../../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../config/formatters.php';
require_once __DIR__ . '/../../config/header_functions.php';

// Instância SMTP partilhada para toda a execução do script.
// SMTPKeepAlive mantém a ligação aberta entre envios, evitando
// que o servidor interprete múltiplas ligações rápidas como spam.
$_smtp_mailer_instance = null;
$_smtp_shutdown_registered = false;

function _resetSmtpMailer() {
    global $_smtp_mailer_instance;
    if ($_smtp_mailer_instance !== null) {
        try {
            $_smtp_mailer_instance->smtpClose();
        } catch (\Throwable $e) {
            // Ignora erro ao fechar ligação caída
        }
        $_smtp_mailer_instance = null;
    }
}

function _getSmtpMailer() {
    global $_smtp_mailer_instance, $_smtp_shutdown_registered;

    $env_path = __DIR__ . '/../../.env';
    $env_vars = file_exists($env_path) ? parse_ini_file($env_path, false, INI_SCANNER_RAW) : [];
    if (!is_array($env_vars)) {
        $env_vars = [];
    }

    if ($_smtp_mailer_instance !== null) {
        // Limpa destinatários, cabeçalhos personalizados e anexos do envio anterior
        $_smtp_mailer_instance->clearAddresses();
        $_smtp_mailer_instance->clearCCs();
        $_smtp_mailer_instance->clearBCCs();
        $_smtp_mailer_instance->clearReplyTos();
        $_smtp_mailer_instance->clearAttachments();
        $_smtp_mailer_instance->clearCustomHeaders();
        return [$_smtp_mailer_instance, $env_vars];
    }

    if (!$_smtp_shutdown_registered) {
        register_shutdown_function('_resetSmtpMailer');
        $_smtp_shutdown_registered = true;
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPKeepAlive = true;
    $mail->Host     = $env_vars['MAIL_HOST'] ?? 'mail.toptop.pt';
    $mail->SMTPAuth = true;
    $mail->Username = $env_vars['MAIL_USER'] ?? 'noreply@toptop.pt';
    $mail->Password = $env_vars['MAIL_PASS'] ?? '';
    $mail->Port     = (int)($env_vars['MAIL_PORT'] ?? 465);
    $mail->CharSet  = 'UTF-8';
    $mail->Timeout  = 60;

    $enc = strtolower($env_vars['MAIL_ENCRYPTION'] ?? 'ssl');
    if ($enc === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($enc === 'none') {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    $_smtp_mailer_instance = $mail;
    return [$mail, $env_vars];
}

function enviarEmailEncomenda($tipo, $dados) {
    global $conn;

    [$mail, $env_vars] = _getSmtpMailer();

    try {
        $from_address     = $env_vars['MAIL_FROM_ADDRESS'] ?? 'noreply@toptop.pt';
        $from_name        = $env_vars['MAIL_FROM_NAME']    ?? 'TopTop';
        $reply_to_address = $env_vars['MAIL_REPLY_TO_ADDRESS'] ?? $from_address;
        $reply_to_name    = $env_vars['MAIL_REPLY_TO_NAME']    ?? $from_name;

        $mail->setFrom($from_address, $from_name);
        $mail->addAddress($dados['cliente_email'], $dados['cliente_nome']);
        $mail->addReplyTo($reply_to_address, $reply_to_name);
        $mail->isHTML(true);


        $assunto          = '';
        $corpo_email_bruto = '';

        if ($tipo === 'personalizado' || !empty($dados['corpo_editado'])) {
            $assunto           = str_replace(["\r", "\n"], ' ', (string)($dados['assunto_email'] ?? ''));
            $corpo_email_bruto = htmlspecialchars((string)($dados['mensagem_para_cliente'] ?? ''), ENT_QUOTES, 'UTF-8', false);
        } else {
            // Buscar template na base de dados
            $template_key = str_replace(' ', '_', $tipo);
            $stmt_tpl = $conn->prepare("SELECT subject, body FROM email_templates WHERE template_key = ?");
            $stmt_tpl->bind_param("s", $template_key);
            $stmt_tpl->execute();
            $res_tpl = $stmt_tpl->get_result();
            
            if ($res_tpl->num_rows === 0) {
                $stmt_tpl->close();
                throw new Exception("Template de email '{$template_key}' não encontrado na base de dados.");
            }
            
            $template_data = $res_tpl->fetch_assoc();
            $assunto = $template_data['subject'];
            $corpo_email_bruto = $template_data['body'];
            $stmt_tpl->close();
        }

        $base_url = rtrim($env_vars['APP_URL'] ?? 'https://toptop.pt', '/');
        $link_encomenda = $base_url . '/estado_encomenda.php?id=' . (int)($dados['id'] ?? 0) . '&token=' . urlencode((string)($dados['token'] ?? ''));

        $total_produtos = (float)($dados['total'] ?? 0);
        $metodo_entrega = $dados['metodo_entrega'] ?? 'envio';
        $portes         = ($metodo_entrega === 'recolha' || $metodo_entrega === 'levantamento') ? 0.0 : (float)($dados['portes_envio'] ?? 0);
        $total_final    = $total_produtos + $portes;

        $lista_produtos_html = '';
        if (strpos($corpo_email_bruto, '{lista_produtos}') !== false) {
            $stmt_itens = $conn->prepare("SELECT * FROM encomenda_itens WHERE encomenda_id = ?");
            $stmt_itens->bind_param("i", $dados['id']);
            $stmt_itens->execute();
            $itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_itens->close();

            $lista_produtos_html = "<table border='0' cellpadding='5' cellspacing='0' width='100%' style='border-collapse:collapse;margin-top:15px;'>";
            foreach ($itens as $item) {
                $detalhes     = json_decode((string)($item['selecoes_atributos'] ?? ''), true);
                $detalhes_str = !empty($detalhes)
                    ? "<br><small style='color:#777;'>" . implode(' / ', array_map('htmlspecialchars', $detalhes)) . "</small>"
                    : '';
                $lista_produtos_html .= "<tr style='border-bottom:1px solid #eee;'>"
                    . "<td style='padding:10px 0;'>" . htmlspecialchars($item['nome_produto'])
                    . " (x" . $item['quantidade'] . ")" . $detalhes_str . "</td>"
                    . "<td align='right'>" . format_money($item['preco_unitario'] * $item['quantidade']) . "</td>"
                    . "</tr>";
            }
            $lista_produtos_html .= "</table>";
        }

        $placeholders = [
            '{nome_cliente}'        => htmlspecialchars($dados['cliente_nome']),
            '{id_encomenda}'        => htmlspecialchars((string)$dados['id']),
            '{total_final}'         => format_money($total_final),
            '{subtotal_produtos}'   => format_money($total_produtos),
            '{portes_envio}'        => format_money($portes),
            '{lista_produtos}'      => $lista_produtos_html,
            '{metodo_pagamento}'    => htmlspecialchars($dados['metodo_pagamento'] ?? 'N/D'),
            '{link_acompanhamento}' => $link_encomenda,
            '{codigo_tracking}'     => htmlspecialchars($dados['codigo_tracking'] ?? 'N/A'),
        ];

        $corpo_processado = str_replace(array_keys($placeholders), array_values($placeholders), $corpo_email_bruto);
        $assunto_final    = str_replace(array_keys($placeholders), array_values($placeholders), $assunto);
        $corpo_html_inner = nl2br($corpo_processado);

        $mail->Subject = $assunto_final;

        $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../');
        $logo_path_web = getHeaderLogo('/public/assets/logo1.jpg');
        $caminho_logo = rtrim($doc_root, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logo_path_web), DIRECTORY_SEPARATOR);
        if (file_exists($caminho_logo)) {
            $mail->addEmbeddedImage($caminho_logo, 'logo_toptop');
        }

        $footer_html = ($tipo === 'personalizado')
            ? "<div class='footer'><p>Para qualquer questão, pode responder diretamente a este email.</p></div>"
            : "<div class='footer'><p>Para qualquer questão, responda a este email.<br>Acompanhe a sua encomenda <a href='{$link_encomenda}' style='color:#555;'>clicando aqui</a>.</p></div>";

        $mail->Body = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
            body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f7f7f7;margin:0;padding:20px}
            .container{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 4px 15px rgba(0,0,0,.05)}
            .header{text-align:center;margin-bottom:25px;border-bottom:1px solid #eee;padding-bottom:20px}
            .header img{max-width:120px}
            .footer{font-size:.9em;color:#999;text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
        </style></head><body><div class='container'>
            <div class='header'><img src='cid:logo_toptop' alt='TopTop'></div>
            {$corpo_html_inner}
            {$footer_html}
        </div></body></html>";

        $mail->send();

        // Guardar email no histórico da encomenda
        $encomenda_id = (int)($dados['id'] ?? 0);
        if ($encomenda_id > 0 && isset($conn)) {
            $stmt_get = $conn->prepare("SELECT mensagens_cliente FROM encomendas WHERE id = ?");
            $stmt_get->bind_param("i", $encomenda_id);
            $stmt_get->execute();
            $result = $stmt_get->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $mensagens = json_decode($row['mensagens_cliente'] ?? '[]', true);
                if (!is_array($mensagens)) $mensagens = [];
                $mensagens[] = [
                    'data'     => date('Y-m-d H:i:s'),
                    'tipo'     => 'email',
                    'assunto'  => $assunto_final,
                    'mensagem' => $corpo_html_inner,
                ];
                $stmt_up = $conn->prepare("UPDATE encomendas SET mensagens_cliente = ? WHERE id = ?");
                $json_msgs = json_encode($mensagens, JSON_UNESCAPED_UNICODE);
                $stmt_up->bind_param("si", $json_msgs, $encomenda_id);
                $stmt_up->execute();
                $stmt_up->close();
            }
            $stmt_get->close();
        }

        return ['assunto' => $assunto_final, 'corpo' => $corpo_html_inner];

    } catch (Exception $e) {
        log_email("PHPMailer falhou (tipo:{$tipo}): {$mail->ErrorInfo}", 'email_handler.php');
        error_log("PHPMailer (tipo:{$tipo}) falhou: {$mail->ErrorInfo}");
        _resetSmtpMailer();
        throw new Exception("Envio de email falhou.");
    }
}

function enviarEmailNotificacaoLoja($tipo, $dados) {
    global $conn;
    [$mail, $env_vars] = _getSmtpMailer();

    try {
        $from_address  = $env_vars['MAIL_FROM_ADDRESS'] ?? 'noreply@toptop.pt';
        $from_name     = $env_vars['MAIL_FROM_NAME']    ?? 'TopTop';
        $store_address = $env_vars['MAIL_STORE_ADDRESS'] ?? ($env_vars['MAIL_REPLY_TO_ADDRESS'] ?? 'toptopclothingstore@gmail.com');

        $mail->setFrom($from_address, $from_name);
        $mail->addAddress($store_address, 'TopTop Loja');
        $mail->isHTML(true);

        // Buscar template na base de dados
        $stmt_tpl = $conn->prepare("SELECT subject, body FROM email_templates WHERE template_key = ?");
        $stmt_tpl->bind_param("s", $tipo);
        $stmt_tpl->execute();
        $res_tpl = $stmt_tpl->get_result();
        
        if ($res_tpl->num_rows === 0) {
            $stmt_tpl->close();
            log_email("Template '{$tipo}' não encontrado na base de dados.", 'enviarEmailNotificacaoLoja');
            error_log("enviarEmailNotificacaoLoja: template '{$tipo}' não encontrado na base de dados.");
            return;
        }
        
        $template_data = $res_tpl->fetch_assoc();
        $assunto_raw   = $template_data['subject'];
        $corpo_raw     = $template_data['body'];
        $stmt_tpl->close();

        $app_url = rtrim($env_vars['APP_URL'] ?? 'https://toptop.pt', '/');

        $placeholders = [
            '{id_encomenda}'     => htmlspecialchars((string)($dados['id'] ?? '')),
            '{nome_cliente}'     => htmlspecialchars($dados['nome_cliente'] ?? ''),
            '{email_cliente}'    => htmlspecialchars($dados['email_cliente'] ?? ''),
            '{total_final}'      => format_money((float)($dados['total_final'] ?? 0)),
            '{metodo_pagamento}' => htmlspecialchars($dados['metodo_pagamento'] ?? ''),
            '{nome_remetente}'   => htmlspecialchars($dados['nome_remetente'] ?? ''),
            '{email_remetente}'  => htmlspecialchars($dados['email_remetente'] ?? ''),
            '{mensagem}'         => htmlspecialchars($dados['mensagem'] ?? ''),
            '{link_admin}'       => $app_url . '/admin/',
        ];

        $assunto_final    = str_replace(array_keys($placeholders), array_values($placeholders), $assunto_raw);
        $corpo_processado = str_replace(array_keys($placeholders), array_values($placeholders), $corpo_raw);
        $corpo_html_inner = nl2br($corpo_processado);

        $mail->Subject = $assunto_final;

        $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../');
        $logo_path_web = getHeaderLogo('/public/assets/logo1.jpg');
        $caminho_logo = rtrim($doc_root, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logo_path_web), DIRECTORY_SEPARATOR);
        if (file_exists($caminho_logo)) {
            $mail->addEmbeddedImage($caminho_logo, 'logo_toptop');
        }

        $mail->Body = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
            body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f7f7f7;margin:0;padding:20px}
            .container{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 4px 15px rgba(0,0,0,.05)}
            .header{text-align:center;margin-bottom:25px;border-bottom:1px solid #eee;padding-bottom:20px}
            .header img{max-width:120px}
            .footer{font-size:.9em;color:#999;text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
        </style></head><body><div class='container'>
            <div class='header'><img src='cid:logo_toptop' alt='TopTop'></div>
            {$corpo_html_inner}
            <div class='footer'><p>Esta é uma notificação automática do site TopTop.</p></div>
        </div></body></html>";

        $mail->send();

    } catch (Exception $e) {
        log_email("Notificação loja falhou (tipo:{$tipo}): {$mail->ErrorInfo}", 'email_handler.php');
        error_log("Notificação loja (tipo:{$tipo}) falhou: {$mail->ErrorInfo}");
        _resetSmtpMailer();
    }
}
function enviarEmailTemplate($template_key, $to_email, $to_name, $placeholders = []) {
    global $conn;
    [$mail, $env_vars] = _getSmtpMailer();

    try {
        $from_address = $env_vars['MAIL_FROM_ADDRESS'] ?? 'noreply@toptop.pt';
        $from_name    = $env_vars['MAIL_FROM_NAME']    ?? 'TopTop';
        $reply_to     = $env_vars['MAIL_REPLY_TO_ADDRESS'] ?? $from_address;

        $mail->setFrom($from_address, $from_name);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo($reply_to, $from_name);
        $mail->isHTML(true);

        // Buscar template
        $stmt = $conn->prepare("SELECT subject, body FROM email_templates WHERE template_key = ?");
        $stmt->bind_param("s", $template_key);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            $stmt->close();
            throw new Exception("Template '{$template_key}' não encontrado.");
        }
        
        $tpl = $res->fetch_assoc();
        $assunto = $tpl['subject'];
        $corpo   = $tpl['body'];
        $stmt->close();

        // Substituir placeholders (texto limpo no Subject; escapado com ENT_QUOTES sem double-encode no corpo HTML)
        $placeholders_subject = [];
        $placeholders_html = [];
        foreach ($placeholders as $k => $v) {
            $strVal = (string)$v;
            $placeholders_subject[$k] = str_replace(["\r", "\n"], ' ', strip_tags($strVal));
            $placeholders_html[$k]    = htmlspecialchars($strVal, ENT_QUOTES, 'UTF-8', false);
        }

        $assunto_final    = str_replace(array_keys($placeholders_subject), array_values($placeholders_subject), $assunto);
        $corpo_processado = str_replace(array_keys($placeholders_html), array_values($placeholders_html), $corpo);
        $corpo_html_inner = nl2br($corpo_processado);

        $mail->Subject = $assunto_final;

        $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../');
        $logo_path_web = getHeaderLogo('/public/assets/logo1.jpg');
        $caminho_logo = rtrim($doc_root, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logo_path_web), DIRECTORY_SEPARATOR);

        if (file_exists($caminho_logo)) {
            $mail->addEmbeddedImage($caminho_logo, 'logo_toptop');
        }

        $mail->Body = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
            body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f7f7f7;margin:0;padding:20px}
            .container{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 4px 15px rgba(0,0,0,.05)}
            .header{text-align:center;margin-bottom:25px;border-bottom:1px solid #eee;padding-bottom:20px}
            .header img{max-width:120px}
            .footer{font-size:.9em;color:#999;text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid #eee}
        </style></head><body><div class='container'>
            <div class='header'><img src='cid:logo_toptop' alt='TopTop'></div>
            {$corpo_html_inner}
            <div class='footer'><p>TopTop Clothing Store</p></div>
        </div></body></html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        log_email("enviarEmailTemplate ({$template_key}) falhou: " . $mail->ErrorInfo, 'email_handler.php');
        error_log("enviarEmailTemplate ({$template_key}) falhou: " . $mail->ErrorInfo);
        _resetSmtpMailer();
        return false;
    }
}
