<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OrangeSmsService
{
    public function isConfigured(): bool
    {
        return is_string(config('services.orange_sms.client_id'))
            && config('services.orange_sms.client_id') !== ''
            && is_string(config('services.orange_sms.client_secret'))
            && config('services.orange_sms.client_secret') !== ''
            && is_string(config('services.orange_sms.sender_address'))
            && config('services.orange_sms.sender_address') !== '';
    }

    public function send(string $recipient, string $message): void
    {
        $senderAddress = config('services.orange_sms.sender_address');

        if (! is_string($senderAddress) || $senderAddress === '') {
            throw new RuntimeException('L’adresse expéditrice Orange SMS n’est pas configurée.');
        }

        $baseUrl = rtrim((string) config('services.orange_sms.base_url'), '/');
        $encodedSender = str_replace('+', '%2B', $senderAddress);

        Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->connectTimeout(5)
            ->timeout(15)
            ->post(
                "{$baseUrl}/smsmessaging/v1/outbound/{$encodedSender}/requests",
                [
                    'outboundSMSMessageRequest' => [
                        'address' => 'tel:'.$recipient,
                        'senderAddress' => $senderAddress,
                        'outboundSMSTextMessage' => ['message' => $message],
                    ],
                ],
            )
            ->throw();
    }

    private function accessToken(): string
    {
        $encryptedToken = Cache::remember(
            'orange-sms-access-token',
            now()->addMinutes(55),
            function (): string {
                $clientId = config('services.orange_sms.client_id');
                $clientSecret = config('services.orange_sms.client_secret');

                if (
                    ! is_string($clientId)
                    || $clientId === ''
                    || ! is_string($clientSecret)
                    || $clientSecret === ''
                ) {
                    throw new RuntimeException('Les identifiants Orange SMS ne sont pas configurés.');
                }

                $baseUrl = rtrim(
                    (string) config('services.orange_sms.base_url'),
                    '/',
                );

                $response = Http::acceptJson()
                    ->asForm()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->post(
                        "{$baseUrl}/oauth/v3/token",
                        ['grant_type' => 'client_credentials'],
                    )
                    ->throw();

                $token = $response->json('access_token');

                if (! is_string($token) || $token === '') {
                    throw new RuntimeException('Orange SMS a renvoyé un jeton invalide.');
                }

                return Crypt::encryptString($token);
            },
        );

        return Crypt::decryptString($encryptedToken);
    }
}
