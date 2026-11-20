<?php

use App\Jobs\SendOrangeSmsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.orange_sms.client_id' => 'client-id',
        'services.orange_sms.client_secret' => 'client-secret',
        'services.orange_sms.sender_address' => 'tel:+22500000000',
    ]);
});

function extractQueuedSmsCode(): string
{
    $code = null;

    Queue::assertPushed(
        SendOrangeSmsMessage::class,
        function (SendOrangeSmsMessage $job) use (&$code): bool {
            preg_match('/\b(\d{6})\b/', $job->message, $matches);
            $code = $matches[1] ?? null;

            return $code !== null;
        },
    );

    expect($code)->toBeString();

    return $code;
}

test('the SMS login screen can be rendered', function () {
    $this->get(route('login.sms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/sms-login')
            ->where('codeSent', false),
        );
});

test('SMS code requests use a generic response and only queue a code for eligible accounts', function () {
    Queue::fake();

    $user = User::factory()->create([
        'phone' => '+2250102030405',
        'phone_verified_at' => now(),
    ]);

    $registeredResponse = $this->from(route('login.sms'))
        ->post(route('login.sms.send'), ['phone' => $user->phone]);

    $registeredResponse
        ->assertRedirect(route('login.sms'))
        ->assertSessionHas(
            'status',
            'Si ce numéro est associé à un compte éligible, un code lui sera envoyé.',
        );

    $unknownResponse = $this->from(route('login.sms'))
        ->post(route('login.sms.send'), ['phone' => '+2250199999999']);

    $unknownResponse
        ->assertRedirect(route('login.sms'))
        ->assertSessionHas(
            'status',
            'Si ce numéro est associé à un compte éligible, un code lui sera envoyé.',
        );

    expect($registeredResponse->getStatusCode())
        ->toBe($unknownResponse->getStatusCode());

    Queue::assertPushed(SendOrangeSmsMessage::class, 1);
});

test('phone details are available to the owner but excluded from shared user data', function () {
    $user = User::factory()->create([
        'phone' => '+2250102030405',
        'phone_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('phone', '+2250102030405')
            ->where('phoneVerified', true)
            ->missing('auth.user.phone')
            ->missing('auth.user.phone_verified_at'),
        );
});

test('a verified phone can authenticate with its one-time code', function () {
    Queue::fake();

    $user = User::factory()->create([
        'phone' => '+2250102030405',
        'phone_verified_at' => now(),
    ]);

    $this->post(route('login.sms.send'), ['phone' => $user->phone])
        ->assertRedirect(route('login.sms'));

    $code = extractQueuedSmsCode();

    $this->post(route('login.sms.verify'), [
        'phone' => $user->phone,
        'code' => $code,
    ])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('an unverified phone does not receive a login code', function () {
    Queue::fake();

    $user = User::factory()->create([
        'phone' => '+2250102030405',
        'phone_verified_at' => null,
    ]);

    $this->post(route('login.sms.send'), ['phone' => $user->phone])
        ->assertSessionHas('status');

    Queue::assertNothingPushed();
});

test('SMS login cannot bypass administrator two-factor authentication', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'role' => 'admin',
        'phone' => '+2250102030405',
        'phone_verified_at' => now(),
    ]);

    $this->post(route('login.sms.send'), ['phone' => $admin->phone])
        ->assertSessionHas('status');

    Queue::assertNothingPushed();
});

test('SMS login cannot bypass a user two-factor challenge', function () {
    Queue::fake();

    $user = User::factory()->withTwoFactor()->create([
        'phone' => '+2250102030405',
        'phone_verified_at' => now(),
    ]);

    $this->post(route('login.sms.send'), ['phone' => $user->phone])
        ->assertSessionHas('status');

    Queue::assertNothingPushed();
});

test('an invalid SMS login code does not authenticate the user', function () {
    Queue::fake();

    $user = User::factory()->create([
        'phone' => '+2250102030405',
        'phone_verified_at' => now(),
    ]);

    $this->post(route('login.sms.send'), ['phone' => $user->phone]);

    $this->from(route('login.sms'))
        ->post(route('login.sms.verify'), [
            'phone' => $user->phone,
            'code' => '000000',
        ])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('SMS code verification is rate limited by phone number', function () {
    Queue::fake();

    $phone = '+2250102030405';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.sms.verify'), [
            'phone' => $phone,
            'code' => '000000',
        ]);
    }

    $this->post(route('login.sms.verify'), [
        'phone' => $phone,
        'code' => '000000',
    ])->assertTooManyRequests();

    $this->assertGuest();
});

test('a user can verify a phone number before using SMS login', function () {
    Queue::fake();
    Cache::flush();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->post(route('phone-verification.send'), [
            'phone' => '+2250102030405',
        ])
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('phone_verification_pending', true);

    $code = extractQueuedSmsCode();

    $this->post(route('phone-verification.confirm'), ['code' => $code])
        ->assertRedirect(route('security.edit'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'phone' => '+2250102030405',
    ]);

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('a phone number cannot be verified with an incorrect code', function () {
    Queue::fake();
    Cache::flush();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('phone-verification.send'), [
            'phone' => '+2250102030405',
        ]);

    $this->from(route('security.edit'))
        ->post(route('phone-verification.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'phone' => null,
        'phone_verified_at' => null,
    ]);
});

test('phone verification is rate limited', function () {
    Queue::fake();

    $user = User::factory()->create();
    $url = route('phone-verification.send');

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post($url, ['phone' => '+2250102030405']);
    }

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post($url, ['phone' => '+2250102030405'])
        ->assertTooManyRequests();
});
