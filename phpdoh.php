<?php
// (C) Murty Rompalli
// PHP script to serve as DNS over HTTPS server

function getip($domain,$type) {
  $ips['www.google.com']['A'] = '192.168.0.1';
  $ips['www.google.com']['AAAA'] = 'fe80::21d:43ff:fee0:1b9a';
  $ips['www.google.com']['MX'] = '192.168.2.1';
  $ips['www.yahoo.com']['A'] = '192.168.0.1';

  if (isset($ips[$domain][$type]))
    return $ips[$domain][$type];

  // TODO: If $domain matches certain TLD, return default IPv4 and IPv6

  if ($type == 'AAAA')
    return '::1';       // Default IPv6 to return
  else
    return '127.0.0.1';  // Default for all other types
}

function HandleQuery($buf) {
  $types = array(
      'A'    => 1,
      'AAAA' => 28,
      '*'    => 255
  );

  $domain = '';
  $tmp = substr($buf,12);
  $e = strlen($tmp);

  for($i=0; $i < $e; $i++) {
    $len = ord($tmp[$i]);
    if ($len==0)
      break;
    $domain .= substr($tmp,$i+1, $len).".";
    $i += $len;
  }

  $i++;$i++;
  $querytype = array_search((string)ord($tmp[$i]), $types);
  $domain = substr($domain,0,strlen($domain)-1); // strip trailing dot
  $ip = getip($domain, $querytype);
  $data = inet_pton($ip);  // IP address as a packed string
  $datalen = strlen($data); // 4 bits for IPv4, 16 bits for IPv6

  $answ = $buf[0].$buf[1].chr(129).chr(128).$buf[4].$buf[5].$buf[4].$buf[5].chr(0).chr(0).chr(0).chr(0).$tmp.chr(192).chr(12);

  switch($querytype) {
    case 'AAAA': $answ .= chr(0).chr(28); break;
    default:     $answ .= chr(0).chr(1);
  }

  # Calculating TTL for 3600 seconds
  # $sec = 3600;
  # $x = floor($sec / 256);
  # $y = $sec - $x * 256;
  # $ttl = chr($x).chr($y);
  $ttl = chr(14).chr(16);

  $answ .= chr(0).chr(1).chr(0).chr(0).$ttl.chr(0).chr($datalen).$data;
  header("Content-Type: application/dns-message");
  echo $answ; // TODO: Only echo first 4096 bytes of $answ
}

// Main
$request = '';

if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/dns-message')
  $request = file_get_contents("php://input");
else if (isset($_GET['dns']))
  $request = base64_decode(str_replace(array('-', '_'), array('+', '/'), $_GET['dns']));

if ($request)
  HandleQuery($request);
?>
