<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_role(['teacher', 'student']);

// Compatibility URL. The shared role entry point now lives under user/.
redirect('user/group.php');
