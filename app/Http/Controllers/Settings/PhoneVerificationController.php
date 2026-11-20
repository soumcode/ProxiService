<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPhoneVerificationRequest;
use App\Http\Requests\StartPhoneVerificationRequest;
use App\Jobs\SendOrangeSmsMessage;
use App\Services\OrangeSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class PhoneVerificationController extends Controller
{
    public function send(
        StartPhoneVerificationRequest $request,
        OrangeSmsService $orangeSms,
    ): RedirectResponse {
        abort_unless(
            $orangeSms->isConfigured(),
            503,
            'La vérification par SMS n’est pas disponible pour le moment.',
        );

        $user = $request->user();
        $phone = $request->string('phone')->toString();

        if (
            $user->phone === $phone
            && $user->phone_verified_at !== null
        ) {
            $request->session()->forget('phone_verification_pending');

            return back()->with(
                'status',
                'Ce numéro est déjà vérifié.',
            );
        }

        $request->session()->put('phone_verification_pending_phone', $phone);

        $code = (string) random_int(100000, 999999);

        Cache::put(
            $this->challengeKey($user->id),
            [
                'phone' => $phone,
                'code_hash' => $this->codeHash($code),
            ],
            now()->addMinutes(5),
        );

        SendOrangeSmsMessage::dispatch(
            $phone,
            "Votre code de vérification ProxiServices est {$code}. Il expire dans 5 minutes.",
        );

        $request->session()->put('phone_verification_pending', true);

        return back()
            ->withInput(['phone' => $phone])
            ->with('status', 'Un code de vérification a été envoyé.');
    }

    public function confirm(ConfirmPhoneVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $code = $request->string('code')->toString();
        $challenge = Cache::get($this->challengeKey($user->id));

        if (
            ! is_array($challenge)
            || ! is_string($challenge['phone'] ?? null)
            || ! is_string($challenge['code_hash'] ?? null)
            || ! hash_equals($challenge['code_hash'], $this->codeHash($code))
        ) {
            return back()->withErrors([
                'code' => 'Le code est invalide ou expiré. Demande un nouveau code.',
            ]);
        }

        $user->phone = $challenge['phone'];
        $user->phone_verified_at = now();
        $user->save();

        $request->session()->forget('phone_verification_pending');
        $request->session()->forget('phone_verification_pending_phone');
        Cache::forget($this->challengeKey($user->id));

        return back()->with('status', 'Ton numéro de téléphone est vérifié.');
    }

    private function challengeKey(int $userId): string
    {
        return "phone-verification:{$userId}";
    }

    private function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
