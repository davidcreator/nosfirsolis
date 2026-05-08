<?php

namespace System\Engine;

abstract class Model
{
    use TemporalClockTrait;

    public function __construct(protected Registry $registry)
    {
    }

    public function __get(string $key): mixed
    {
        return $this->registry->get($key);
    }

    protected function modelClockUnixNow(): int
    {
        return $this->clockUnixNow();
    }

    protected function modelClockDateTimeNow(): string
    {
        return $this->clockDateTimeNow();
    }

    protected function modelClockIso8601Now(): string
    {
        return $this->clockIso8601Now();
    }

    protected function modelClockFormat(string $format): string
    {
        return $this->clockFormat($format);
    }

    protected function modelClockFormatAt(int $timestamp, string $format): string
    {
        return $this->clockFormatAt($timestamp, $format);
    }
}
