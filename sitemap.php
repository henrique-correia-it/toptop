<?php
ob_start();
require_once 'config/database.php';
require_once 'config/url_helpers.php';
ob_end_clean();

header("Content-Type: application/xml; charset=utf-8");

$url_base = $base_url;
$hoje = date('Y-m-d');
$xml_loc = static fn(string $url): string => htmlspecialchars($url, ENT_XML1, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <url>
        <loc><?php echo $xml_loc($url_base . '/'); ?></loc>
        <lastmod><?php echo $hoje; ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?php echo $xml_loc($url_base . '/produtos'); ?></loc>
        <lastmod><?php echo $hoje; ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?php echo $xml_loc($url_base . '/contacto'); ?></loc>
        <lastmod><?php echo $hoje; ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?php echo $xml_loc($url_base . '/envios'); ?></loc>
        <lastmod><?php echo $hoje; ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>
    <url>
        <loc><?php echo $xml_loc($url_base . '/trocas'); ?></loc>
        <lastmod><?php echo $hoje; ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>
    <url>
        <loc><?php echo $xml_loc($url_base . '/consultar-encomenda'); ?></loc>
        <lastmod><?php echo $hoje; ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>

    <?php
    // Category URLs
    $cats_r = $conn->query("SELECT c.nome FROM categorias c WHERE EXISTS (SELECT 1 FROM produtos p WHERE p.categoria COLLATE utf8mb4_unicode_ci = c.nome COLLATE utf8mb4_unicode_ci AND p.ativo = 1 AND EXISTS (SELECT 1 FROM produto_variacoes pv WHERE pv.produto_id = p.id AND pv.quantidade > 0)) ORDER BY c.ordem ASC, c.id ASC");
    if ($cats_r && $cats_r->num_rows > 0) {
        while ($cat = $cats_r->fetch_assoc()) {
            $cat_url = $url_base . '/produtos?categorias%5B%5D=' . rawurlencode($cat['nome']);
            ?>
    <url>
        <loc><?php echo $xml_loc($cat_url); ?></loc>
        <lastmod><?php echo $hoje; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
            <?php
        }
    }

    // Product URLs
    $result = $conn->query("SELECT id, nome FROM produtos WHERE ativo = 1");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $slug = criar_slug($row['nome'] . '-' . $row['id']);
            $url_produto = $url_base . '/produto/' . $slug;
            ?>
    <url>
        <loc><?php echo $xml_loc($url_produto); ?></loc>
        <lastmod><?php echo $hoje; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
            <?php
        }
    }
    ?>
</urlset>
