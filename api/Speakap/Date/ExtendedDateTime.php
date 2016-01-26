<?php

namespace Speakap\Date;

use Speakap\Util\TimeUtils;

/**
 * Adds milliseconds support to PHP's DataTime
 *
 * Use 'ms' in your date formats to output milliseconds:
 * <code>
 * <?php
 * $xdt = new ExtendedDateTime();
 * echo $xdt->format('ms milliseconds'); // "497 milliseconds"
 * echo $xdt->format(\DateTime::ISO8601); // "2013-08-23\T11:14:22.598+0000"
 * ?>
 * </code>
 *
 * In ISO 8601 date times the milliseconds are automagically injected.
 *
 * Known issues:
 *  * Doesn't support < and > operators with microsecond precision
 *
 * @SuppressWarnings(PHPMD.CamelCaseVariableName, PHPMD.ShortVariableName, PHPMD.BooleanArgumentFlag)
 */
class ExtendedDateTime extends \DateTime
{
    const PRECISION_MICROSECOND = 'micro';
    const PRECISION_MILLISECOND = 'milli';

    /**
     * @var integer
     */
    protected $microseconds = 0;

    public function __construct($time = 'now', \DateTimeZone $timezone = null)
    {
        if (self::isRelativeFormat($time)) {
            $time = microtime(true);
            $µs = TimeUtils::getMicroseconds($time);

            parent::__construct(date('Y-m-d H:i:s.' . $µs, $time), $timezone);
            $this->microseconds = pow(10, 6 - strlen($µs)) * $µs;

            return;
        }

        parent::__construct($time, $timezone);

        /*
         * Workaround to support milli- and microseconds when passend directly
         * into constructor
         */
        if (0 === $this->microseconds && preg_match('/\.(\d{3,6})/', $time, $match)) {
            $this->microseconds = pow(10, 6 - strlen($match[1])) * $match[1];
        }
    }

    /**
     * @param float         $microtime
     * @param \DateTimeZone $timezone
     *
     * @return ExtendedDateTime
     */
    public static function createFromMicrotime($microtime, \DateTimeZone $timezone = null)
    {
        $µs = TimeUtils::getMicroseconds($microtime);

        $instance = new self(date('Y-m-d H:i:s.' . $µs, $microtime), $timezone);
        $instance->microseconds = $µs;

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromFormat($format, $time, $timezone = null)
    {
        $dt = ($timezone instanceof \DateTimeZone)
                ? parent::createFromFormat($format, $time, $timezone)
                : parent::createFromFormat($format, $time);

        if ($dt === false) {
            $dt = new self($time, $timezone);
        }
        return self::createFromDateTime($dt);
    }

    /**
     * @param  \DateTime $dt
     *
     * @return ExtendedDateTime
     */
    public static function createFromDateTime(\DateTime $dt)
    {
        $u = $dt->format('u');
        $µs = (0 == $u) ? TimeUtils::getMicroseconds() : $u;

        $instance = new ExtendedDateTime($dt->format('Y-m-d H:i:s.') . $µs, $dt->getTimezone());
        $instance->microseconds = $µs;

        return $instance;
    }

    /**
     * @param  ExtendedDateTime|\DateTime $datetime2
     * @param  boolean                    $absolute
     *
     * @return ExtendedDateInterval|\DateInterval
     *
     *
     */
    public function diff($datetime2, $absolute = false)
    {
        $dt2 = clone $datetime2;

        $µs1 = $this->microseconds;
        $µs2 = 0;
        if ($dt2 instanceof ExtendedDateTime) {
            $µs2 = $dt2->microseconds;
        } elseif (!$dt2 instanceof \DateTime) {
            return false;
        }

        if ($dt2 >= $this) {
            $µsDiff = $µs2 - $µs1;
            if ($µsDiff < 0) {
                $dt2->modify('-1 second');
                $µsDiff += 1000000;
            }
            $invert = false;
        } else {
            $µsDiff = $µs1 - $µs2;
            if ($µsDiff < 0) {
                $dt2->modify('+1 second');
                $µsDiff += 1000000;
            }
            $invert = true;
        }

        $interval = ExtendedDateInterval::createFromInterval(parent::diff($dt2, true));

        $interval->f = (int) floor($µsDiff / 1000);
        $interval->u = ($µsDiff - (1000 * $interval->f));
        $interval->invert = !$absolute && $invert;

        return $interval;
    }

    /**
     * {@inheritdoc}
     */
    public function add($interval)
    {
        parent::add($interval);

        if ($interval instanceof ExtendedDateInterval) {
            $microseconds = $this->microseconds + round(($interval->f * 1000) + $interval->u);
            $this->modify(floor($microseconds / 1000000) . ' seconds');
            $this->microseconds = $microseconds % 1000000;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sub($interval)
    {
        parent::sub($interval);

        if ($interval instanceof ExtendedDateInterval) {
            $microseconds = $this->microseconds - round(($interval->f * 1000) + $interval->u);
            $this->modify(floor($microseconds / 1000000) . ' seconds');
            $this->microseconds = $microseconds % 1000000;

            if ($this->microseconds < 0) {
                $this->microseconds += 1000000;
            }
        }
    }

    /**
     * @param  string $precision Either 'milli' or the default 'micro'
     *
     * @return float
     */
    public function getMicrotime($precision = self::PRECISION_MICROSECOND)
    {
        return $precision === self::PRECISION_MILLISECOND
                ? (float) $this->format('U\.f')
                : (float) $this->format('U\.u');
    }

    /**
     * @return integer
     */
    public function getMilliseconds()
    {
        return (int) ($this->microseconds / 1000);
    }

    /**
     * @return integer
     */
    public function getMicroseconds()
    {
        return $this->microseconds;
    }

    /**
     * Replaces 'ms' in format with milliseconds and adds milliseconds to
     * ISO formatted dates
     *
     * {@inheritdoc}
     */
    public function format($format)
    {
        if ('c' === $format || $format === self::ISO8601) {
            $date = parent::format($format);
            $ms = sprintf('%03u', $this->microseconds / 1000);

            return preg_replace('/(T\d{2}:\d{2}:\d{2})/', '${1}.' . $ms, $date);
        }

        $format = preg_replace('/(?<!\\\|\pL)f|ms(?=[\sPOT]|$)/', '%03u', $format);

        return parent::format(sprintf($format, $this->microseconds / 1000));
    }

    /**
     * @param  string $format Date and Time format
     *
     * @return boolean
     *
     * @see http://www.php.net/manual/en/datetime.formats.relative.php
     */
    private static function isRelativeFormat($format)
    {
        return 'now' === $format;
    }

    public function __wakeup()
    {
        return static::createFromDateTime(parent::__wakeup());
    }
}
