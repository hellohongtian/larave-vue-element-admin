<?php
/**
 * 日期辅助函数
 *
 * @author    Liu Bo
 * @copyright liubo9@xin.com
 */


/**
 * 返回的MySQL格式的当前日期时间值
 *
 * @param boolean
 * @return string
 */
function datetime_now($hms = TRUE)
{
    if ($hms)
    {
        return date("Y-m-d H:i:s");
    }
    else
    {
        return date("Y-m-d");
    }
}
/**
 * 跟据提供的时间，返回一个时间戳
 *
 * @param string date (optional)
 * @return string
 */
function timestamp($date = NULL)
{
    if (empty($date))
    {
        return time();
    }

    return strtotime($date);
}

/**
 * 跟据日期返回月份值
 *
 * @param string date (optional)
 * @param string options are 'm/numeric', 'F/long', 'M/short' <- default (optional)
 * @return string
 */
function month($date = NULL, $format = 'M')
{
    $ts = timestamp($date);
    switch ($format)
    {
        case 'm':
            return date('m', $ts);
        case 'n':
        case 'numeric':
            return date('n', $ts);
        case 'M':
        case 'short':
            return date('M', $ts);
        default:
            return date('F', $ts);
    }
}

/**
 * 跟据日期返回日的值
 *
 * @param string date (optional)
 * @param string options are 'd/leading', 'j' <- default (optional)
 * @return string
 */
function day($date = NULL, $format = 'j')
{
    switch ($format)
    {
        case 'd':
        case 'leading':
            return date('d', timestamp($date));
        default:
            return date('j', timestamp($date));
    }
}

/**
 * 跟据日期返回的一周中的第几天
 *
 * @param string date (optional)
 * @param string options are 'l/full', 'N/numeric', 'D' <- default (optional)
 * @return string
 */
function weekday($date = NULL, $format = 'D')
{
    $ts = timestamp($date);
    switch ($format)
    {
        case 'l':
        case 'full':
            return date('l', $ts);
        case 'N':
        case 'numeric':
            return date('N', $ts);
        default:
            return date('D', $ts);
    }
}

/**
 * 跟据日期返回的年
 *
 * @param string date (optional)
 * @param string options are 'y/short', 'Y/long' <- default (optional)
 * @return string
 */
function year($date = NULL, $format = 'Y')
{
    $ts = timestamp($date);
    switch ($format)
    {
        case 'y':
        case 'short':
            return date('y', $ts);
        default:
            return date('Y', $ts);
    }
}

/**
 * 跟据日期返回的小时
 *
 * @param string date (optional)
 * @param string options are '24/military', '12' <- default (optional)
 * @return string
 */
function hour($date = NULL, $format = '12')
{
    $ts = timestamp($date);
    switch ($format)
    {
        case '24':
        case 'military':
            return date('H', $ts);
        default:
            return date('h', $ts);
    }
}

/**
 * 跟据日期返回的分钟
 *
 * @param string date (optional)
 * @param string options are 'noleading', 'leading' <- default (optional)
 * @return string
 */
function minute($date = NULL, $format = 'leading')
{
    $min = date('i', timestamp($date));
    if ($format != 'leading')
    {
        return (int) $min;
    }
    return $min;
}

/**
 * 跟据日期返回的钞
 *
 * @param string date (optional)
 * @param string options are 'noleading', 'leading' <- default (optional)
 * @return string
 */
function second($date = NULL, $format = 'leading')
{
    $sec = date('s', timestamp($date));
    if ($format != 'leading')
    {
        return (int) $sec;
    }
    return $sec;
}

/**
 * 确定时间是否是午夜与否。有助于日期被设置没有时间值
 *
 * @param string date
 * @return string
 */
function is_midnight($date)
{
    $ts = timestamp($date);
    return (date('H:i:s', $ts) == '00:00:00');
}

/**
 * 使用YYYY-mm-dd的方式对日期进行运算
 * @param string $params
 * @param date $date
 * @return date
 */
function strtodate($params, $date = false)
{
    if(!$date) $date = datetime_now();

    return date('Y-m-d', strtotime($params, strtotime($date)));
}

/**
 * 使用YYYY-mm-dd的方式对日期进行运算
 * @param string $params
 * @param date $date
 * @return date
 */
function strtodatetime($params, $date = false)
{
    if(!$date) $date = datetime_now();

    return date('Y-m-d H:i:s', strtotime($params, strtotime($date)));
}

/**
 * 使用YYYY-mm-dd的方式对日期进行格式化
 * @param string $params
 * @param date $date
 * @return date
 */
function strdate_format($date = false, $format = 'Y-m-d')
{
    if(!$date) $date = datetime_now();

    return date($format, strtotime($date));
}

/**
 * 对日期，计算天数差
 *
 * @param date $date1
 * @param date $date2
 * @param int $days
 * @return bool
 */
function get_diff_day($date1, $date2)
{
    $one_day_second = 86400;

    $date1 = strtotime($date1);
    $date2 = strtotime($date2);

    $result = ($date1 - $date2) / $one_day_second;
    return $result+1;
}

/**
 * 跟据传入的日期取得格式化后结果
 * @param string $date
 * @param string $format
 * @return string
 */
function year_month($date, $format = 'Y-m')
{
    return date($format, strtotime($date));
}

/**
 * 返回周几信息
 * @return string
 */
function dateweek()
{
    $week = array('日', '一', '二', '三', '四', '五', '六');
    return date('Y年m月d日') . ' 星期' . $week[date('w')];
}


/**
 * 校验日期格式是否正确
 *
 * @param string $date 日期
 * @param string $formats 需要检验的格式数组
 * @return boolean
 */
function date_valid($date, $formats = array("Y-m-d", "Y/m/d")) {
    $unixTime = strtotime($date);
    if (!$unixTime) { //strtotime转换不对，日期格式显然不对。
        return false;
    }

    if(!is_array($formats)) $formats = [$formats];

    //校验日期的有效性，只要满足其中一个格式就OK
    foreach ($formats as $format) {
        if (date($format, $unixTime) == $date) {
            return true;
        }
    }

    return false;
}