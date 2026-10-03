<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Admin\Http\Requests\Admin\Auth\TwoFactorCodeRequest;
use Modules\Admin\Services\TwoFactorService;

final class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor)
    {
    }

    /**
     * Mandatory enrollment screen for roles that require 2FA but haven't
     * confirmed it yet (EnforceTwoFactor middleware redirects here).
     */
    public function setup(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.dashboard');
        }

        $secret = $request->session()->get('pending_2fa_secret');

        if (! is_string($secret)) {
            $secret = $this->twoFactor->generateSecret();
            $request->session()->put('pending_2fa_secret', $secret);
        }

        $qrSvg = $this->twoFactor->qrCodeSvg($user, $secret);

        return view('admin::admin.auth.two-factor-setup', [
            'secret' => $secret,
            'qrSvg' => $qrSvg,
        ]);
    }

    public function confirm(TwoFactorCodeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $secret = $request->session()->get('pending_2fa_secret');

        if (! is_string($secret) || ! $this->twoFactor->verify($secret, (string) $request->string('code'))) {
            return back()->withErrors(['code' => __('Invalid authentication code.')]);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('pending_2fa_secret');
        $request->session()->put('admin_2fa_passed', true);

        return redirect()
            ->route('admin.2fa.recovery-codes')
            ->with('fresh_recovery_codes', array_column($recoveryCodes, 'code'));
    }

    public function recoveryCodes(Request $request): View
    {
        $codes = $request->session()->pull('fresh_recovery_codes', []);

        return view('admin::admin.auth.two-factor-recovery-codes', ['codes' => $codes]);
    }

    public function challenge(): View
    {
        return view('admin::admin.auth.two-factor-challenge');
    }

    public function verify(TwoFactorCodeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $code = (string) $request->string('code');

        if ($this->twoFactor->verify((string) $user->two_factor_secret, $code)) {
            $request->session()->put('admin_2fa_passed', true);

            return redirect()->intended(route('admin.dashboard'));
        }

        $updated = $this->twoFactor->consumeRecoveryCode($user->two_factor_recovery_codes ?? [], $code);

        if ($updated !== null) {
            $user->forceFill(['two_factor_recovery_codes' => $updated])->save();
            $request->session()->put('admin_2fa_passed', true);

            return redirect()
                ->intended(route('admin.dashboard'))
                ->with('status', __('Signed in with a recovery code. Generate new codes soon from your security settings.'));
        }

        return back()->withErrors(['code' => __('Invalid authentication code.')]);
    }
}
