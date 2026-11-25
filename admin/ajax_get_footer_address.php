<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/http.php';

if (!is_admin_manager()) {
    json_response(['rua' => '', 'cp' => '', 'localidade' => '']);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/footer_functions.php';

json_response([
    'rua' => getFooterText('morada_rua', 'Edifício Chafariz, Rua dos Fontenários'),
    'cp' => getFooterText('morada_cp', '4535-221'),
    'localidade' => getFooterText('morada_localidade', 'Lourosa, Portugal')
]);
