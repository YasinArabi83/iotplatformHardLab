<?php
    class Database {
        // Defaults (original values). On a real host, override them in iot/config.local.php
        private $host = "localhost";
        private $database = "hivaindb_alldata";
        private $username = "root";
        private $password = "";
        public $conn;
    public function __construct(){
        // Server-side overrides: kept out of git and never touched by the auto-deploy.
        $configFile = __DIR__ . '/../config.local.php';
        if (is_readable($configFile)) {
            $cfg = require $configFile;
            if (is_array($cfg)) {
                $this->host     = $cfg['host']     ?? $this->host;
                $this->database = $cfg['database'] ?? $this->database;
                $this->username = $cfg['username'] ?? $this->username;
                $this->password = $cfg['password'] ?? $this->password;
            }
        }
        try {
            $this->conn = new mysqli ($this->host, $this->username, $this->password,$this->database);
        } catch (mysqli_sql_exception $exception) {
            echo "Database could not be connected: " . $exception->getMessage();
        }
    }
    }
?>
