<?php

function fix_timezone ($time)
{
    return $time - 21600; // 6 hours
}


define('HUMAN_TIME_TOKENS', serialize( array (
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    )));

function time_diff_human ($time, $reference = NULL)
{
    if (is_null($reference))
        $reference = time();

    $diff = fix_timezone($time) - $reference;
    if ($diff == 0)
        return 'now';

    $absdiff = abs($diff);

    foreach (unserialize(HUMAN_TIME_TOKENS) as $unit => $text) {
        if ($absdiff >= $unit) {
            $numberOfUnits = (int) ($absdiff / $unit);
            $rv = $numberOfUnits . ' ' . $text;
            if ($numberOfUnits > 1)
                $rv .= 's';
            break;
        }
    }

    return ($diff <= 0) ?
        ($rv . ' ago') :
        ('in ' . $rv);
}

?>
