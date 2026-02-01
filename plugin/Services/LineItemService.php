<?php
namespace PB_LTI\Services;

use GuzzleHttp\Client;

class LineItemService {
  public static function create($platform, string $context_id, string $label, float $max) {
    $token = TokenCache::get($platform->issuer);
    $client = new Client();
    $res = $client->post($platform->lineitems_url, [
      'headers' => [
        'Authorization' => 'Bearer '.$token,
        'Content-Type' => 'application/vnd.ims.lis.v2.lineitem+json'
      ],
      'json' => [
        'label' => $label,
        'scoreMaximum' => $max,
        'resourceId' => uniqid('pb_'),
        'tag' => 'pressbooks'
      ]
    ]);
    return json_decode($res->getBody(), true);
  }
}
