<?php

foreach (glob("app/code/Magento/*/etc/webapi.xml") as $webapixml) {
     $module = preg_replace('(app/code/Magento/(.*)/etc/webapi.xml)', '$1', $webapixml);
     print "==== $module ====\n\n";
     $xml = simplexml_load_file($webapixml);
     foreach ($xml as $key => $value) {
	$attr = $value->attributes();
	$method = (string) $attr["method"];
	$url = (string) $attr["url"];
	$line = "    ".(str_pad($method, 6))." ".$url;
	print "$line\n";
    }
    print "\n";
}
