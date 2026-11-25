<?php
/**
 * dev/includes/storage_helper.php
 * Fonte única de verdade para a Galeria de Imagens, Deteção de Fantasmas
 * e Cálculo de Armazenamento no painel /dev.
 */

if (!function_exists('dev_galeria_extensoes')) {
    function dev_galeria_extensoes(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
    }
}

if (!function_exists('dev_galeria_protegidos')) {
    function dev_galeria_protegidos(): array
    {
        return ['default.jpg', 'logo1.jpg', 'logo1_branco.jpg', 'hero_bg.jpg', 'hero.avif', 'mbway.png'];
    }
}

if (!function_exists('dev_galeria_tipo_labels')) {
    function dev_galeria_tipo_labels(): array
    {
        return [
            'produto'   => 'Produto',
            'categoria' => 'Categoria',
            'logo'      => 'Logo',
            'hero'      => 'Hero',
            'outros'    => 'Outro',
        ];
    }
}

if (!function_exists('dev_galeria_pastas_permitidas')) {
    /**
     * Mapeia prefixo relativo (a partir de public/) -> caminho absoluto no disco.
     */
    function dev_galeria_pastas_permitidas(): array
    {
        $publicDir = realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public');
        $publicDir = rtrim($publicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return [
            'images/'                => $publicDir . 'images' . DIRECTORY_SEPARATOR,
            'assets/img_categorias/' => $publicDir . 'assets' . DIRECTORY_SEPARATOR . 'img_categorias' . DIRECTORY_SEPARATOR,
            'assets/header/'         => $publicDir . 'assets' . DIRECTORY_SEPARATOR . 'header' . DIRECTORY_SEPARATOR,
            'assets/hero/'           => $publicDir . 'assets' . DIRECTORY_SEPARATOR . 'hero' . DIRECTORY_SEPARATOR,
            'assets/'                => $publicDir . 'assets' . DIRECTORY_SEPARATOR,
        ];
    }
}

if (!function_exists('dev_classificar_asset_raiz')) {
    function dev_classificar_asset_raiz(string $filename): string
    {
        if (preg_match('/^hero/i', $filename)) {
            return 'hero';
        }
        if (preg_match('/^logo/i', $filename)) {
            return 'logo';
        }
        return 'outros';
    }
}

if (!function_exists('dev_listar_imagens_galeria')) {
    /**
     * Lista todas as imagens do site (incluindo raiz de public/assets/)
     * e devolve contagens e tamanhos exatos por tipo e totais.
     */
    function dev_listar_imagens_galeria(): array
    {
        $pastas = dev_galeria_pastas_permitidas();
        $allowedExt = dev_galeria_extensoes();

        $typedSources = [
            'produto'   => ['prefix' => 'images/',                'web' => '/public/images/',                'dir' => $pastas['images/']],
            'categoria' => ['prefix' => 'assets/img_categorias/', 'web' => '/public/assets/img_categorias/', 'dir' => $pastas['assets/img_categorias/']],
            'logo'      => ['prefix' => 'assets/header/',         'web' => '/public/assets/header/',         'dir' => $pastas['assets/header/']],
            'hero'      => ['prefix' => 'assets/hero/',           'web' => '/public/assets/hero/',           'dir' => $pastas['assets/hero/']],
        ];

        $imagens = [];
        $contagens = [
            'produto'   => 0,
            'categoria' => 0,
            'logo'      => 0,
            'hero'      => 0,
            'outros'    => 0,
        ];
        $bytesTipo = [
            'produto'   => 0,
            'categoria' => 0,
            'logo'      => 0,
            'hero'      => 0,
            'outros'    => 0,
        ];
        $vistos = [];

        $adicionarFicheiro = static function (string $dir, string $filename, string $prefix, string $webPath, string $tipo) use (&$imagens, &$contagens, &$bytesTipo, &$vistos, $allowedExt): void {
            if ($filename === '.' || $filename === '..') {
                return;
            }
            $path = $dir . $filename;
            if (!is_file($path) || is_link($path)) {
                return;
            }
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                return;
            }

            $real = realpath($path) ?: $path;
            if (isset($vistos[$real])) {
                return;
            }
            $vistos[$real] = true;

            $sz = (int)filesize($path);
            $mtime = (int)filemtime($path);
            $rel = $prefix . $filename;

            $imagens[] = [
                'nome'     => $filename,
                'rel'      => $rel,
                'web_path' => $webPath,
                'src'      => $webPath . rawurlencode($filename),
                'tamanho'  => $sz,
                'data'     => $mtime,
                'tipo'     => $tipo,
                'realpath' => $real,
            ];

            if (!isset($contagens[$tipo])) {
                $contagens[$tipo] = 0;
                $bytesTipo[$tipo] = 0;
            }
            $contagens[$tipo]++;
            $bytesTipo[$tipo] += $sz;
        };

        // 1. Subpastas tipadas
        foreach ($typedSources as $tipo => $info) {
            if (!is_dir($info['dir'])) {
                continue;
            }
            $items = @scandir($info['dir']);
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $f) {
                $adicionarFicheiro($info['dir'], $f, $info['prefix'], $info['web'], $tipo);
            }
        }

        // 2. Raiz de public/assets/ (hero.avif padrão, logo1_branco.jpg favicon, etc.)
        $assetsRoot = $pastas['assets/'];
        if (is_dir($assetsRoot)) {
            $items = @scandir($assetsRoot);
            if (is_array($items)) {
                foreach ($items as $f) {
                    $tipoRoot = dev_classificar_asset_raiz($f);
                    $adicionarFicheiro($assetsRoot, $f, 'assets/', '/public/assets/', $tipoRoot);
                }
            }
        }

        usort($imagens, static fn(array $a, array $b): int => $b['data'] <=> $a['data']);

        return [
            'imagens'     => $imagens,
            'contagens'   => $contagens,
            'bytes_tipo'  => $bytesTipo,
            'total_count' => count($imagens),
            'total_bytes' => array_sum($bytesTipo),
        ];
    }
}

