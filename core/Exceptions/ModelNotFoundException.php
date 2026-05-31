<?php
declare(strict_types=1);

namespace Flint\Exceptions;

class ModelNotFoundException extends \RuntimeException
{
    public function __construct(string $model, int|string $id)
    {
        parent::__construct("No {$model} found with id {$id}.");
    }
}
