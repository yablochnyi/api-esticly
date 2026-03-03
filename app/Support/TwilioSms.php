<?php

namespace App\Support;

use Twilio\Rest\Client as TwilioClient;

class TwilioSms
{
    public static function send(string $toE164, string $body): bool
    {
        $sid = (string)config('services.twilio.sid');
        if (trim($sid) === '') return false;

        $token = (string)config('services.twilio.token');
        $apiKeySid = (string)config('services.twilio.api_key_sid');
        $apiKeySecret = (string)config('services.twilio.api_key_secret');

        $client = null;
        if (trim($apiKeySid) !== '' && trim($apiKeySecret) !== '') {
            $client = new TwilioClient($apiKeySid, $apiKeySecret, $sid);
        } elseif (trim($token) !== '') {
            $client = new TwilioClient($sid, $token);
        }
        if (!$client) return false;

        $from = (string)config('services.twilio.from');
        $mg = (string)config('services.twilio.messaging_service_sid');

        $payload = ['body' => $body];
        if (trim($mg) !== '') {
            $payload['messagingServiceSid'] = $mg;
        } else {
            if (trim($from) === '') return false;
            $payload['from'] = $from;
        }

        $client->messages->create($toE164, $payload);
        return true;
    }
}

