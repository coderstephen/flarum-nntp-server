<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\Loop;

Coroutine\create(function () {
    $access = new flarumNntp\FlarumAccessLayer(new flarumNntp\ApiClient('https://discuss.flarum.org'));

    foreach (yield from $access->getTags() as $tag) {
        var_dump($tag->slug());
    }
})->done();

Loop\run();
