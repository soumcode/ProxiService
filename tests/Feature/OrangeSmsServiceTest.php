<?php

use App\Services\OrangeSmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::forget('orange-sms-access-token');

    config([
        'services.orange_sms.base_url' => 'https://api.orange.com',
        'services.orange_sms.client_id' => 'client-id',
        'services.orange_sms.client_secret' => 'client-secret',
        'services.orange_sms.sender_address' => 'tel:+22500000000',
    ]);
});

test('Orange SMS requests a token and sends an SMS using the configured sender', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.orange.com/oauth/v3/token' => Http::response([
            'access_token' => 'orange-access-token',
            'expires_in' => 3600,
        ]),
        'api.orange.com/smsmessaging/v1/outbound/*/requests' => Http::response([], 201),
    ]);

    app(OrangeSmsService::class)->send(
        '+2250102030405',
        'Votre code est 123456.',
    );

    Http::assertSent(fn (Request $request): bool => str_ends_with(
        $request->url(),
        '/oauth/v3/token',
    )
        && $request->hasHeader(
            'Authorization',
            'Basic '.base64_encode('client-id:client-secret'),
        )
        && $request->data() === ['grant_type' => 'client_credentials']);

    Http::assertSent(fn (Request $request): bool => str_contains(
        $request->url(),
        '/smsmessaging/v1/outbound/',
    )
        && str_contains($request->url(), '/tel:%2B22500000000/requests')
        && $request->hasHeader('Authorization', 'Bearer orange-access-token')
        && data_get(
            $request->data(),
            'outboundSMSMessageRequest.address',
        ) === 'tel:+2250102030405'
        && data_get(
            $request->data(),
            'outboundSMSMessageRequest.outboundSMSTextMessage.message',
        ) === 'Votre code est 123456.');

    Http::assertSentCount(2);
});

test('Orange SMS rejects a token response without an access token', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.orange.com/oauth/v3/token' => Http::response([
            'expires_in' => 3600,
        ]),
    ]);

    expect(fn () => app(OrangeSmsService::class)->send(
        '+2250102030405',
        'Votre code est 123456.',
    ))->toThrow(RuntimeException::class, 'Orange SMS a renvoyé un jeton invalide.');
});

test('Orange SMS reports provider request failures', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.orange.com/oauth/v3/token' => Http::response([
            'access_token' => 'orange-access-token',
            'expires_in' => 3600,
        ]),
        'api.orange.com/smsmessaging/v1/outbound/*/requests' => Http::response([], 503),
    ]);

    expect(fn () => app(OrangeSmsService::class)->send(
        '+2250102030405',
        'Votre code est 123456.',
    ))->toThrow(RequestException::class);
});

test('Orange SMS fails explicitly when the sender is not configured', function () {
    Http::preventStrayRequests();
    config(['services.orange_sms.sender_address' => null]);

    expect(fn () => app(OrangeSmsService::class)->send(
        '+2250102030405',
        'Votre code est 123456.',
    ))->toThrow(RuntimeException::class, 'L’adresse expéditrice Orange SMS n’est pas configurée.');
});
