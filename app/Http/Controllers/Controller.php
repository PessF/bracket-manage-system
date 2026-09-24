<?php

namespace App\Http\Controllers;

use DomainException;
use InvalidArgumentException;
use Throwable;

abstract class Controller
{
    protected function isUserError(Throwable $exception): bool
    {
        return $exception instanceof DomainException || $exception instanceof InvalidArgumentException;
    }

    protected function userErrorMessage(Throwable $exception): string
    {
        if ($this->isUserError($exception)) {
            return $exception->getMessage();
        }

        report($exception);

        return __('ui.request_failed');
    }
}
