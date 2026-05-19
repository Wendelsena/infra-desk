<?php

class Connection
{
    public static function connect()
    {
        $config = require __DIR__ . '/database.php';

        try {
            $pdo = new PDO(
                "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}",
                $config['user'],
                $config['password']
            );

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $pdo;

        } catch (PDOException $e) {
            die("Erro na conexão com o banco: " . $e->getMessage());
        }
    }
}