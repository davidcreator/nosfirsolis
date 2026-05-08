<?php

namespace System\Engine;

trait TemporalClockTrait
{
    private function clockUnixNow(): int
    {
        return mktime(
            (int) date('H'),
            (int) date('i'),
            (int) date('s'),
            (int) date('n'),
            (int) date('j'),
            (int) date('Y')
        );
    }

    private function clockDateTimeNow(): string
    {
        return date('Y-m-d H:i:s', $this->clockUnixNow());
    }

    private function clockDateTimeFromUnix(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function clockDateTimeAfterSeconds(int $seconds): string
    {
        return date('Y-m-d H:i:s', $this->clockUnixNow() + $seconds);
    }

    private function clockIso8601Now(): string
    {
        return date('c', $this->clockUnixNow());
    }

    private function clockFormat(string $format): string
    {
        return date($format, $this->clockUnixNow());
    }

    private function clockFormatAt(int $timestamp, string $format): string
    {
        return date($format, $timestamp);
    }
}
