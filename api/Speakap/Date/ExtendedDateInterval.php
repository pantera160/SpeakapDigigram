<?php

namespace Speakap\Date;

/**
 * Date interval with support for milli (F) and microseconds (U)
 *
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 * @SuppressWarnings(PHPMD.ShortVariableNames)
 */
class ExtendedDateInterval extends \DateInterval
{
    /**
     * Seconds with optional fraction
     *
     * @var float
     */
    public $seconds;

    /**
     * Milliseconds
     *
     * @var number
     */
    public $f;

    /**
     * Microseconds
     *
     * @var number
     */
    public $u;

    public function __construct($interval)
    {
        $this->seconds = $this->f = $this->u = 0;

        if (preg_match('/(\d*)[.,]?(\d*)S/', $interval, $match)) {
            $fraction = (float) rtrim($match[1] . '.' . $match[2], '.');
            if ($fraction < 1/1000) {
                $this->u = 1000000 * $fraction;
            } elseif ($fraction < 1) {
                $this->f = 1000 * $fraction;
            } elseif ($fraction >= 1) {
                $this->seconds = $fraction;
            }
            $interval = str_replace($match[0], floor($this->seconds) . 'S', $interval);
        }
        if (preg_match('/(\d*)[.,]?(\d*)F/', $interval, $match)) {
            $this->f = (float) rtrim($match[1] . '.' . $match[2], '.');
            $interval = str_replace($match[0], '', $interval);
        }
        if (preg_match('/(\d*)[.,]?(\d*)U/', $interval, $match)) {
            $this->u = (float) rtrim($match[1] . '.' . $match[2], '.');
            $interval = str_replace($match[0], '', $interval);
        }

        ('PT' !== $interval) ? parent::__construct(rtrim($interval, 'T')) : parent::__construct('P0Y');
    }

    /**
     * @param  \DateInterval $interval
     *
     * @return ExtendedDateInterval
     */
    public static function createFromInterval(\DateInterval $interval)
    {
        /* @var $instance ExtendedDateInterval */
        $instance = new static('P0Y');

        $instance->y = $interval->y;
        $instance->m = $interval->m;
        $instance->d = $interval->d;
        $instance->h = $interval->h;
        $instance->i = $interval->i;
        $instance->s = $instance->seconds =$interval->s;
        $instance->invert = $interval->invert;
        $instance->days = $interval->days;

        $instance->u = $instance->f = 0;

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function format($format)
    {
        $format = preg_replace('/(?<=%)([fsu])/i', '%\\1', $format);

        $str = parent::format($format);

        $str = str_replace('%f', $this->f, $str);
        $str = str_replace('%F', sprintf('%02d', $this->f), $str);
        $str = str_replace('%s', $this->getSeconds(), $str);
        $str = str_replace('%S', $this->getSeconds(), $str);
        $str = str_replace('%u', $this->u, $str);
        $str = str_replace('%U', sprintf('%02d', $this->u), $str);

        return $str;
    }

    /**
     * @return float
     */
    private function getSeconds()
    {
        return (float) $this->seconds + ($this->f / 1000) + ($this->u / 1000000);
    }
}