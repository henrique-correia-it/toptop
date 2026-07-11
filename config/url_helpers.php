<?php
// config/url_helpers.php

if (!function_exists('criar_slug')) {
    function criar_slug($texto)
    {
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
        $texto = preg_replace('/[^a-zA-Z0-9 -]/', '', $texto);
        $texto = str_replace(' ', '-', $texto);
        $texto = strtolower($texto);
        $texto = preg_replace('/-+/', '-', $texto);
        return trim($texto, '-');
    }
}
