<?php

require __DIR__ . '/config.php';  // Load DB creds securely

function getDbConnection() {
    try {
        $pdo = new PDO($dsn, $username, $password);  // Uses variables from config.php
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Optional: Set fetch mode for easier JSON in API responses
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        // Handle based on ENV (dev shows details, prod logs silently)
        if (ENV === 'dev') {
            die("DB Connection failed: " . $e->getMessage());  // For debugging applicant queries
        } else {
            error_log("DB Connection failed: " . $e->getMessage());  // Prod: Log without exposing
            die("Service unavailable. Please try again later.");  // User-friendly for Angular frontend
        }
    }
}
