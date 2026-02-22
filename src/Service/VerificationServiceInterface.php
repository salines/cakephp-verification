<?php
declare(strict_types=1);

namespace CakeVerification\Service;

use CakeVerification\Value\VerificationResult;
use CakeVerification\Verificator\VerificationVerificatorInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Contract for running post-authentication verification flows (Email OTP, SMS OTP, TOTP).
 *
 * Responsibilities:
 * - Execute the verification pipeline for a given HTTP request.
 * - Expose normalized configuration (steps, drivers, routing).
 * - Resolve verificator drivers by step name.
 * - Provide a result snapshot of the current verification state.
 */
interface VerificationServiceInterface
{
    /**
     * Execute the verification pipeline for the current request.
     *
     * Identifies the current user (if any) from the request and determines pending steps.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return \CakeVerification\Value\VerificationResult Verification state (identity, pending steps, next route/URL)
     */
    public function verify(ServerRequestInterface $request): VerificationResult;

    /**
     * Get the merged configuration used by the service.
     *
     * @return array<string,mixed> Configuration array
     */
    public function getConfig(): array;

    /**
     * Get the normalized list of verification steps.
     *
     * @return array<int,string> Ordered step names
     */
    public function getSteps(): array;

    /**
     * Resolve a verificator driver instance for a step.
     *
     * @param string $name Step name (e.g. "email_otp", "sms_otp", "totp")
     * @return \CakeVerification\Verificator\VerificationVerificatorInterface|null Driver instance or null if unmapped
     */
    public function getDriver(string $name): ?VerificationVerificatorInterface;

    /**
     * Check whether there are pending verification steps for the request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @return bool True when at least one step is pending
     */
    public function hasPending(ServerRequestInterface $request): bool;

    /**
     * Get the list of OTP driver step names (all steps except emailVerify).
     *
     * @return array<int,string>
     */
    public function getAvailableOtpDrivers(): array;
}
