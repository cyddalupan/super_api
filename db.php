<?php

require __DIR__ . '/config.php';  // Load DB creds securely

function getDbConnection() {
    global $dsn, $username, $password;  // Declare globals to bring variables into function scope

    try {
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        if (ENV === 'dev') {
            die("DB Connection failed: " . $e->getMessage());  // For debugging applicant queries
        } else {
            error_log("DB Connection failed: " . $e->getMessage());
            die("Service unavailable. Please try again later.");
        }
    }
}
