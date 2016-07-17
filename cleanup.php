<?php

//$dir = 'D:\Dropbox\Sync\htdocs\_PROJEKTE\MyWebSolution\FilesTest';
//$di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
//$ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::SELF_FIRST);
//$pattern = "#\\.php$#";
////$pi = new RegexIterator($ri, "#\\.php$#");
//foreach ($ri as $f => $obj) {
//    if(!preg_match($pattern,$f)){
//        unlink($f);
//        continue;
//    }
//    $content = file_get_contents($f);
//    $content = str_replace("ISO-8859-1","UTF-8",$content);
//    $content = mb_convert_encoding($content,"UTF-8","CP1252");
//    file_put_contents($f,$content);
//    echo $f."\n";
//}
if(!isset($_GET["doit"]) || $_GET["doit"] !== "02488378"){
    die("Wrong code for ?doit ({$_GET["doit"]})!");
}
$di = new DirectoryIterator(__DIR__."/backlinkseller");
$pattern = "#^bs\\-#";
$i = 0;
foreach($di as $f){
    if(!$f->isDot() && $f->isFile() && preg_match("$pattern",$f->getFilename())){
        unlink($f->getPathname());
        $i++;
    }
}
echo "Cleaned $i files";