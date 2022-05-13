<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/LogFile.php';

function logMessage($message) {
    static $logFile = null;
    if (is_null($logFile)) {
        $filename = "/karma.log";
        $logFile = new \LogFile(__DIR__ . $filename, 'a', false);
    }
    $logFile->writeString($message);
    echo $message . "\r\n";
}

function add_subscription($connection, $userId, $service) {
    $stmt = $connection->prepare("INSERT INTO subscriptions (userId,service,validts) VALUES($userId,:service,UNIX_TIMESTAMP()+FLOOR(RAND()*10*60*60*24))");
    $stmt->bindParam(":service", $service);
    $stmt->execute();
}

function generate_users($connection) {
    $connection->exec("DELETE FROM users");
    $services = ['service1', 'service2'];
    for ($i = 0; $i < 1000; ++$i) {
        $email = "email$i@mail.ru";
        $stmt = $connection->prepare("INSERT INTO users (id,username,email,confirmed,checked,valid) VALUES(:id,:username,:email,:confirmed,:checked,:valid)");
        $stmt->bindParam(":id", $i);
        $username = "user$i";
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $confirmed = rand(1, 3) % 2;
        $stmt->bindParam(":confirmed", $confirmed);
        $checked = rand(1, 3) % 2;
        $stmt->bindParam(":checked", $checked);
        $valid = rand(1, 3) % 2;
        $stmt->bindParam(":valid", $valid);
        $stmt->execute();
        //subscriptions
        $subscriptionCode = rand(1, 3);
        switch ($subscriptionCode) {
            case 1:
                add_subscription($connection, $i, $services[0]);
                break;
            case 2:
                add_subscription($connection, $i, $services[1]);
                break;
            case 3:
                add_subscription($connection, $i, $services[0]);
                add_subscription($connection, $i, $services[1]);
                break;
        }
    }
}

function checkSubscriptions($connection) {
    $MAX_NUMBER_OF_PROCESSES = 100;
    $LETTERS_PER_PROCESS = 1000;
    $where = " WHERE users.valid=1 AND s.validts > 0 AND UNIX_TIMESTAMP() - s.queued > 90*60 AND UNIX_TIMESTAMP() - s.notified > 60*60*24 AND s.validts > UNIX_TIMESTAMP() AND s.validts - UNIX_TIMESTAMP() < 60*60*24*3";
    $query = "SELECT s.id, service, queued, notified FROM subscriptions  as s LEFT JOIN users ON s.userId=users.id "
            . $where
            . " ORDER BY s.notified,s.validts ASC";
    //выбираем все подписки, по которым еще не было запущено уведомление, либо оно было запущено более часа тому назад и с момента получения уведомления прошло более суток
    //упрорядочены по дате окончания подписки - сначала те, которые раньше закончатся
    //считаем, что это скрипт запускается раз в два часа. скрипт, который отвечает за отправку письма, пусть работает 90 минут - полтора часа
    //тогда мы гарантировано отравим 90*60/10 = 720 писем. но так как  не все письма будут уходить 10 сек, то можно задать список из 1000 писем.
    //Просто часть из них может не успеть уйти. Мы запустим какое-то разумное количетсво процессов отправки писем. допустим, 100 - получим 72 000  писем за два часа
    //гарантировано. за сутки  - 72 000 * 12 = 864 000 гарантировано, но в идеале, все 1 000 000.
    //За двое суток будут отправлены 2 000 000 писем
    $stmt = $connection->prepare($query);
    $ids = [];
    if ($stmt->execute()) {
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }
    }
    if(empty($ids))
    {
        logMessage ("nothing to do.");
        return;
    }
    $lettersPerProcess = $LETTERS_PER_PROCESS;
    $processes = ceil(count($ids)/$lettersPerProcess);
    if($processes > $MAX_NUMBER_OF_PROCESSES)
        $processes = $MAX_NUMBER_OF_PROCESSES;
    else if($processes < $MAX_NUMBER_OF_PROCESSES)
        $lettersPerProcess = ceil(count($ids)/$processes);
    echo $processes." ".$lettersPerProcess;
    $path = __DIR__.'/send_letters.php';
    $processesIds = array_chunk($ids, $lettersPerProcess);
    foreach ($processesIds as $processIds)
    {
        $processIds = join(',',$processIds);
        $command = "php $path ids=$processIds >/dev/null 2>&1 &";
        echo $command."\r\n";
        shell_exec($command);
    }
}

function lock() {
    //return true;
    $filePath = __DIR__ . '/lock';
    $lockFile = @fopen($filePath, 'x');
    if (!$lockFile) {
        //get creation time
        $lockedAt = filemtime($filePath);
        $diff = time() - $lockedAt;
        if ($diff > 60 * 60/* one hour */) {
            @unlink($filePath);
            $lockFile = @fopen($filePath, 'x');
        } else
            logMessage("locked $diff seconds ago");
    }
    return $lockFile;
}

function unlock($lockFile) {
    if ($lockFile) {
        @fclose($lockFile);
        @unlink(__DIR__ . '/lock');
    }
}

function getConnection($host, $dbname, $user, $password) {
    static $connection = null;
    if (!$connection) {
        try {
            $connection = new PDO("mysql:host=" . $host . ";dbname=" . $dbname, $user, $password);
            $connection->exec("set names utf8");
        } catch (PDOException $exception) {
            logMessage("Connection error: " . $exception->getMessage());
        }
    }
    return $connection;
}

function dropTables($connection) {
    try {
        $connection->exec("DROP TABLE IF EXISTS subscriptions");
        $connection->exec("DROP TABLE IF EXISTS users");
    } catch (PDOException $exception) {
        echo "Connection error: " . $exception->getMessage();
    }
    logMessage("droped");
}

parse_str(implode('&', array_slice($argv, 1)), $_GET);
if ($lockFile = lock()) {
    $connection = getConnection(MYSQL_HOST . ':' . MYSQL_PORT, MYSQL_DB, MYSQL_USER, MYSQL_PASSWORD);
    if ($connection) {
        logMessage("connected");
        set_time_limit(0);
        if (!empty($_GET['dropTables']))
            dropTables($connection);
        //create tables
        $sql = file_get_contents('./config/schema.tables.sql');
        $connection->exec($sql);
        if (!empty($_GET['fillTables']))
            generate_users($connection);
        checkSubscriptions($connection);
    } else
        logMessage("not connected");
    unlock($lockFile);
} else
    logMessage("the service is already running");


    