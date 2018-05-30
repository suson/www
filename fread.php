<?php
$host = '10.11.4.130';
$port = '3306';
$user = 'root';
$pass = '123456';
$db = 'test';

$txtfile = 'test.txt'; // 读取的文件
$file = @fopen($txtfile, 'r');

$table = 'tb_urls'; // 要写入的表名
$line = 5; // 读取几行当一串内容
$field = 'body'; // 要写入的字段


if (!$file) {
    die('file open fail');
} else {
    $pdo = connection($host, $user, $pass, $db, $port);
    while (!feof($file)) {
        $content = array();
        for ($i = 0; $i < $line; $i++) {
            $content[$i] = mb_convert_encoding(fgets($file), "UTF-8", "GBK,ASCII,ANSI,UTF-8");
//            $content[$i] = fgets($file);
        }
        $content = array_filter($content); //数组去空
        $content_str = implode('', $content);
        $sql = "INSERT INTO {$table} ({$field})VALUES (:val)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(':val' => $content_str));
        echo $pdo->lastinsertid();
    }
}

fclose($file);


function connection($host = '127.0.0.1', $user = 'root', $pass = '123456', $db = 'test', $port = 3306)
{
    try {
        $dbh = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dbh->exec('set names utf8');
        return $dbh;
    } catch (PDOException $e) {
        print "SQLError!:" . $e->getMessage() . "<br/>";
        die();
    }

}