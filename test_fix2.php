<?php
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$k=$app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
function get($uri){
    $r=Request::create($uri,'GET');
    $k=app()->make(Illuminate\Contracts\Http\Kernel::class);
    return $k->handle($r)->getContent();
}
$c=get("/lexicon/H7225");
$hasCorrect = strpos($c,'البداية')!==false && strpos($c,'�لبداية')===false ? 'CORRECT' : 'STILL_BROKEN';
echo "$hasCorrect\n";
echo strpos($c,'الأعظم')!==false ? "HAS_AAZAM\n" : "NO_AAZAM\n";
echo strpos($c,'الأول')!==false ? "HAS_AWAL\n" : "NO_AWAL\n";
// Check that the first chip's Arabic is not corrupted
if(preg_match('/البداية.*?الأعظم/s',$c,$m)){
    echo "SPLIT_OK\n";
    echo substr($c, strpos($c,'البداية')-100, 300)."\n";
}