if (!function_exists('dev_recolher_referencias_imagens')) {
    /**
     * Recolhe os nomes de ficheiro referenciados nas tabelas reais da BD.
     */
    function dev_recolher_referencias_imagens(mysqli $conn): array
    {
        $referenciados = [
            'produto'   => [],
            'categoria' => [],
            'logo'      => [],
            'hero'      => [],
            'outros'    => [],
            'any'       => [],
        ];

        $queries = [
            ['tipo' => 'produto',   'sql' => "SELECT foto_principal AS f FROM produtos WHERE foto_principal IS NOT NULL AND foto_principal != ''"],
            ['tipo' => 'produto',   'sql' => "SELECT nome_ficheiro  AS f FROM produto_imagens WHERE nome_ficheiro IS NOT NULL AND nome_ficheiro != ''"],
            ['tipo' => 'produto',   'sql' => "SELECT foto_snapshot  AS f FROM encomenda_itens WHERE foto_snapshot IS NOT NULL AND foto_snapshot != ''"],
            ['tipo' => 'categoria', 'sql' => "SELECT foto_capa      AS f FROM categorias WHERE foto_capa IS NOT NULL AND foto_capa != ''"],
            ['tipo' => 'logo',      'sql' => "SELECT conteudo       AS f FROM header_config WHERE seccao = 'logo_src' AND conteudo IS NOT NULL AND conteudo != ''"],
            ['tipo' => 'hero',      'sql' => "SELECT valor          AS f FROM loja_configuracoes WHERE chave = 'home_hero_bg' AND valor IS NOT NULL AND valor != ''"],
        ];

        foreach ($queries as $q) {
            try {
                $res = $conn->query($q['sql']);
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $raw = (string)($row['f'] ?? '');
                        $pathOnly = parse_url($raw, PHP_URL_PATH);
                        $pathOnly = is_string($pathOnly) && $pathOnly !== '' ? $pathOnly : $raw;
                        $nome = basename(str_replace('\\', '/', $pathOnly));
                        if ($nome !== '' && $nome !== '.' && $nome !== '..') {
                            $referenciados[$q['tipo']][$nome] = true;
                            $referenciados['any'][$nome] = true;
                        }
                    }
                    $res->free();
                }
            } catch (\Throwable $e) {
                // Ignora tabela/coluna opcional caso falhe
            }
        }

        return $referenciados;
    }
}

