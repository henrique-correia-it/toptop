<?php
// admin/includes/image_upload.php - Helper DRY para validação e gravação de imagens base64 de produtos

if (!function_exists('salvar_imagens_base64_produto')) {
    /**
     * Valida e grava uma lista de imagens base64 (vindas do multiCropper) na pasta de destino.
     *
     * @param array  $imagensInfo      Lista de ['placeholder' => ..., 'dados' => ...]
     * @param string $destDir          Caminho absoluto da pasta destino (com ou sem barra final)
     * @param array  &$ficheirosCriados Array onde são registados os caminhos absolutos criados (para rollback)
     * @param int    $maxBytes         Tamanho máximo por imagem em bytes (default: 5MB)
     * @return array Mapa [placeholder => nome_ficheiro]
     * @throws Exception Se alguma imagem for inválida ou falhar a gravação
     */
    function salvar_imagens_base64_produto(array $imagensInfo, string $destDir, array &$ficheirosCriados, int $maxBytes = 5242880): array
    {
        $tiposPermitidos = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
        $extMap = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF  => 'gif',
        ];

        $destDir = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR;
        $mapa = [];
        $maxBase64Len = (int)ceil($maxBytes * 1.38) + 128;

        foreach ($imagensInfo as $imgInfo) {
            if (!is_array($imgInfo)) {
                throw new Exception('Formato de dados de imagem inválido.');
            }
            $placeholder = trim((string)($imgInfo['placeholder'] ?? ''));
            if ($placeholder === '' || isset($mapa[$placeholder])) {
                throw new Exception('Identificador de imagem inválido ou duplicado.');
            }

            $dadosStr = (string)($imgInfo['dados'] ?? '');
            if ($dadosStr === '' || strlen($dadosStr) > $maxBase64Len) {
                throw new Exception('Dados de imagem inválidos ou demasiado grandes (máx. 5MB).');
            }

            $base64Raw = preg_replace('#^data:image/\w+;base64,#i', '', $dadosStr);
            $binario   = base64_decode($base64Raw, true);

            if ($binario === false || strlen($binario) < 12 || strlen($binario) > $maxBytes) {
                throw new Exception('Dados de imagem inválidos ou demasiado grandes (máx. 5MB).');
            }

            $infoImagem = @getimagesizefromstring($binario);
            if ($infoImagem === false || !in_array($infoImagem[2], $tiposPermitidos, true)) {
                throw new Exception('O ficheiro enviado não é uma imagem válida (JPEG, PNG, WEBP ou GIF).');
            }

            $width  = (int)($infoImagem[0] ?? 0);
            $height = (int)($infoImagem[1] ?? 0);
            if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || ($width * $height) > 25000000) {
                throw new Exception('Dimensões de imagem inválidas ou excessivas (máx. 6000x6000px).');
            }

            $imgType = $infoImagem[2];
            $extReal = $extMap[$imgType] ?? 'jpg';
            $nomeFicheiro = 'img_' . bin2hex(random_bytes(16)) . '.' . $extReal;
            $caminhoAbsoluto = $destDir . $nomeFicheiro;

            $gravado = false;
            if (function_exists('imagecreatefromstring')) {
                $im = @imagecreatefromstring($binario);
                if ($im === false) {
                    throw new Exception('Não foi possível processar a imagem enviada.');
                }
                try {
                    if ($imgType === IMAGETYPE_PNG || $imgType === IMAGETYPE_WEBP) {
                        @imagepalettetotruecolor($im);
                        @imagealphablending($im, false);
                        @imagesavealpha($im, true);
                    }
                    if ($imgType === IMAGETYPE_JPEG && function_exists('imagejpeg')) {
                        $gravado = @imagejpeg($im, $caminhoAbsoluto, 90);
                    } elseif ($imgType === IMAGETYPE_PNG && function_exists('imagepng')) {
                        $gravado = @imagepng($im, $caminhoAbsoluto, 6);
                    } elseif ($imgType === IMAGETYPE_WEBP && function_exists('imagewebp')) {
                        $gravado = @imagewebp($im, $caminhoAbsoluto, 88);
                    } elseif ($imgType === IMAGETYPE_GIF && function_exists('imagegif')) {
                        $gravado = @imagegif($im, $caminhoAbsoluto);
                    }
                } finally {
                    @imagedestroy($im);
                }
            }

            if (!$gravado) {
                if (file_put_contents($caminhoAbsoluto, $binario) === false) {
                    throw new Exception('Erro ao guardar a imagem. Verifique as permissões da pasta public/images no servidor.');
                }
            }

            $ficheirosCriados[] = $caminhoAbsoluto;
            $mapa[$placeholder] = $nomeFicheiro;
        }

        return $mapa;
    }
}
