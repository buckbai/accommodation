<?php
/**
 * Created by PhpStorm.
 * User: buck
 * Date: 2017/10/18
 * Time: 13:00
 *
 * 1.restore the status to InActive
 * 2.replace the data
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/conf.php';

(new \Dotenv\Dotenv(__DIR__))->load();

$urls = [
    'BeiCai' => 'http://sh.ziroom.com/z/nl/z2-u4-d310115-b611900123.html',
];
$goutte = new Goutte\Client();
$dbh = new PDO('mysql:host='. getenv('DB_HOST') .
    ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
$sql = "update ziru_house set status = 0;";
$dbh->exec($sql);
foreach ($urls as $url) {
    do {
        $crawler = $goutte->request('GET', $url);
        $texts = $crawler->filter('#houseList > li')->each(function ($node) {
            return array_map(function ($v) {
                return trim($v);
            }, [
                    'title' => $node->filter('.txt > h3')->text(),
                    'zone' => $node->filter('.txt > h4')->text(),
                    'link' => isset($_SERVER['HTTPS']) ? 'https' : 'http' . ':' . $node->filter('.txt > h3 > a')->attr('href'),
                    'area' => $node->filter('.txt > .detail > p')->first()->filter('span')->first()->text(),
                    'floor' => $node->filter('.txt > .detail > p')->first()->filter('span')->eq(1)->text(),
                    'type' => $node->filter('.txt > .detail > p')->first()->filter('span')->eq(2)->text(),
                    'distance' => $node->filter('.txt > .detail > p')->eq(1)->text(),
                    'price' => preg_match('/(\d+)/', $node->filter('.priceDetail > .price')->text(), $m) ? $m[1] : false,
                    'is_part' => $node->filter('.txt > .detail > p')->first()->filter('span')->eq(3)->text() == '合' ? 1 : 0,
                    'raw_text' => $node->text(),
                    'province' => $GLOBALS['crawler']->filter('#current_city')->text(),
                    'status' => 1,
            ]);
        });
        store($dbh, $texts);

        $next = $crawler->selectLink('下一页')->count();
    } while ($url = $next ? $crawler->selectLink('下一页')->link()->getUri() : false);

}


/**
 * @param $dbh
 * @param $texts
 */
function store(PDO $dbh, $texts)
{
    $keys = array_keys(current($texts));
    $sql = "insert into ziru_house (" . implode(',', $keys) . ") values (" . implode(',', array_fill(0, count($keys), '?')) . ") on duplicate key update ";
    $onUpdate = array_map(function ($v) {
        return "$v=values($v)";
    }, $keys);
    $sql .= implode(',', $onUpdate);
//    var_dump($sql);die;
    $sth = $dbh->prepare($sql);
    foreach ($texts as $text) {
//        var_dump(array_values($text));
        $sth->execute(array_values($text));
//        var_dump($sth->errorInfo());die;
    }
}