if (!function_exists('dev_listar_fantasmas')) {
    /**
     * Devolve a lista de chaves relativas ($img['rel']) de imagens não referenciadas.
     */
    function dev_listar_fantasmas(mysqli $conn, ?array $galeria = null): array
    {
        $galeria = $galeria ?? dev_listar_imagens_galeria();
        $referenciados = dev_recolher_referencias_imagens($conn);
        $protegidos = array_flip(dev_galeria_protegidos());
        $fantasmas = [];

        foreach ($galeria['imagens'] as $img) {
            $nome = $img['nome'];
            $tipo = $img['tipo'];
            if (isset($protegidos[$nome])) {
                continue;
            }
            $isRef = isset($referenciados[$tipo][$nome]) || isset($referenciados['any'][$nome]);
            if (!$isRef) {
                $fantasmas[] = $img['rel'];
            }
        }

        return $fantasmas;
    }
}

if (!function_exists('dev_calcular_armazenamento')) {
    /**
     * Calcula o armazenamento total e as 3 categorias mutuamente exclusivas:
     * imagens_bytes + codigo_bytes + outros_bytes === total_bytes.
     * Garante que imagens_bytes === dev_listar_imagens_galeria()['total_bytes'].
     */
    function dev_calcular_armazenamento(): array
    {
        $rootDir = realpath(__DIR__ . '/../..') ?: (__DIR__ . '/../..');
        $galeria = dev_listar_imagens_galeria();

        $galleryRealPaths = [];
        foreach ($galeria['imagens'] as $img) {
            if (!empty($img['realpath'])) {
                $galleryRealPaths[$img['realpath']] = true;
            }
        }

        $skipDirs = ['.git', '.vscode', '.agents', 'scratch'];
        $totalBytes = 0;
        $imagensBytes = 0;
        $codigoBytes = 0;
        $outrosBytes = 0;

        $rootNorm = rtrim(str_replace('\\', '/', $rootDir), '/') . '/';

        try {
            $dirIt = new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS);
            $filterIt = new RecursiveCallbackFilterIterator($dirIt, static function ($current) use ($skipDirs): bool {
                if ($current->isLink()) {
                    return false;
                }
                if ($current->isDir() && in_array($current->getFilename(), $skipDirs, true)) {
                    return false;
                }
                return true;
            });
            $files = new RecursiveIteratorIterator($filterIt);

            foreach ($files as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }
                $real = $file->getRealPath() ?: $file->getPathname();
                $size = (int)$file->getSize();
                $totalBytes += $size;

                if (isset($galleryRealPaths[$real])) {
                    $imagensBytes += $size;
                    continue;
                }

                $relPath = str_replace('\\', '/', $real);
                if (str_starts_with($relPath, $rootNorm)) {
                    $relPath = substr($relPath, strlen($rootNorm));
                }

                if (
                    str_starts_with($relPath, 'logs/')
                    || str_starts_with($relPath, 'public/images/')
                    || str_starts_with($relPath, 'public/assets/')
                ) {
                    $outrosBytes += $size;
                } else {
                    $codigoBytes += $size;
                }
            }
        } catch (\Throwable $e) {
            // Mantém totais parciais caso haja restrição de permissões
        }

        return [
            'total_bytes'   => $totalBytes,
            'imagens_bytes' => $imagensBytes,
            'codigo_bytes'  => $codigoBytes,
            'outros_bytes'  => $outrosBytes,
            'galeria'       => $galeria,
        ];
    }
}
