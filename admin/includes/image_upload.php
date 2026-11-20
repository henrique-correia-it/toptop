<?php
// admin/includes/image_upload.php — Helper DRY para validação e gravação de imagens base64 de produtos

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

        foreach ($imagensInfo as $imgInfo) {
            $placeholder = (string)($imgInfo['placeholder'] ?? '');
            $base64Raw   = preg_replace('#^data:image/\w+;base64,#i', '', (string)($imgInfo['dados'] ?? ''));
            $binario     = base64_decode($base64Raw, true);

            if ($binario === false || strlen($binario) < 12 || strlen($binario) > $maxBytes) {
                throw new Exception('Dados de imagem inválidos ou demasiado grandes (máx. 5MB).');
            }

            $infoImagem = @getimagesizefromstring($binario);
            if ($infoImagem === false || !in_array($infoImagem[2], $tiposPermitidos, true)) {
                throw new Exception('O ficheiro enviado não é uma imagem válida (JPEG, PNG, WEBP ou GIF).');
            }

            $extReal = $extMap[$infoImagem[2]] ?? 'jpg';
            $nomeFicheiro = uniqid('img_', true) . '.' . $extReal;
            $caminhoAbsoluto = $destDir . $nomeFicheiro;

            if (file_put_contents($caminhoAbsoluto, $binario) === false) {
                throw new Exception('Erro ao guardar a imagem. Verifique as permissões da pasta public/images no servidor.');
            }

            $ficheirosCriados[] = $caminhoAbsoluto;
            $mapa[$placeholder] = $nomeFicheiro;
        }

        return $mapa;
    }
}
