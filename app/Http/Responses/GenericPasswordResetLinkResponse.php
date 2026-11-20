<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

class GenericPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = 'Si un compte correspond à cette adresse, un lien de réinitialisation lui sera envoyé.';

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message]);
        }

        return redirect()
            ->route('password.request')
            ->with('status', $message);
    }
}
