<?php declare(strict_types=1);
define('HISTORY_LOG_DIR', dirname(__FILE__, 2));
define('TEST_FILES_DIR', HISTORY_LOG_DIR
    . DIRECTORY_SEPARATOR . 'tests'
    . DIRECTORY_SEPARATOR . 'suite'
    . DIRECTORY_SEPARATOR . '_files');
require_once dirname(HISTORY_LOG_DIR, 2) . '/application/tests/bootstrap.php';
require_once 'HistoryLog_Test_AppTestCase.php';
