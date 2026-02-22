<?php
declare(strict_types=1);

namespace CakeVerification\Verificator;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Contract for a single verification step (e.g. Email OTP, SMS OTP, TOTP).
 */
interface VerificationVerificatorInterface
{
    /**
     * Unique step key (e.g. "email_otp", "sms_otp", "totp").
     *
     * @return string Step identifier
     */
    public function key(): string;

    /**
     * Human readable label.
     *
     * @return string Step label
     */
    public function label(): string;

    /**
     * Return a cloned instance with merged config.
     *
     * @param array<string,mixed> $config Configuration overrides
     * @return static New instance with merged config
     */
    public function withConfig(array $config): static;

    /**
     * Get current config.
     *
     * @return array<string,mixed> Configuration
     */
    public function getConfig(): array;

    /**
     * Can this step be started for the given identity?
     * (e.g. user has an email/phone or enrolled TOTP secret)
     *
     * @param \Authentication\IdentityInterface $identity Authenticated identity
     * @return bool True if prerequisites exist
     */
    public function canStart(IdentityInterface $identity): bool;

    /**
     * Start the step (issue OTP, send message, etc).
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @param \Authentication\IdentityInterface $identity Authenticated identity
     * @return void
     */
    public function start(ServerRequestInterface $request, IdentityInterface $identity): void;

    /**
     * Does this step require user input to complete?
     *
     * @return bool True if an input form/code is expected
     */
    public function requiresInput(): bool;

    /**
     * Describe expected input fields (for validation scaffolding).
     *
     * @return array<string,string> Field => rule description
     */
    public function expectedFields(): array;

    /**
     * Verify a user-provided payload (e.g. OTP code).
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request HTTP request
     * @param \Authentication\IdentityInterface $identity Authenticated identity
     * @param array<string,mixed> $data Submitted data
     * @return bool True on successful verification
     */
    public function verify(ServerRequestInterface $request, IdentityInterface $identity, array $data): bool;

    /**
     * Check whether the identity is already verified for this step.
     *
     * Implementations typically check a configured "verified" flag/date
     * (e.g. email_verified_at, phone_verified_at) or, for TOTP, both
     * presence of secret and an explicit verified flag/date if configured.
     *
     * @param \Authentication\IdentityInterface $identity Authenticated identity
     * @return bool True if the step is already satisfied
     */
    public function isVerified(IdentityInterface $identity): bool;
}
