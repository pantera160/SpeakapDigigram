<?php

namespace Speakap\Util;

use Speakap\Date\ExtendedDateInterval;

/**
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class TimeUtils
{
    const UNIT_MILLISECOND = 'ms';
    const UNIT_MICROSECOND = 'µs';

    /**
     * @param  integer $since
     * @param  float $microtime Optional
     *
     * @return integer
     */
    public static function getMicrosecondsSince($since = 0, $microtime = null)
    {
        if (null === $microtime) {
            $microtime = microtime(true);
        }

        $time = $microtime - $since;
        $µs = (int) round($time * 1000000);

        return ($µs - (self::getMillisecondsSince($since, $microtime) * 1000));
    }

    /**
     * @param  float $microtime Optional
     *
     * @return integer
     */
    public static function getMicroseconds($microtime = null)
    {
        if (null === $microtime) {
            $microtime = microtime(true);
        }


        return (int) (round($microtime - floor($microtime), 6) * 1000000);
    }

    /**
     * Returns the number of milliseconds for a given, or the current PHP
     * microtime. Please take note that the resulting integer is always rounded
     * down!
     *
     * @param  integer $since
     * @param  float $microtime Optional
     *
     * @return integer
     */
    public static function getMillisecondsSince($since = 0, $microtime = null)
    {
        if (null === $microtime) {
            $microtime = microtime(true);
        }

        $time = $microtime - $since;
        $µs = round($time * 1000000);

        return (int) ($µs / 1000);
    }

    /**
     * Returns separate millisecond and microsecond units for a given, or the
     * current PHP microtime
     *
     * @param  integer $since
     * @param  float $microtime Optional
     *
     * @return array Units of time
     */
    public static function getTimeUnitsSince($since = 0, $microtime = null)
    {
        if (null === $microtime) {
            $microtime = microtime(true);
        }

        return array(
            self::UNIT_MILLISECOND => self::getMillisecondsSince($since, $microtime),
            self::UNIT_MICROSECOND => self::getMicrosecondsSince($since, $microtime)
        );
    }

    /**
     * @param  \DateTime $dt
     *
     * @return float
     */
    public static function convertDateTimeToMicrotime(\DateTime $dt)
    {
        $time = $dt->getTimestamp();
        $µs = (int) $dt->format('u');

        $time += $µs / 1000000;

        return (float) $time;
    }

    /**
     * Returns the total number of seconds in an interval, not taking leap
     * years into account and using 30 days per month.
     *
     * @param  \DateInterval $interval
     *
     * @return integer
     */
    public static function convertIntervalToSeconds(\DateInterval $interval)
    {
        $secs = $interval->s;

        $secs += $interval->y *  3600 * 24 * 365;
        $secs += $interval->m *  3600 * 24 *  30;
        $secs += $interval->d *  3600 * 24;
        $secs += $interval->h *  3600;
        $secs += $interval->i *    60;

        return $secs;
    }

    /**
     * @param  ExtendedDateInterval $interval
     *
     * @return integer
     */
    public static function convertIntervalToMicroseconds(ExtendedDateInterval $interval)
    {
        $µs = 1000000 * self::convertIntervalToSeconds($interval);

        return (int) round($µs + ($interval->f * 1000) + $interval->u);
    }
}
