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

$mailer = new PHPMailer();


$dbh = new PDO('mysql:host='. getenv('DB_HOST') .
    ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'));

$unique = $data = [];
$sql = "select * from ziru_house where last_change_time > date_sub(now(), interval 1 hour);";
$sth = $dbh->query($sql);
while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    $flag = false;
    foreach ($unique as $i => $u) {
        if ($row['zone'] == $u['zone'] &&
            $row['floor'] == $u['floor'] &&
            $row['type'] == $u['type'] &&
            $row['distance'] === $u['distance']
        ) {
//            if (strstr($row['title'], '-', true) ==
//                strstr($u['title'], '-', true)
//            ) {
            $flag = true;
            $data[$i] = isset($data[$i]) ? $data[$i] + 1 : 2;
            break;
//            }
        }
    }
    if (!$flag) {
        $unique[] = $row;
    }
}

array_walk($data, function ($v, $k) use ($mailer, $unique) {
    if ($v > 3) {
        try {
            $mailer->SMTPDebug = 2;                                 // Enable verbose debug output
            $mailer->isSMTP();                                      // Set mailer to use SMTP
            $mailer->Host = 'smtp.163.com';  // Specify main and backup SMTP servers
            $mailer->SMTPAuth = true;                               // Enable SMTP authentication
            $mailer->Username = getenv('MAIL_USERNAME');                 // SMTP username
            $mailer->Password = getenv('MAIL_PASSWORD');                           // SMTP password
            $mailer->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
            $mailer->Port = 465;
            $mailer->setFrom(getenv('MAIL_USERNAME'));
            $mailer->addAddress(getenv('MAIL_USERNAME'));     // Add a recipient

            $mailer->isHTML(true);
            $mailer->Subject = $unique[$k]['title'];
            $mailer->Body    = "<a href='{$unique[$k]['link']}'>{$unique[$k]['title']}</a>";
            $mailer->AltBody = $unique[$k]['link'];
            $mailer->send();
        } catch (Exception $e) {
            echo 'Mailer Error: ' . $mailer->ErrorInfo;
        }
    }
});