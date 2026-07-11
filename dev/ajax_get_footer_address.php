<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/http.php';

if (!admin_has_role(['superadmin', 'desenvolvedor'])) {
    json_response(['rua' => '', 'cp' => '', 'localidade' => '']);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/footer_functions.php';

json_response([
    'rua' => getFooterText('morada_rua', 'Edifício Chafariz, Rua dos Fontenários'),
    'cp' => getFooterText('morada_cp', '4535-221'),
    'localidade' => getFooterText('morada_localidade', 'Lourosa, Portugal')
]);
