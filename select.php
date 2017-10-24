<?php
/**
 * Created by PhpStorm.
 * User: bch
 * Date: 2017/10/22
 * Time: 12:21
 */

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

(new \Dotenv\Dotenv(__DIR__))->load();
date_default_timezone_set('Asia/Shanghai');
$mailer = new PHPMailer();
$goutte = new Goutte\Client();

$roomCount = 4;
$dbh = new PDO('mysql:host='. getenv('DB_HOST') .
    ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'));

$unique = $data = [];
$sql = "select * from ziru_house where status = 1 order by id;";
$sth = $dbh->query($sql);
while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    $flag = false;
    foreach ($unique as $i => $u) {
        if ($row['zone'] == $u['zone'] &&
            $row['floor'] == $u['floor'] &&
            $row['type'] == $u['type'] &&
            $row['distance'] === $u['distance']
        ) {
            $flag = true;
            if (empty($data[$i])) {
                $data[$i][] = $u;
            }
            $data[$i][] = $row;
            break;
        }
    }
    if (!$flag) {
        $unique[] = $row;
    }
}

# remove same attr add but not satisfy the requirements.
foreach ($data as $k => $d) {
    if (count($d) >= $roomCount) {
        foreach ($d as $i => $r) {
            if (count($data[$k]) < $roomCount) {
                break;
            }
//            echo 'request ', $unique[$k]['id'], PHP_EOL;
            $crawler = $goutte->request('GET', $unique[$k]['link']);
            if ($crawler->filter('.greatRoommate li.current')->count() != $roomCount) {
                unset($data[$k][$i]);
                $unique[$k] = current($data[$k]);
            } else {
                storeInSatisfaction($dbh, $unique[$k]['id']);
                break;
            }
        }
    }
}

# email send
$mailer->CharSet = 'UTF-8';
$mailer->SMTPDebug = 2;                                 // Enable verbose debug output
$mailer->isSMTP();                                      // Set mailer to use SMTP
$mailer->Host = 'smtp.163.com';  // Specify main and backup SMTP servers
$mailer->SMTPAuth = true;                               // Enable SMTP authentication
$mailer->Username = getenv('MAIL_USERNAME');                 // SMTP username
$mailer->Password = getenv('MAIL_PASSWORD');                           // SMTP password
$mailer->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
$mailer->Port = 465;
$mailer->setFrom(getenv('MAIL_USERNAME'));
$mailer->addAddress(getenv('MAIL_USERNAME'));     // Add a recipient

$mailer->isHTML(true);
$mailer->Subject = 'ziru room info_' . date('Y-m-d H:i:s');
$mailer->Body    = '';
$mailer->AltBody = '';

$info = array_filter($data, function ($v) use ($roomCount) {
    return count($v) == $roomCount;
});
foreach ($info as $k => $v) {
    if (alreadyExist($dbh, $unique[$k]['id'])) {
        continue;
    }
    $mailer->Body .= <<<EOL
<a href='{$unique[$k]['link']}'>{$unique[$k]['title']}</a><br>
EOL;
}

if ($mailer->Body) {
    try {
        $mailer->send();
    } catch (Exception $e) {
        echo 'Mailer Error: ' . $mailer->ErrorInfo;
    }
}


function storeInSatisfaction(PDO $dbh, $id)
{
    $sql = "insert into ziru_satisfaction (ziru_house_id) values ($id) on duplicate key update last_change_time = NOW();";
    $dbh->exec($sql);
}

function alreadyExist(PDO $dbh, $id)
{
    $sql = "select count(*) from ziru_satisfaction where ziru_house_id = $id and add_time < DATE_SUB(last_change_time, INTERVAL 1 MINUTE) limit 1;";
    return $dbh->query($sql)->fetchColumn();
}