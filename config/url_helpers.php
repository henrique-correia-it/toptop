<?php
// config/url_helpers.php

if (!function_exists('criar_slug')) {
    function criar_slug($texto)
    {
        $texto = (string)$texto;
        $mapa = [
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A',
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n','Ý'=>'Y','ý'=>'y','ÿ'=>'y',
        ];
        $texto = strtr($texto, $mapa);
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($conv !== false) {
            $texto = $conv;
        }
        $texto = preg_replace('/[_\/]+/', '-', $texto);
        $texto = preg_replace('/[^a-zA-Z0-9 -]/', '', $texto);
        $texto = str_replace(' ', '-', $texto);
        $texto = strtolower($texto);
        $texto = preg_replace('/-+/', '-', $texto);
        return trim($texto, '-');
    }
}
