<?php

namespace EolabsIo\ThrottlingMiddleware;

use EolabsIo\ThrottlingMiddleware\Concerns\HasThrottlingParameters;
use EolabsIo\ThrottlingMiddleware\Contracts\Deferrer;
use Illuminate\Support\Carbon;

class Throttle
{
    use HasThrottlingParameters;

    /** @var bool */
    private $throttled = false;

    /** @var EolabsIo\ThrottlingMiddleware\Contracts\Deferrer */
    protected $deferrer;

    public function __construct(Deferrer $deferrer)
    {
        $this->deferrer = $deferrer;
    }

    public function then(callable $callback, callable $failure = null)
    {
        $this->evaluateThrottle();

        if ($this->isThrottled()) {
            $this->blockUntilAvailable();
        }

        $this->decreaseRequestQuota();

        return $callback();
    }

    public function blockUntilAvailable(): void
    {
        $delayUntilNextRequest = ($this->getThrottleDuration() * 1000);
        $this->deferrer->sleep($delayUntilNextRequest);
    }

    public function isThrottled(): bool
    {
        return $this->throttled;
    }

    public function evaluateThrottle(): self
    {
        $this->restoreRequestQuota();
        $this->throttled = ($this->getRequestQuota() == 0);

        return $this;
    }

    public function restoreRequestQuota(): self
    {
        $secondsSinceLastRequest = $this->getLastRequestDuration();

        // restore rate of (one request) every (second)
        $restoredRequests = (int) ($secondsSinceLastRequest * $this->restoreRatePerSecond);

        if ($restoredRequests > 0) {
            $this->addNumberOfRequests($restoredRequests);
        }

        return $this;
    }

    public function getLastRequestDuration(): float
    {
        $lastRequest = $this->getLastRequest();
        return (Carbon::now()->diffInMilliseconds($lastRequest) / 1000);
    }

    public function getThrottleDuration(): float
    {
        $secondsSinceLastRequest = $this->getLastRequestDuration();
        $secondsPerRestoreRate = (1/$this->restoreRatePerSecond);

        return (($secondsPerRestoreRate)-$secondsSinceLastRequest);
    }
}
