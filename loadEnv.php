<?php

/**
 * Charge les variables d'environnement à partir du fichier .env */
function loadEnv() {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        throw new Exception("Le fichier .env n'existe pas");
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Ignore les commentaires
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        putenv("$name=$value"); // Définit la variable d'environnement
    }
}
