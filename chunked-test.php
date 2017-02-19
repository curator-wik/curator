<?php

if (function_exists('apache_setenv')) {
  apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
while(ob_get_level()){ob_end_flush();}

header("Transfer-Encoding: chunked");
header("Content-Type: text/plain");
flush();


function getThingsToSay() {
  $results = [];
  for ($i = 0; $i < 80; $i++) {
    $output = [];
    exec('/usr/games/fortune', $output);
    $results[] = implode("\n", $output);
  }
  return $results;
}

function send_chunk($chunk) {
  printf("%x\r\n", strlen($chunk));
  print $chunk;
  print "\r\n";
  flush();
}

$things = getThingsToSay();
send_chunk("Done loading fortunes.\n");
sleep(2);

foreach ($things as $fortune) {
 send_chunk($fortune . "\n\n");
 usleep(3e5);
}


