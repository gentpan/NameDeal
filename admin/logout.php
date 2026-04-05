<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

admin_logout();
header('Location: /admin/index.php');
exit;
