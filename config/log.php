<?php
function logError($message) {
    $logFile = __DIR__ . '/../logs/mantastico.log';
    $formattedMessage = "[" . date("Y-m-d H:i:s") . "] " . $message . "\n";
    
    // Garante que o diretório de logs exista
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}
