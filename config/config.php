<?php

declare(strict_types=1);

define('APP_NAME', 'Hệ thống đăng ký đề tài');
define('APP_SHORT_NAME', 'DKDT');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'K73_nhom10_dangky_detai');
define('DB_USER', 'root');
define('DB_PASS', '123456');
define('DB_CHARSET', 'utf8mb4');

define('ROOT_PATH', dirname(__DIR__));
define('ITEMS_PER_PAGE', 8);
define('SESSION_TIMEOUT_SECONDS', 20 * 60);

date_default_timezone_set('Asia/Bangkok');
