<?php
$map = array(
    'tarball' => 'tar.gz',
    'zipball' => 'zip',
);

$downloadsFolder  = "/var/www/phorum/downloads/";
$baseurlDownloads = '/downloads/';

$url = "https://api.github.com/repos/phorum/Core/releases/latest";

$ch = curl_init();
$timeout = 5;
curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
$data = curl_exec($ch);
curl_close($ch);

$release = json_decode($data);

$release->tarball_url;

$files = array();

foreach($map as $type => $extension) {
    $fp = fopen ($downloadsFolder . '/'.$release->tag_name.'.'.$extension, 'w+');//This is the file where we save the    information
    $ch = curl_init($release->{$type.'_url'});//Here is the file we are downloading, replace spaces with %20
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_FILE, $fp); // write curl response to file
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch); // get curl response
    curl_close($ch);
    fclose($fp);

    $files[$extension]['url']  = $baseurlDownloads.$release->tag_name.'.'.$extension;
    $files[$extension]['size'] = filesize($downloadsFolder . '/'.$release->tag_name.'.'.$extension);

}

$release->files = $files;

$release->html = nl2br($release->body);

file_put_contents("/tmp/latest_release.tmp", json_encode($release));
