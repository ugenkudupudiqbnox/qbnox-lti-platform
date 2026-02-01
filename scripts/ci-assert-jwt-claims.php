
<?php
$jwt = $argv[1] ?? null;
if (!$jwt) {
  fwrite(STDERR, "JWT missing\n");
  exit(1);
}

list($h, $p, $s) = explode('.', $jwt);
$payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);

$required = [
  'iss',
  'aud',
  'exp',
  'iat',
  'nonce',
  'https://purl.imsglobal.org/spec/lti/claim/message_type',
  'https://purl.imsglobal.org/spec/lti/claim/version',
];

foreach ($required as $claim) {
  if (!isset($payload[$claim])) {
    fwrite(STDERR, "Missing JWT claim: $claim\n");
    exit(1);
  }
}

if ($payload['https://purl.imsglobal.org/spec/lti/claim/version'] !== '1.3.0') {
  fwrite(STDERR, "Invalid LTI version\n");
  exit(1);
}

echo "JWT claims validated successfully\n";
