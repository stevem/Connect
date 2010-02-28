<?php
// work around for "uncaught exception: Permission denied to call method XMLHttpRequest.open"
$url = (ereg("^http://makethechange.ca/",  $_GET['url'])) ? $_GET['url'] : '';
echo  file_get_contents($url, FALSE, NULL, 0, 500);
