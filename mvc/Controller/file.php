<?php
/**
 * User: Emaro
 * Date: 2017-03-17
 * Time: 10:24
 */

function printFile() {
    
    $f = file('file.txt');
    
    if ($f == NULL)
        return;
    
    foreach ($f as $line) {
        echo $line . '<br>';
    }
}

function fileAppend($content) {
    
    $content .= "\n";
    
    $f = file('file.txt');
    
    $c = count($f) -1 ;
    $l = $f[$c];
    
    if ($l != $content)
        file_put_contents('file.txt', $content, FILE_APPEND);
}