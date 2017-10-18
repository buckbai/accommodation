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

use Goutte\Client;

$urls = [
    'BeiCai' => 'http://sh.ziroom.com/z/nl/z2-u4-d310115-b611900123.html',
];
$goutte = new Client();

foreach ($urls as $url) {
    $url = 'http://sh.ziroom.com/z/nl/z2-u4-d310115-b611900123.html?p=3';
    do {
        $crawler = $goutte->request('GET', $url)->filter('div.t_newlistbox');
        $texts = $crawler->filter('#houseList > li')->each(function ($node) {
            return array_map(function ($v) {
                return trim($v);
            }, [
                    'title' => $node->filter('.txt > h3')->text(),
                    'zone' => $node->filter('.txt > h4')->text(),
                    'page' => $node->filter('.txt > h3 > a')->attr('href'),
                    'area' => $node->filter('.txt > .detail > p')->first()->filter('span')->first()->text(),
                    'floor' => $node->filter('.txt > .detail > p')->first()->filter('span')->eq(1)->text(),
                    'roomType' => $node->filter('.txt > .detail > p')->first()->filter('span')->eq(2)->text(),
                    'distance' => $node->filter('.txt > .detail > p')->eq(1)->text(),
                    'price' => preg_match('/(\d+)/', $node->filter('.priceDetail > .price')->text(), $m) ? $m[1] : false,
            ]);
        });
        var_dump($texts);
        $next = $crawler->selectLink('下一页')->count();
    } while ($url = $next ? $crawler->selectLink('下一页')->link()->getUri() : false);

}
