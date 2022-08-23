<?php

namespace Src\User\Domain\Exceptions;

final class PasswordsDoNotMatchException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Passwords do not match');
    }
}