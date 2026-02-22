<?php
declare(strict_types=1);

namespace Verification\Value;

use Authentication\IdentityInterface;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;
use Cake\Utility\Inflector;
use JsonSerializable;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Immutable snapshot of the verification state for the current request.
 *
 * Responsibilities:
 * - Hold the authenticated identity (if any).
 * - Expose the ordered list of pending steps (e.g. ['email_otp','totp','sms_otp']).
 * - Provide the next route (array) and absolute URL (string) where verification should continue.
 * - Report whether the user is fully verified.
 *
 * Notes:
 * - This is a pure value object. All "mutators" return a cloned instance.
 * - Route → URL resolution uses Router and the provided ServerRequest to build an absolute URL.
 */
final class VerificationResult implements JsonSerializable
{
    /**
     * @var \Authentication\IdentityInterface|null
     */
    private ?IdentityInterface $identity;

    /**
     * @var array<int,string> Ordered, unique, normalized (lowercase) step names
     */
    private array $pendingSteps;

    /**
     * @var array<int|string,mixed> Cake route array for the next verification hop
     */
    private array $nextRoute;

    /**
     * @var string Absolute URL for the next verification hop
     */
    private string $nextUrl;

    /**
     * @param \Authentication\IdentityInterface|null $identity
     * @param array<int,string> $pendingSteps
     * @param array<int|string,mixed> $nextRoute
     * @param string $nextUrl
     */
    private function __construct(
        ?IdentityInterface $identity,
        array $pendingSteps,
        array $nextRoute,
        string $nextUrl,
    ) {
        $this->identity = $identity;
        $this->pendingSteps = $pendingSteps;
        $this->nextRoute = $nextRoute;
        $this->nextUrl = $nextUrl;
    }

    /**
     * Factory that resolves the absolute URL for $nextRoute using the current request.
     *
     * @param \Authentication\IdentityInterface|null $identity
     * @param array<int,string> $pendingSteps
     * @param array<int|string,mixed> $nextRoute Cake-style route array
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return static
     */
    public static function fromRequest(
        ?IdentityInterface $identity,
        array $pendingSteps,
        array $nextRoute,
        ServerRequestInterface $request,
    ): self {
        $normalizedSteps = self::normalizeSteps($pendingSteps);
        $normalizedRoute = self::normalizeRoute($nextRoute);
        $absoluteUrl = '';
        if ($normalizedRoute !== []) {
            try {
                $absoluteUrl = Router::url($normalizedRoute + ['_full' => true], true);
            } catch (MissingRouteException) {
                $absoluteUrl = '';
            }
        }

        return new self($identity, $normalizedSteps, $normalizedRoute, $absoluteUrl);
    }

    /**
     * Returns the authenticated identity, if any.
     */
    public function identity(): ?IdentityInterface
    {
        return $this->identity;
    }

    /**
     * Returns the ordered list of pending steps.
     *
     * @return array<int,string>
     */
    public function pendingSteps(): array
    {
        return $this->pendingSteps;
    }

    /**
     * Returns true when there are no pending steps.
     */
    public function isVerified(): bool
    {
        return $this->pendingSteps === [];
    }

    /**
     * Returns the first pending step or null when fully verified.
     */
    public function firstPendingStep(): ?string
    {
        return $this->pendingSteps[0] ?? null;
    }

    /**
     * Checks whether a given step is still pending.
     */
    public function hasStep(string $step): bool
    {
        $needle = self::normalizeStep($step);

        return in_array($needle, $this->pendingSteps, true);
    }

    /**
     * Returns a new instance with the provided next route (URL recalculated).
     *
     * @param array<int|string,mixed> $route
     */
    public function withNextRoute(array $route, ServerRequestInterface $request): self
    {
        $clone = clone $this;
        $normalizedRoute = self::normalizeRoute($route);
        $clone->nextRoute = $normalizedRoute;
        try {
            $clone->nextUrl = Router::url($normalizedRoute + ['_full' => true], true);
        } catch (MissingRouteException) {
            $clone->nextUrl = '';
        }

        return $clone;
    }

    /**
     * Returns a new instance with the given step removed from the pending list.
     * If the step is not present, the same ordering is preserved.
     */
    public function withoutStep(string $step): self
    {
        $needle = self::normalizeStep($step);
        $clone = clone $this;
        $clone->pendingSteps = array_values(array_filter(
            $this->pendingSteps,
            static fn(string $s): bool => $s !== $needle,
        ));

        return $clone;
    }

    /**
     * Returns the route array for the next verification hop.
     *
     * @return array<int|string,mixed>
     */
    public function nextRoute(): array
    {
        return $this->nextRoute;
    }

    /**
     * Returns the absolute URL for the next verification hop.
     */
    public function nextUrl(): string
    {
        return $this->nextUrl;
    }

    /**
     * Array representation suitable for APIs.
     *
     * @return array{
     *   verified: bool,
     *   first_pending_step: string|null,
     *   pending_steps: array<int,string>,
     *   next_route: array<int|string,mixed>,
     *   next_url: string
     * }
     */
    public function toArray(): array
    {
        return [
            'verified' => $this->isVerified(),
            'first_pending_step' => $this->firstPendingStep(),
            'pending_steps' => $this->pendingSteps,
            'next_route' => $this->nextRoute,
            'next_url' => $this->nextUrl,
        ];
    }

    /**
     * @inheritDoc
     */

    /**
     * @return array{
     *   verified: bool,
     *   first_pending_step: string|null,
     *   pending_steps: array<int,string>,
     *   next_route: array<int|string,mixed>,
     *   next_url: string
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<int,string> $steps
     * @return array<int,string>
     */
    private static function normalizeSteps(array $steps): array
    {
        $norm = [];
        foreach ($steps as $step) {
            $s = self::normalizeStep($step);
            if ($s !== '' && !in_array($s, $norm, true)) {
                $norm[] = $s;
            }
        }

        return $norm;
    }

    /**
     * @param string $step Raw step name
     * @return string
     */
    private static function normalizeStep(string $step): string
    {
        $s = trim($step);
        if ($s === '') {
            return '';
        }
        if (!str_contains($s, '_') && mb_strtoupper($s) === $s) {
            $s = mb_strtolower($s);
        } elseif (str_contains($s, '_')) {
            $s = mb_strtolower($s);
        } else {
            $s = mb_strtolower(Inflector::underscore($s));
        }
        $s = (string)preg_replace('/[^a-z0-9_]+/i', '_', $s);
        $s = (string)preg_replace('/_{2,}/', '_', $s);

        return trim($s, '_');
    }

    /**
     * @param array<int|string, mixed> $route
     * @return array<int|string, mixed>
     */
    private static function normalizeRoute(array $route): array
    {
        foreach (['plugin', 'prefix'] as $key) {
            if (!array_key_exists($key, $route)) {
                continue;
            }
            if ($route[$key] === null || $route[$key] === false || $route[$key] === '') {
                unset($route[$key]);
            }
        }

        return $route;
    }
}
