<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorService
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $qrCodeUrl = $this->engine->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );

        return (new Writer($renderer))->writeString($qrCodeUrl);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code, 2);
    }

    /**
     * @return array<int, array{code: string, used_at: null}>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => ['code' => Str::upper(Str::random(4).'-'.Str::random(4)), 'used_at' => null])
            ->all();
    }

    /**
     * @param array<int, array{code: string, used_at: string|null}> $recoveryCodes
     * @return array<int, array{code: string, used_at: string|null}>|null Returns the updated codes array
     *         (with the used one marked) on success, or null if the code didn't match any unused entry.
     */
    public function consumeRecoveryCode(array $recoveryCodes, string $submitted): ?array
    {
        $submitted = Str::upper(trim($submitted));

        foreach ($recoveryCodes as $index => $entry) {
            if ($entry['used_at'] === null && hash_equals($entry['code'], $submitted)) {
                $recoveryCodes[$index]['used_at'] = now()->toIso8601String();

                return $recoveryCodes;
            }
        }

        return null;
    }
}
