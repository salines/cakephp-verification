<?php
declare(strict_types=1);

namespace CakeVerification\View\Helper;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\View\Helper;
use CakeVerification\Transport\Sms\Message;

class VerificationHelper extends Helper
{
    /**
     * Render a simple QR placeholder for the given data.
     *
     * @param string $data QR payload
     * @return string
     */
    public function qrCode(string $data): string
    {
        if (!class_exists(Writer::class)) {
            return '<div class="qrcode">' . htmlspecialchars($data) . '</div>';
        }

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($data);

        return '<div class="qrcode">' . $svg . '</div>';
    }

    /**
     * Return the last SMS code sent via DummyTransport (development only).
     *
     * @return string|null
     */
    public function lastSmsCode(): ?string
    {
        if (!Configure::read('debug')) {
            return null;
        }
        if (!class_exists(Cache::class)) {
            return null;
        }
        $message = Cache::read('verification_last_sms', 'default');
        if (!$message instanceof Message) {
            return null;
        }
        $digits = (string)preg_replace('/\D+/', '', $message->body);
        $length = (int)(Configure::read('Verification.otp.length') ?? 6);
        if ($length > 0 && strlen($digits) >= $length) {
            return substr($digits, 0, $length);
        }

        return $digits !== '' ? $digits : null;
    }
}
