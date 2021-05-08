<?php

namespace EolabsIo\ThrottlingMiddleware\Contracts;

interface Deferrer
{
    public function getCurrentTime(): int;

    public function sleep(int $milliseconds);
}
