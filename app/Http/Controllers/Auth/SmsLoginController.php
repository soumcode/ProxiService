<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestSmsLoginCodeRequest;
use App\Http\Requests\VerifySmsLoginCodeRequest;
use App\Jobs\SendOrangeSmsMessage;
use App\Models\User;
use App\Services\OrangeSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SmsLoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/sms-login', [
            'phone' => $request->old('phone'),
            'codeSent' => $request->session()->get('sms_login_code_sent', false),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function send(
        RequestSmsLoginCodeRequest $request,
        OrangeSmsService $orangeSms,
    ): RedirectResponse {
        if (! $orangeSms->isConfigured()) {
            return back()->withErrors([
                'phone' => 'La connexion SMS n’est pas disponible pour le moment.',
            ]);
        }

        $phone = $request->string('phone')->toString();
        $user = User::query()
            ->where('phone', $phone)
            ->whereNotNull('phone_verified_at')
            ->first();

        if (
            $user !== null
            && ! $user->isAdmin()
            && $user->two_factor_confirmed_at === null
        ) {
            $code = (string) random_int(100000, 999999);

            Cache::put(
                $this->challengeKey($phone),
                [
                    'user_id' => $user->id,
                    'code_hash' => $this->codeHash($code),
                ],
                now()->addMinutes(5),
            );

            SendOrangeSmsMessage::dispatch(
                $phone,
                "Votre code de connexion ProxiServices est {$code}. Il expire dans 5 minutes.",
            );
        }

        $request->session()->put('sms_login_code_sent', true);

        return to_route('login.sms')
            ->withInput(['phone' => $phone])
            ->with(
                'status',
                'Si ce numéro est associé à un compte éligible, un code lui sera envoyé.',
            );
    }

    public function verify(VerifySmsLoginCodeRequest $request): RedirectResponse
    {
        $phone = $request->string('phone')->toString();
        $code = $request->string('code')->toString();
        $challenge = Cache::get($this->challengeKey($phone));

        if (
            ! is_array($challenge)
            || ! is_int($challenge['user_id'] ?? null)
            || ! is_string($challenge['code_hash'] ?? null)
            || ! hash_equals($challenge['code_hash'], $this->codeHash($code))
        ) {
            return back()->withErrors([
                'code' => 'Le code est invalide ou expiré. Demande un nouveau code.',
            ]);
        }

        $user = User::query()
            ->whereKey($challenge['user_id'])
            ->where('phone', $phone)
            ->whereNotNull('phone_verified_at')
            ->first();

        if (
            $user === null
            || $user->isAdmin()
            || $user->two_factor_confirmed_at !== null
        ) {
            Cache::forget($this->challengeKey($phone));

            return back()->withErrors([
                'code' => 'Le code est invalide ou expiré. Demande un nouveau code.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('sms_login_code_sent');
        Cache::forget($this->challengeKey($phone));

        return redirect()->intended(route('dashboard'));
    }

    private function challengeKey(string $phone): string
    {
        return 'sms-login:'.hash_hmac(
            'sha256',
            $phone,
            (string) config('app.key'),
        );
    }

    private function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
