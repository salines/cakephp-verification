<?php
declare(strict_types=1);

namespace Verification\Verificator\Driver;

use Authentication\IdentityInterface;

trait ReadFieldTrait
{
    /**
     * @param \Authentication\IdentityInterface $identity Identity
     * @param string $field Field name
     * @return mixed
     */
    protected function readField(IdentityInterface $identity, string $field): mixed
    {
        $orig = $identity->getOriginalData();
        if (is_object($orig) && method_exists($orig, 'get')) {
            return $orig->get($field);
        }
        if (is_array($orig)) {
            return $orig[$field] ?? null;
        }

        return null;
    }
}
