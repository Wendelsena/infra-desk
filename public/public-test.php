<?php

require_once __DIR__ . '/../config/Connection.php';

$pdo = Connection::connect();

echo "Conexão com PostgreSQL funcionando!";