<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/LogFile.php';

function logMessage($message) {
    static $logFile = null;
    static $id = null;
    if (is_null($logFile)) {
        $filename = "/karma.log";
        $logFile = new \LogFile(__DIR__ . $filename, 'a', false);
        $id = rand(10000, 1000000);
    }
    $logFile->writeString("from $id: " . $message);
}

function check_email($email) {
    
}

function send_email($email, $from, $to, $subj, $body) {
    $timeout = rand(1, 10);
    sleep($timeout);
    logMessage("sended to $email($to) from $from for $timeout sec.");
}

function getConnection($host, $dbname, $user, $password) {
    static $connection = null;
    if (!$connection) {
        try {
            $connection = new PDO("mysql:host=" . $host . ";dbname=" . $dbname, $user, $password);
            $connection->exec("set names utf8");
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
    }
    return $connection;
}

parse_str(implode('&', array_slice($argv, 1)), $_GET);
$ids = explode(',', @$_GET['ids']);
if (empty($ids)) {
    logMessage("sender got empty ids");
    die;
}
set_time_limit(90 * 60);
$connection = getConnection(MYSQL_HOST . ':' . MYSQL_PORT, MYSQL_DB, MYSQL_USER, MYSQL_PASSWORD);
if ($connection) {
    logMessage("connected");
    $idsString = join(',', $ids);
    try {
        $connection->exec("UPDATE subscriptions SET notified=0, queued=UNIX_TIMESTAMP()  WHERE  id IN($idsString)");
        $query = "SELECT s.id, service, users.email, users.username FROM subscriptions as s LEFT JOIN users ON s.userId=users.id "
                . " WHERE s.id IN(" . $idsString . ") ORDER BY s.validts ASC";
        $stmt = $connection->prepare($query);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                send_email($row['email'], $row['service'], $row['username'], "Окончание подписки!", "Вам письмо...");
                $connection->exec("UPDATE subscriptions SET notified = UNIX_TIMESTAMP(), queued=0 WHERE  id={$connection->quote($row['id'])}");
            }
        }
    } catch (PDOException $exception) {
        logMessage("Connection error: " . $exception->getMessage());
    }
} else
    logMessage("not connected");