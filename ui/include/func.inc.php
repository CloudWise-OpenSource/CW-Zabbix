<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Verify that function exists and can be called as a function.
 *
 * @param array		$names
 *
 * @return bool
 */
function zbx_is_callable(array $names) {
	foreach ($names as $name) {
		if (!is_callable($name)) {
			return false;
		}
	}

	return true;
}

/************ REQUEST ************/
function redirect($url) {
	$curl = (new CUrl($url))->removeArgument('sid');
	header('Location: '.$curl->getUrl());
	exit;
}

/**
 * Check the HTTP request method.
 *
 * @param string $method  HTTP request method
 *
 * @return bool  true, if the request method matches
 */
function isRequestMethod($method) {
	return (strtolower($method) === strtolower($_SERVER['REQUEST_METHOD']));
}

/**
 * Check if request exist.
 *
 * @param string	$name
 *
 * @return bool
 */
function hasRequest($name) {
	return isset($_REQUEST[$name]);
}

/**
 * Check request, if exist request - return request value, else return default value.
 *
 * @param string	$name
 * @param mixed		$def
 *
 * @return mixed
 */
function getRequest($name, $def = null) {
	return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $def;
}

function countRequest($str = null) {
	if (!empty($str)) {
		$count = 0;

		foreach ($_REQUEST as $name => $value) {
			if (strpos($name, $str) !== false) {
				$count++;
			}
		}

		return $count;
	}
	else {
		return count($_REQUEST);
	}
}

/************ COOKIES ************/
function get_cookie($name, $default_value = null) {
	if (isset($_COOKIE[$name])) {
		return $_COOKIE[$name];
	}

	return $default_value;
}

function zbx_setcookie($name, $value, $time = null) {
	setcookie($name, $value, isset($time) ? $time : 0, CSession::getDefaultCookiePath(), null, HTTPS, true);
	$_COOKIE[$name] = $value;
}

function zbx_unsetcookie($name) {
	zbx_setcookie($name, null, -99999);
	unset($_COOKIE[$name]);
}

/************* DATE *************/
function getMonthCaption($num) {
	switch ($num) {
		case 1: return _('January');
		case 2: return _('February');
		case 3: return _('March');
		case 4: return _('April');
		case 5: return _('May');
		case 6: return _('June');
		case 7: return _('July');
		case 8: return _('August');
		case 9: return _('September');
		case 10: return _('October');
		case 11: return _('November');
		case 12: return _('December');
	}

	return _s('[Wrong value for month: "%1$s" ]', $num);
}

function getDayOfWeekCaption($num) {
	switch ($num) {
		case 1: return _('Monday');
		case 2: return _('Tuesday');
		case 3: return _('Wednesday');
		case 4: return _('Thursday');
		case 5: return _('Friday');
		case 6: return _('Saturday');
		case 0:
		case 7: return _('Sunday');
	}

	return _s('[Wrong value for day: "%1$s" ]', $num);
}

// Convert seconds (0..SEC_PER_WEEK) to string representation. For example, 212400 -> 'Tuesday 11:00'
function dowHrMinToStr($value, $display24Hours = false) {
	$dow = $value - $value % SEC_PER_DAY;
	$hr = $value - $dow;
	$hr -= $hr % SEC_PER_HOUR;
	$min = $value - $dow - $hr;
	$min -= $min % SEC_PER_MIN;

	$dow /= SEC_PER_DAY;
	$hr /= SEC_PER_HOUR;
	$min /= SEC_PER_MIN;

	if ($display24Hours && $hr == 0 && $min == 0) {
		$dow--;
		$hr = 24;
	}

	return sprintf('%s %02d:%02d', getDayOfWeekCaption($dow), $hr, $min);
}

// Convert Day Of Week, Hours and Minutes to seconds representation. For example, 2 11:00 -> 212400. false if error occurred
function dowHrMinToSec($dow, $hr, $min) {
	if (zbx_empty($dow) || zbx_empty($hr) || zbx_empty($min) || !zbx_ctype_digit($dow) || !zbx_ctype_digit($hr) || !zbx_ctype_digit($min)) {
		return false;
	}

	if ($dow == 7) {
		$dow = 0;
	}

	if ($dow < 0 || $dow > 6) {
		return false;
	}

	if ($hr < 0 || $hr > 24) {
		return false;
	}

	if ($min < 0 || $min > 59) {
		return false;
	}

	return $dow * SEC_PER_DAY + $hr * SEC_PER_HOUR + $min * SEC_PER_MIN;
}

// Convert timestamp to string representation. Return 'Never' if 0.
function zbx_date2str($format, $value = null) {
	static $weekdaynames, $weekdaynameslong, $months, $monthslong;

	$prefix = '';

	if ($value === null) {
		$value = time();
	}
	elseif ($value > ZBX_MAX_DATE) {
		$prefix = '> ';
		$value = ZBX_MAX_DATE;
	}
	elseif (!$value) {
		return _('Never');
	}

	if (!is_array($weekdaynames)) {
		$weekdaynames = [
			0 => _('Sun'),
			1 => _('Mon'),
			2 => _('Tue'),
			3 => _('Wed'),
			4 => _('Thu'),
			5 => _('Fri'),
			6 => _('Sat')
		];
	}

	if (!is_array($weekdaynameslong)) {
		$weekdaynameslong = [
			0 => _('Sunday'),
			1 => _('Monday'),
			2 => _('Tuesday'),
			3 => _('Wednesday'),
			4 => _('Thursday'),
			5 => _('Friday'),
			6 => _('Saturday')
		];
	}

	if (!is_array($months)) {
		$months = [
			1 => _('Jan'),
			2 => _('Feb'),
			3 => _('Mar'),
			4 => _('Apr'),
			5 => _x('May', 'May short'),
			6 => _('Jun'),
			7 => _('Jul'),
			8 => _('Aug'),
			9 => _('Sep'),
			10 => _('Oct'),
			11 => _('Nov'),
			12 => _('Dec')
		];
	}

	if (!is_array($monthslong)) {
		$monthslong = [
			1 => _('January'),
			2 => _('February'),
			3 => _('March'),
			4 => _('April'),
			5 => _('May'),
			6 => _('June'),
			7 => _('July'),
			8 => _('August'),
			9 => _('September'),
			10 => _('October'),
			11 => _('November'),
			12 => _('December')
		];
	}

	$rplcs = [
		'l' => $weekdaynameslong[date('w', $value)],
		'F' => $monthslong[date('n', $value)],
		'D' => $weekdaynames[date('w', $value)],
		'M' => $months[date('n', $value)]
	];

	$output = $part = '';
	$length = strlen($format);

	for ($i = 0; $i < $length; $i++) {
		$pchar = ($i > 0) ? substr($format, $i - 1, 1) : '';
		$char = substr($format, $i, 1);

		if ($pchar != '\\' && isset($rplcs[$char])) {
			$output .= (strlen($part) ? date($part, $value) : '').$rplcs[$char];
			$part = '';
		}
		else {
			$part .= $char;
		}
	}

	$output .= (strlen($part) > 0) ? date($part, $value) : '';

	return $prefix.$output;
}

/**
 * Calculates and converts timestamp to string representation.
 *
 * @param int|string $start_date  Start date timestamp.
 * @param int|string $end_date    End date timestamp.
 *
 * @return string
 */
function zbx_date2age($start_date, $end_date = 0) {
	$end_date = ($end_date != 0) ? $end_date : time();

	return convertUnitsS($end_date - $start_date);
}

function zbxDateToTime($strdate) {
	if (6 == sscanf($strdate, '%04d%02d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes, $seconds)) {
		return mktime($hours, $minutes, $seconds, $month, $date, $year);
	}
	elseif (5 == sscanf($strdate, '%04d%02d%02d%02d%02d', $year, $month, $date, $hours, $minutes)) {
		return mktime($hours, $minutes, 0, $month, $date, $year);
	}
	else {
		return ($strdate && is_numeric($strdate)) ? $strdate : time();
	}
}

/*************** CONVERTING ******************/
/**
 * Convert the Windows new line (CR+LF) to Linux style line feed (LF).
 *
 * @param string $string  Input string that will be converted.
 *
 * @return string
 */
function CRLFtoLF($string) {
	return str_replace("\r\n", "\n", $string);
}

function rgb2hex($color) {
	$HEX = [
		dechex($color[0]),
		dechex($color[1]),
		dechex($color[2])
	];
	foreach ($HEX as $id => $value) {
		if (strlen($value) != 2) {
			$HEX[$id] = '0'.$value;
		}
	}

	return $HEX[0].$HEX[1].$HEX[2];
}

function hex2rgb($color) {
	if ($color[0] == '#') {
		$color = substr($color, 1);
	}

	if (strlen($color) == 6) {
		list($r, $g, $b) = [$color[0].$color[1], $color[2].$color[3], $color[4].$color[5]];
	}
	elseif (strlen($color) == 3) {
		list($r, $g, $b) = [$color[0].$color[0], $color[1].$color[1], $color[2].$color[2]];
	}
	else {
		return false;
	}

	return [hexdec($r), hexdec($g), hexdec($b)];
}

function getColorVariations($color, $variations_requested = 1) {
	if ($variations_requested <= 1) {
		return [$color];
	}

	$change = hex2rgb('#ffffff'); // Color which is increased/decreased in variations.
	$max = 50;

	$color = hex2rgb($color);
	$variations = [];

	$range = range(-1 * $max, $max, $max * 2 / $variations_requested);

	// Remove redundant values.
	while (count($range) > $variations_requested) {
		(count($range) % 2) ? array_shift($range) : array_pop($range);
	}

	// Calculate colors.
	foreach ($range as $var) {
		$r = $color[0] + ($change[0] / 100 * $var);
		$g = $color[1] + ($change[1] / 100 * $var);
		$b = $color[2] + ($change[2] / 100 * $var);

		$variations[] = '#' . rgb2hex([
			$r < 0 ? 0 : ($r > 255 ? 255 : (int) $r),
			$g < 0 ? 0 : ($g > 255 ? 255 : (int) $g),
			$b < 0 ? 0 : ($b > 255 ? 255 : (int) $b)
		]);
	}

	return $variations;
}

function zbx_num2bitstr($num, $rev = false) {
	if (!is_numeric($num)) {
		return 0;
	}

	$strbin = '';

	$len = 32;
	if ($num > ZBX_MAX_INT32) {
		$len = 64;
	}

	for ($i = 0; $i < $len; $i++) {
		$sbin = 1 << $i;
		$bit = ($sbin & $num) ? '1' : '0';
		if ($rev) {
			$strbin .= $bit;
		}
		else {
			$strbin = $bit.$strbin;
		}
	}

	return $strbin;
}

/**
 * Convert suffixed string to decimal bytes ('10K' => 10240).
 * Note: this function must not depend on optional PHP libraries, since it is used in Zabbix setup.
 *
 * @param string $value
 *
 * @return int
 */
function str2mem($value) {
	$value = trim($value);
	$suffix = strtoupper(substr($value, -1));

	if (ctype_digit($suffix)) {
		return (int) $value;
	}

	$value = (int) substr($value, 0, -1);

	if ($suffix === 'G') {
		$value *= ZBX_GIBIBYTE;
	}
	elseif ($suffix === 'M') {
		$value *= ZBX_MEBIBYTE;
	}
	elseif ($suffix === 'K') {
		$value *= ZBX_KIBIBYTE;
	}

	return $value;
}

/**
 * Convert decimal bytes to suffixed string (10240 => '10K').
 * Note: this function must not depend on optional PHP libraries, since it is used in Zabbix setup.
 *
 * @param int $bytes
 *
 * @return string
 */
function mem2str($bytes) {
	if ($bytes > ZBX_GIBIBYTE) {
		return round($bytes / ZBX_GIBIBYTE, ZBX_UNITS_ROUNDOFF_SUFFIXED).'G';
	}
	elseif ($bytes > ZBX_MEBIBYTE) {
		return round($bytes / ZBX_MEBIBYTE, ZBX_UNITS_ROUNDOFF_SUFFIXED).'M';
	}
	elseif ($bytes > ZBX_KIBIBYTE) {
		return round($bytes / ZBX_KIBIBYTE, ZBX_UNITS_ROUNDOFF_SUFFIXED).'K';
	}
	else {
		return round($bytes).'B';
	}
}

function convertUnitsUptime($value) {
	$value = round($value);
	$value_abs = abs($value);

	$result = $value < 0 ? '-' : '';

	$days = floor($value_abs / SEC_PER_DAY);

	if ($days != 0) {
		$result .= _n('%1$d day', '%1$d days', formatFloat($days));
	}

	// Is original value precise enough for showing detailed data?
	if (strlen($value_abs) <= ZBX_FLOAT_DIG) {
		if ($days != 0) {
			$result .= ', ';
		}

		$value_abs = $value_abs - $days * SEC_PER_DAY;

		$hours = floor($value_abs / SEC_PER_HOUR);
		$value_abs -= $hours * SEC_PER_HOUR;

		$minutes = floor($value_abs / SEC_PER_MIN);
		$seconds = $value_abs - $minutes * SEC_PER_MIN;

		$result .= sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
	}

	return $result;
}

/**
 * Convert time period to a human-readable format.
 * The following units will be used: years, months, days, hours, minutes, seconds and milliseconds.
 * Only the 3 most significant units will be displayed: #y #m #d, #m #d #h, #d #h #mm and so on, omitting empty ones.
 *
 * @param int  $value            Time period in seconds.
 * @param bool $ignore_millisec  Without ms (1s 200 ms = 1.2s).
 *
 * @return string
 */
function convertUnitsS($value, $ignore_millisec = false) {
	$value = (float) $value;
	$value_abs = abs($value);

	$parts = [];
	$start = null;

	$value_abs_int = floor($value_abs);

	if (($v = floor($value_abs_int / SEC_PER_YEAR)) > 0) {
		$parts['years'] = $v;
		$value_abs_int -= $v * SEC_PER_YEAR;
		$start = 0;
	}

	$v = floor($value_abs_int / SEC_PER_MONTH);
	if ($v == 12) {
		$parts['years'] = $start === null ? 1 : $parts['years'] + 1;
		$start = 0;
	}
	elseif ($start === null || ceil(log10($parts['years'])) <= ZBX_FLOAT_DIG) {
		if ($v > 0) {
			$parts['months'] = $v;
			$value_abs_int -= $v * SEC_PER_MONTH;
			$start = $start === null ? 1 : $start;
		}

		$level = 2;
		foreach ([
			'days' => SEC_PER_DAY,
			'hours' => SEC_PER_HOUR,
			'minutes' => SEC_PER_MIN,
		] as $part => $sec_per_part) {
			$v = floor($value_abs_int / $sec_per_part);
			if ($v > 0) {
				$parts[$part] = $v;
				$value_abs_int -= $v * $sec_per_part;
				$start = $start === null ? $level : $start;
			}

			if ($start !== null && $level - $start >= 2) {
				break;
			}

			$level++;
		}

		if ($start === null || $start >= 3) {
			if ($ignore_millisec) {
				$v = $value_abs_int + round(fmod($value_abs, 1), ZBX_UNITS_ROUNDOFF_SUFFIXED);

				if ($v > 0) {
					$parts['seconds'] = $v;
				}
			}
			else {
				$parts['seconds'] = $value_abs_int;

				if ($start === null || $start >= 4) {
					$v = fmod($value_abs, 1) * 1000;

					if ($v > 0) {
						$parts['milliseconds'] = formatFloat($v, null, ZBX_UNITS_ROUNDOFF_SUFFIXED);
					}
				}
			}
		}
	}

	$units = [
		'years' => _x('y', 'year short'),
		'months' => _x('m', 'month short'),
		'days' => _x('d', 'day short'),
		'hours' => _x('h', 'hour short'),
		'minutes' => _x('m', 'minute short'),
		'seconds' => _x('s', 'second short'),
		'milliseconds' => _x('ms', 'millisecond short')
	];

	$result = [];

	foreach (array_filter($parts) as $unit => $value) {
		$result[] = formatFloat($value, null, ZBX_UNITS_ROUNDOFF_SUFFIXED).$units[$unit];
	}

	return $result ? ($value < 0 ? '-' : '').implode(' ', $result) : '0';
}

/**
 * Converts value to actual value.
 * Example:
 * 	6442450944 B convert to 6 GB
 *
 * @param array  $options
 * @param string $options['value']
 * @param string $options['units']
 * @param int    $options['convert']
 * @param int    $options['power']
 * @param string $options['unit_base']
 * @param bool   $options['ignore_milliseconds']
 * @param int    $options['precision']
 * @param int    $options['decimals']
 * @param bool   $options['decimals_exact']
 *
 * @return string
 */
function convertUnits(array $options) {
	static $power_table = ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];

	$options += [
		'value' => '',
		'units' => '',
		'convert' => ITEM_CONVERT_WITH_UNITS,
		'power' => null,
		'unit_base' => null,
		'ignore_milliseconds' => false,
		'precision' => null,
		'decimals' => null,
		'decimals_exact' => false
	];

	$value = $options['value'] !== null ? $options['value'] : '';

	if (!is_numeric($value)) {
		return $value;
	}

	$units = $options['units'] !== null ? $options['units'] : '';

	if ($units === 'unixtime') {
		return zbx_date2str(DATE_TIME_FORMAT_SECONDS, $value);
	}

	if ($units === 'uptime') {
		return convertUnitsUptime($value);
	}

	if ($units === 's') {
		return convertUnitsS($value, $options['ignore_milliseconds']);
	}

	$blacklist = ['%', 'ms', 'rpm', 'RPM'];

	if ($units !== '' && $units[0] === '!') {
		$units = substr($units, 1);
		$blacklist[] = $units;
	}

	$value = (float) $value;
	$value_abs = abs($value);

	$do_convert = $units !== '' || $options['convert'] == ITEM_CONVERT_NO_UNITS;

	if (in_array($units, $blacklist) || !$do_convert || $value_abs < 1) {
		$result = formatFloat($value, $options['precision'], $options['decimals'] ?? ZBX_UNITS_ROUNDOFF_UNSUFFIXED,
			$options['decimals_exact']
		);

		$result .= ($units === '' ? '' : ' '.$units);

		return $result;
	}

	$unit_base = $options['unit_base'];
	if ($unit_base != 1000 && $unit_base != ZBX_KIBIBYTE) {
		$unit_base = ($units === 'B' || $units === 'Bps') ? ZBX_KIBIBYTE : 1000;
	}

	if ($options['power'] === null) {
		$result = null;
		$unit_prefix = null;

		foreach ($power_table as $power => $prefix) {
			$result = formatFloat($value / pow($unit_base, $power), $options['precision'],
				$options['decimals'] ?? ($prefix === '' ? ZBX_UNITS_ROUNDOFF_UNSUFFIXED : ZBX_UNITS_ROUNDOFF_SUFFIXED),
				$options['decimals_exact']
			);
			$unit_prefix = $prefix;

			if (abs($result) < $unit_base) {
				break;
			}
		}
	}
	else {
		if (array_key_exists($options['power'], $power_table) && $value_abs != 0) {
			$unit_power = $options['power'];
			$unit_prefix = $power_table[$unit_power];
		}
		else {
			$unit_power = count($power_table);
			$unit_prefix = $power_table[$unit_power];
		}

		$result = formatFloat($value / pow($unit_base, $unit_power), $options['precision'], $options['decimals'] ??
			($unit_prefix === '' ? ZBX_UNITS_ROUNDOFF_UNSUFFIXED : ZBX_UNITS_ROUNDOFF_SUFFIXED), $options['decimals_exact']
		);
	}

	$result_units = ($result == 0 ? '' : $unit_prefix).$units;

	$result .= ($result_units === '' ? '' : ' '.$result_units);

	return $result;
}

/**
 * Validate and convert time to seconds.
 * Examples: '100' => '100'; '10m' => '600'; '-10m' => '-600'; '3d' => '259200'.
 *
 * @param string $time       Decimal integer with optional time suffix.
 * @param bool   $with_year  Additionally parse year suffixes.
 *
 * @return string|null  Decimal integer seconds or null on error.
 */
function timeUnitToSeconds($time, $with_year = false) {
	$suffixes = $with_year ? ZBX_TIME_SUFFIXES_WITH_YEAR : ZBX_TIME_SUFFIXES;

	if (!preg_match('/^'.ZBX_PREG_INT.'(?<suffix>['.$suffixes.'])?$/', $time, $matches)) {
		return null;
	}

	$suffix = array_key_exists('suffix', $matches) ? $matches['suffix'] : 's';

	return $matches['int'] * ZBX_TIME_SUFFIX_MULTIPLIERS[$suffix];
}

/************* ZBX MISC *************/

/**
 * Check if every character in given string value is a decimal digit.
 *
 * @param string | int   $x Value to check.
 *
 * @return boolean
 */
function zbx_ctype_digit($x) {
	return ctype_digit(strval($x));
}

/**
 * Returns true if the value is an empty string, empty array or null.
 *
 * @deprecated use strict comparison instead
 *
 * @param $value
 *
 * @return bool
 */
function zbx_empty($value) {
	if ($value === null) {
		return true;
	}
	if (is_array($value) && empty($value)) {
		return true;
	}
	if (is_string($value) && $value === '') {
		return true;
	}

	return false;
}

function zbx_is_int($var) {
	if (is_int($var)) {
		return true;
	}

	if (is_string($var)) {
		if (function_exists('ctype_digit') && ctype_digit($var) || strcmp(intval($var), $var) == 0) {
			return true;
		}
	}
	else {
		if ($var > 0 && zbx_ctype_digit($var)) {
			return true;
		}
	}

	return preg_match("/^\-?\d{1,20}+$/", $var);
}

/**
 * Look for two arrays field value and create 3 array lists, one with arrays where field value exists only in first array
 * second with arrays where field values are only in second array and both where field values are in both arrays.
 *
 * @param array  $primary
 * @param array  $secondary
 * @param string $field field that is searched in arrays
 *
 * @return array
 */
function zbx_array_diff(array $primary, array $secondary, $field) {
	$fields1 = zbx_objectValues($primary, $field);
	$fields2 = zbx_objectValues($secondary, $field);

	$first = array_diff($fields1, $fields2);
	$first = zbx_toHash($first);

	$second = array_diff($fields2, $fields1);
	$second = zbx_toHash($second);

	$result = [
		'first' => [],
		'second' => [],
		'both' => []
	];

	foreach ($primary as $array) {
		if (!isset($array[$field])) {
			$result['first'][] = $array;
		}
		elseif (isset($first[$array[$field]])) {
			$result['first'][] = $array;
		}
		else {
			$result['both'][$array[$field]] = $array;
		}
	}

	foreach ($secondary as $array) {
		if (!isset($array[$field])) {
			$result['second'][] = $array;
		}
		elseif (isset($second[$array[$field]])) {
			$result['second'][] = $array;
		}
	}

	return $result;
}

function zbx_array_push(&$array, $add) {
	foreach ($array as $key => $value) {
		foreach ($add as $newKey => $newValue) {
			$array[$key][$newKey] = $newValue;
		}
	}
}

/**
 * Find if array has any duplicate values and return an array with info about them.
 * In case of no duplicates, empty array is returned.
 * Example of usage:
 *     $result = zbx_arrayFindDuplicates(
 *         array('a', 'b', 'c', 'c', 'd', 'd', 'd', 'e')
 *     );
 *     array(
 *         'd' => 3,
 *         'c' => 2,
 *     )
 *
 * @param array $array
 *
 * @return array
 */
function zbx_arrayFindDuplicates(array $array) {
	$countValues = array_count_values($array); // counting occurrences of every value in array
	foreach ($countValues as $value => $count) {
		if ($count <= 1) {
			unset($countValues[$value]);
		}
	}
	arsort($countValues); // sorting, so that the most duplicates would be at the top

	return $countValues;
}

/************* STRING *************/
function zbx_nl2br($str) {
	$str_res = [];
	foreach (explode("\n", $str) as $str_line) {
		array_push($str_res, $str_line, BR());
	}
	array_pop($str_res);

	return $str_res;
}

function zbx_formatDomId($value) {
	return str_replace(['[', ']'], ['_', ''], $value);
}

/************* SORT *************/
function natksort(&$array) {
	$keys = array_keys($array);
	natcasesort($keys);

	$new_array = [];

	foreach ($keys as $k) {
		$new_array[$k] = $array[$k];
	}

	$array = $new_array;

	return true;
}

// recursively sort an array by key
function zbx_rksort(&$array, $flags = null) {
	if (is_array($array)) {
		foreach ($array as $id => $data) {
			zbx_rksort($array[$id]);
		}
		ksort($array, $flags);
	}

	return $array;
}

/**
 * Sorts the data using a natural sort algorithm.
 *
 * Not suitable for sorting macros, use order_macros() instead.
 *
 * @param $data
 * @param null $sortfield
 * @param string $sortorder
 *
 * @return bool
 *
 * @see order_macros()
 */
function order_result(&$data, $sortfield = null, $sortorder = ZBX_SORT_UP) {
	if (empty($data)) {
		return false;
	}

	if (is_null($sortfield)) {
		natcasesort($data);
		if ($sortorder != ZBX_SORT_UP) {
			$data = array_reverse($data, true);
		}
		return true;
	}

	$sort = [];
	foreach ($data as $key => $arr) {
		if (!isset($arr[$sortfield])) {
			return false;
		}
		$sort[$key] = $arr[$sortfield];
	}
	natcasesort($sort);

	if ($sortorder != ZBX_SORT_UP) {
		$sort = array_reverse($sort, true);
	}

	$tmp = $data;
	$data = [];
	foreach ($sort as $key => $val) {
		$data[$key] = $tmp[$key];
	}

	return true;
}

/**
 * Sorts the macros in the given order. Supports user and LLD macros.
 *
 * order_result() is not suitable for sorting macros, because it treats the "}" as a symbol with a lower priority
 * then any alphanumeric character, and the result will be invalid.
 *
 * E.g: order_result() will sort array('{$DD}', '{$D}', '{$D1}') as
 * array('{$D1}', '{$DD}', '{$D}') while the correct result is array('{$D}', '{$D1}', '{$DD}').
 *
 * @param array $macros
 * @param string $sortfield
 * @param string $order
 *
 * @return array
 */
function order_macros(array $macros, $sortfield, $order = ZBX_SORT_UP) {
	$temp = [];
	foreach ($macros as $key => $macro) {
		$temp[$key] = substr($macro[$sortfield], 2, strlen($macro[$sortfield]) - 3);
	}
	order_result($temp, null, $order);

	$rs = [];
	foreach ($temp as $key => $macroLabel) {
		$rs[$key] = $macros[$key];
	}

	return $rs;
}

// preserve keys
function zbx_array_merge() {
	$args = func_get_args();
	$result = [];
	foreach ($args as &$array) {
		if (!is_array($array)) {
			return false;
		}
		foreach ($array as $key => $value) {
			$result[$key] = $value;
		}
	}
	unset($array);

	return $result;
}

function uint_in_array($needle, $haystack) {
	foreach ($haystack as $value) {
		if (bccomp($needle, $value) == 0) {
			return true;
		}
	}

	return false;
}

function str_in_array($needle, $haystack, $strict = false) {
	if (is_array($needle)) {
		return in_array($needle, $haystack, $strict);
	}
	elseif ($strict) {
		foreach ($haystack as $value) {
			if ($needle === $value) {
				return true;
			}
		}
	}
	else {
		foreach ($haystack as $value) {
			if (strcmp($needle, $value) == 0) {
				return true;
			}
		}
	}

	return false;
}

function zbx_value2array(&$values) {
	if (!is_array($values) && !is_null($values)) {
		$tmp = [];
		if (is_object($values)) {
			$tmp[] = $values;
		}
		else {
			$tmp[$values] = $values;
		}
		$values = $tmp;
	}
}

// creates chain of relation parent -> child, for all chain levels
function createParentToChildRelation(&$chain, $link, $parentField, $childField) {
	if (!isset($chain[$link[$parentField]])) {
		$chain[$link[$parentField]] = [];
	}

	$chain[$link[$parentField]][$link[$childField]] = $link[$childField];
	if (isset($chain[$link[$childField]])) {
		$chain[$link[$parentField]] = zbx_array_merge($chain[$link[$parentField]], $chain[$link[$childField]]);
	}
}

// object or array of objects to hash
function zbx_toHash($value, $field = null) {
	if (is_null($value)) {
		return $value;
	}
	$result = [];

	if (!is_array($value)) {
		$result = [$value => $value];
	}
	elseif (isset($value[$field])) {
		$result[$value[$field]] = $value;
	}
	else {
		foreach ($value as $val) {
			if (!is_array($val)) {
				$result[$val] = $val;
			}
			elseif (isset($val[$field])) {
				$result[$val[$field]] = $val;
			}
		}
	}

	return $result;
}

/**
 * Transforms a single or an array of values to an array of objects, where the values are stored under the $field
 * key.
 *
 * E.g:
 * zbx_toObject(array(1, 2), 'hostid')            // returns array(array('hostid' => 1), array('hostid' => 2))
 * zbx_toObject(3, 'hostid')                      // returns array(array('hostid' => 3))
 * zbx_toObject(array('a' => 1), 'hostid', true)  // returns array('a' => array('hostid' => 1))
 *
 * @param $value
 * @param $field
 * @param $preserve_keys
 *
 * @return array
 */
function zbx_toObject($value, $field, $preserve_keys = false) {
	if (is_null($value)) {
		return $value;
	}
	$result = [];

	// Value or Array to Object or Array of objects
	if (!is_array($value)) {
		$result = [[$field => $value]];
	}
	elseif (!isset($value[$field])) {
		foreach ($value as $key => $val) {
			if (!is_array($val)) {
				$result[$key] = [$field => $val];
			}
		}

		if (!$preserve_keys) {
			$result = array_values($result);
		}
	}

	return $result;
}

/**
 * Converts the given value to a numeric array:
 * - a scalar value will be converted to an array and added as the only element;
 * - an array with first element key containing only numeric characters will be converted to plain zero-based numeric array.
 * This is used for resetting nonsequential numeric arrays;
 * - an associative array will be returned in an array as the only element, except if first element key contains only numeric characters.
 *
 * @param mixed $value
 *
 * @return array
 */
function zbx_toArray($value) {
	if ($value === null) {
		return $value;
	}

	if (is_array($value)) {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);

		if (zbx_ctype_digit(key($value))) {
			$result = array_values($value);
		}
		elseif (!empty($value)) {
			$result = [$value];
		}
		else {
			$result = [];
		}
	}
	else {
		$result = [$value];
	}

	return $result;
}

/**
 * Converts value OR object OR array of objects TO an array.
 *
 * @deprecated  Use array_column() instead.
 *
 * @param $value
 * @param $field
 *
 * @return array
 */
function zbx_objectValues($value, $field) {
	if (is_null($value)) {
		return $value;
	}

	if (!is_array($value)) {
		$result = [$value];
	}
	elseif (isset($value[$field])) {
		$result = [$value[$field]];
	}
	else {
		$result = [];

		foreach ($value as $val) {
			if (!is_array($val)) {
				$result[] = $val;
			}
			elseif (isset($val[$field])) {
				$result[] = $val[$field];
			}
		}
	}

	return $result;
}

function zbx_cleanHashes(&$value) {
	if (is_array($value)) {
		// reset() is needed to move internal array pointer to the beginning of the array
		reset($value);
		if (zbx_ctype_digit(key($value))) {
			$value = array_values($value);
		}
	}

	return $value;
}

function zbx_toCSV($values) {
	$csv = '';
	$glue = '","';
	foreach ($values as $row) {
		if (!is_array($row)) {
			$row = [$row];
		}
		foreach ($row as $num => $value) {
			$row[$num] = str_replace('"', '""', $value);
		}
		$csv .= '"'.implode($glue, $row).'"'."\n";
	}

	return $csv;
}

function zbx_str2links($text) {
	$result = [];

	foreach (explode("\n", $text) as $line) {
		$line = rtrim($line, "\r ");

		preg_match_all('#https?://[^\n\t\r ]+#u', $line, $matches);

		$start = 0;

		foreach ($matches[0] as $match) {
			if (($pos = mb_strpos($line, $match, $start)) !== false) {
				if ($pos != $start) {
					$result[] = mb_substr($line, $start, $pos - $start);
				}
				$result[] = new CLink(CHTML::encode($match), $match);
				$start = $pos + mb_strlen($match);
			}
		}

		if (mb_strlen($line) != $start) {
			$result[] = mb_substr($line, $start);
		}

		$result[] = BR();
	}

	array_pop($result);

	return $result;
}

function zbx_subarray_push(&$mainArray, $sIndex, $element = null, $key = null) {
	if (!isset($mainArray[$sIndex])) {
		$mainArray[$sIndex] = [];
	}
	if ($key) {
		$mainArray[$sIndex][$key] = is_null($element) ? $sIndex : $element;
	}
	else {
		$mainArray[$sIndex][] = is_null($element) ? $sIndex : $element;
	}
}

/*************** PAGE SORTING ******************/

/**
 * Returns header with sorting options.
 *
 * @param string obj			Header item.
 * @param string $tabfield		Table field.
 * @param string $sortField		Sorting field.
 * @param string $sortOrder		Sorting order.
 * @param string $link			Sorting link.
 *
 * @return CColHeader
 */
function make_sorting_header($obj, $tabfield, $sortField, $sortOrder, $link = null) {
	$sortorder = ($sortField == $tabfield && $sortOrder == ZBX_SORT_UP) ? ZBX_SORT_DOWN : ZBX_SORT_UP;

	$link = CUrlFactory::getContextUrl($link);

	$link->setArgument('sort', $tabfield);
	$link->setArgument('sortorder', $sortorder);

	zbx_value2array($obj);

	$arrow = null;
	if ($tabfield == $sortField) {
		if ($sortorder == ZBX_SORT_UP) {
			$arrow = (new CSpan())->addClass(ZBX_STYLE_ARROW_DOWN);
		}
		else {
			$arrow = (new CSpan())->addClass(ZBX_STYLE_ARROW_UP);
		}
	}

	return new CColHeader(new CLink([$obj, $arrow], $link->getUrl()));
}

/**
 * Format floating-point number in best possible way for displaying.
 *
 * @param string   $number     Valid number in decimal or scientific notation.
 * @param int|null $precision  Max number of significant digits to take into account. Default: ZBX_FLOAT_DIG.
 * @param int|null $decimals   Max number of first non-zero decimals decimals to display. Default: 0.
 * @param bool     $exact      Display exaclty this number of decimals instead of first non-zeros.
 *
 * Note: $decimals must be less than $precision.
 *
 * @return string
 */
function formatFloat(float $number, int $precision = null, int $decimals = null, bool $exact = false): string {
	if ($number == 0) {
		return '0';
	}

	if ($number == INF) {
		return _('Infinity');
	}

	if ($number == -INF) {
		return '-'._('Infinity');
	}

	if ($precision === null) {
		$precision = ZBX_FLOAT_DIG;
	}

	if ($decimals === null) {
		$decimals = 0;
	}

	$number_original = $number;

	$exponent = (int) explode('E', sprintf('%.'.($precision - 1).'E', $number))[1];

	if ($exponent < 0) {
		for ($i = 1; $i >= 0; $i--) {
			$round_precision = $decimals - $exponent - $i;

			// PHP rounding bug when precision is set more than 294.
			if ($round_precision > 294) {
				$decimal_shift = pow(10, $round_precision - 294);
				$test = round($number * $decimal_shift, 294) / $decimal_shift;
			}
			else {
				$test = round($number, $round_precision);
			}

			$test_number = sprintf('%.'.($precision - 1).'E', $test);
			$test_digits = ($precision == 1)
				? 1
				: strlen(rtrim(explode('E', $test_number)[0], '0')) - ($test_number[0] === '-' ? 2 : 1);

			if ($test_digits - $exponent <= $precision) {
				break;
			}
		}
		$number = $test_number;
		$digits = $test_digits;
	}
	else {
		if ($exponent >= $precision) {
			if ($exponent >= min(PHP_FLOAT_DIG, $precision + 3)
					|| round($number, $precision - $exponent - 1) != $number) {
				$number = round($number, $decimals - $exponent);
			}
		}
		else {
			$number = round($number, min($decimals, $precision - $exponent - 1));
		}

		$number = sprintf('%.'.($precision - 1).'E', $number);
		$digits = ($precision == 1) ? 1 : strlen(rtrim(explode('E', $number)[0], '0')) - ($number[0] === '-' ? 2 : 1);
	}

	if ($number == 0) {
		return '0';
	}

	$exponent = (int) explode('E', sprintf('%.'.($precision - 1).'E', $number))[1];

	if ($exponent < 0) {
		if ($digits - $exponent <= ($exact ? min($decimals + 1, $precision) : $precision)) {
			return sprintf('%.'.($exact ? $decimals : $digits - $exponent - 1).'f', $number);
		}
		else {
			return sprintf('%.'.($exact ? $decimals : min($digits - 1, $decimals)).'E', $number);
		}
	}
	elseif ($exponent >= min(PHP_FLOAT_DIG, $precision + 3)
			|| ($exponent >= $precision && $number != $number_original)) {
		return sprintf('%.'.($exact ? $decimals : min($digits - 1, $decimals)).'E', $number);
	}
	else {
		return sprintf('%.'.($exact ? $decimals : max(0, min($digits - $exponent - 1, $decimals))).'f', $number);
	}
}

/**
* Truncate float to the amount of significant digits, to allow safe float comparison.
*
* @param float $number
*
* @return float
*/
function truncateFloat(float $number): float {
	return (float) sprintf('%.'.(ZBX_FLOAT_DIG - 1).'E', $number);
}

/**
 * Get number of digits after the decimal dot.
 *
 * @param float $number  Valid number in decimal or scientific notation.
 *
 * @return int
 */
function getNumDecimals(float $number): int {
	[$mantissa, $exponent] = explode('E', sprintf('%.'.(ZBX_FLOAT_DIG - 1).'E', $number));

	$significant_size = strlen(rtrim($mantissa, '0')) - ($number < 0 ? 2 : 1);

	return max(0, $significant_size - 1 - $exponent);
}

/**
 * Converts number to letter representation.
 * From A to Z, then from AA to ZZ etc.
 * Example: 0 => A, 25 => Z, 26 => AA, 27 => AB, 52 => BA, ...
 *
 * Keep in sync with JS num2letter().
 *
 * @param int $number
 *
 * @return string
 */
function num2letter($number) {
	$start = ord('A');
	$base = 26;
	$str = '';
	$level = 0;

	do {
		if ($level++ > 0) {
			$number--;
		}
		$remainder = $number % $base;
		$number = ($number - $remainder) / $base;
		$str = chr($start + $remainder).$str;
	} while (0 != $number);

	return $str;
}

/**
 * Renders an "access denied" message and stops the execution of the script.
 *
 * The $mode parameters controls the layout of the message for logged in users:
 * - ACCESS_DENY_OBJECT     - render the message when denying access to a specific object
 * - ACCESS_DENY_PAGE       - render a complete access denied page
 *
 * If visitor is without any access permission then layout of the message is same as in ACCESS_DENY_PAGE mode.
 *
 * @param int $mode
 */
function access_deny($mode = ACCESS_DENY_OBJECT) {
	// deny access to an object
	if ($mode == ACCESS_DENY_OBJECT && CWebUser::isLoggedIn()) {
		show_error_message(_('No permissions to referred object or it does not exist!'));

		require_once dirname(__FILE__).'/page_header.php';
		(new CWidget())->show();
		require_once dirname(__FILE__).'/page_footer.php';
	}
	// deny access to a page
	else {
		// url to redirect the user to after he logs in
		$url = (new CUrl(!empty($_REQUEST['request']) ? $_REQUEST['request'] : ''))->removeArgument('sid');
		$config = select_config();

		if ($config['http_login_form'] == ZBX_AUTH_FORM_HTTP && $config['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED
				&& (!CWebUser::isLoggedIn() || CWebUser::isGuest())) {
			$redirect_to = (new CUrl('index_http.php'))->setArgument('request', $url->toString());
			redirect($redirect_to->toString());

			exit;
		}

		$url = urlencode($url->toString());

		// if the user is logged in - render the access denied message
		if (CWebUser::isLoggedIn()) {
			$data = [
				'header' => _('Access denied'),
				'messages' => [
					_s('You are logged in as "%1$s".', CWebUser::$data['alias']).' '._('You have no permissions to access this page.'),
					_('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
				],
				'buttons' => []
			];

			// display the login button only for guest users
			if (CWebUser::isGuest()) {
				$data['buttons'][] = (new CButton('login', _('Login')))
					->onClick('javascript: document.location = "index.php?request='.$url.'";');
			}
			$data['buttons'][] = (new CButton('back', _('Go to dashboard')))
				->onClick('javascript: document.location = "zabbix.php?action=dashboard.view"');
		}
		// if the user is not logged in - offer to login
		else {
			$data = [
				'header' => _('You are not logged in'),
				'messages' => [
					_('You must login to view this page.'),
					_('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
				],
				'buttons' => [
					(new CButton('login', _('Login')))->onClick('javascript: document.location = "index.php?request='.$url.'";')
				]
			];
		}

		$data['theme'] = getUserTheme(CWebUser::$data);

		if (detect_page_type() == PAGE_TYPE_JS) {
			echo (new CView('layout.json', ['main_block' => json_encode(['error' => $data['header']])]))->getOutput();
		}
		else {
			echo (new CView('general.warning', $data))->getOutput();
		}
		exit;
	}
}

function detect_page_type($default = PAGE_TYPE_HTML) {
	if (isset($_REQUEST['output'])) {
		switch (strtolower($_REQUEST['output'])) {
			case 'text':
				return PAGE_TYPE_TEXT;
			case 'ajax':
				return PAGE_TYPE_JS;
			case 'json':
				return PAGE_TYPE_JSON;
			case 'json-rpc':
				return PAGE_TYPE_JSON_RPC;
			case 'html':
				return PAGE_TYPE_HTML_BLOCK;
			case 'img':
				return PAGE_TYPE_IMAGE;
			case 'css':
				return PAGE_TYPE_CSS;
		}
	}

	return $default;
}

function makeMessageBox($good, array $messages, $title = null, $show_close_box = true, $show_details = false)
{
	$class = $good ? ZBX_STYLE_MSG_GOOD : ZBX_STYLE_MSG_BAD;
	$msg_details = null;
	$link_details = null;

	if ($messages) {
		if ($title !== null) {
			$link_details = (new CLinkAction())
				->addItem(_('Details'))
				->addItem(' ') // space
				->addItem((new CSpan())
					->setId('details-arrow')
					->addClass($show_details ? ZBX_STYLE_ARROW_UP : ZBX_STYLE_ARROW_DOWN)
				)
				->setAttribute('aria-expanded', $show_details ? 'true' : 'false')
				->onClick('javascript: '.
					'showHide(jQuery(this).siblings(\'.'.ZBX_STYLE_MSG_DETAILS.'\')'.
						'.find(\'.'.ZBX_STYLE_MSG_DETAILS_BORDER.'\'));'.
					'jQuery("#details-arrow", $(this)).toggleClass("'.ZBX_STYLE_ARROW_UP.' '.ZBX_STYLE_ARROW_DOWN.'");'.
					'jQuery(this).attr(\'aria-expanded\', jQuery(this).find(\'.'.ZBX_STYLE_ARROW_DOWN.'\').length == 0)'
				);
		}

		$list = new CList();
		if ($title !== null) {
			$list->addClass(ZBX_STYLE_MSG_DETAILS_BORDER);

			if (!$show_details) {
				$list->setAttribute('style', 'display: none;');
			}
		}
		foreach ($messages as $message) {
			foreach (explode("\n", $message['message']) as $message_part) {
				$list->addItem($message_part);
			}
		}
		$msg_details = (new CDiv())->addClass(ZBX_STYLE_MSG_DETAILS)->addItem($list);
	}

	if ($title !== null) {
		$title = new CSpan($title);
	}

	// Details link should be in front of title.
	$msg_box = (new CTag('output', true, [$link_details, $title, $msg_details]))
		->addClass($class)
		->setAttribute('role', 'contentinfo')
		->setAttribute('aria-label', $good ? _('Success message') : _('Error message'));

	if ($show_close_box) {
		$msg_box->addItem((new CSimpleButton())
			->addClass(ZBX_STYLE_OVERLAY_CLOSE_BTN)
			->onClick('jQuery(this).closest(\'.'.$class.'\').remove();')
			->setTitle(_('Close')));
	}

	return $msg_box;
}

/**
 * Filters messages that can be displayed to user based on defines (see ZBX_SHOW_TECHNICAL_ERRORS) and user settings.
 *
 * @param array $messages	List of messages to filter.
 *
 * @return array
 */
function filter_messages(array $messages = []) {
	if (!ZBX_SHOW_TECHNICAL_ERRORS && CWebUser::getType() != USER_TYPE_SUPER_ADMIN && !CWebUser::getDebugMode()) {
		$filtered_messages = [];
		$generic_exists = false;

		foreach ($messages as $message) {
			if (array_key_exists('src', $message) && ($message['src'] === 'sql' || $message['src'] === 'php')) {
				if (!$generic_exists) {
					$message['message'] = _('System error occurred. Please contact Zabbix administrator.');
					$filtered_messages[] = $message;
					$generic_exists = true;
				}
			}
			else {
				$filtered_messages[] = $message;
			}
		}
		$messages = $filtered_messages;
	}

	return $messages;
}

/**
 * Returns the message box when messages are present; null otherwise
 *
 * @param  bool    $good            Parameter passed to makeMessageBox to specify message box style.
 * @param  string  $title           Message box title.
 * @param  bool    $show_close_box  Show or hide close button in error message box.
 * @global array   $ZBX_MESSAGES
 *
 * @return CDiv|null
 */
function getMessages($good = false, $title = null, $show_close_box = true) {
	global $ZBX_MESSAGES;

	$messages = (isset($ZBX_MESSAGES) && $ZBX_MESSAGES) ? filter_messages($ZBX_MESSAGES) : [];

	$message_box = ($title || $messages)
		? makeMessageBox($good, $messages, $title, $show_close_box)
		: null;

	$ZBX_MESSAGES = [];

	return $message_box;
}

function show_messages($good = false, $okmsg = null, $errmsg = null) {
	global $page, $ZBX_MESSAGES, $ZBX_MESSAGES_PREPARED;

	if (defined('ZBX_API_REQUEST')) {
		return null;
	}

	$title = $good ? $okmsg : $errmsg;
	$messages = isset($ZBX_MESSAGES) ? filter_messages($ZBX_MESSAGES) : [];

	$ZBX_MESSAGES = [];

	if ($title === null && !$messages) {
		return;
	}

	$page_type = (is_array($page) && array_key_exists('type', $page)) ? $page['type'] : PAGE_TYPE_HTML;

	switch ($page_type) {
		case PAGE_TYPE_IMAGE:
			$image_messages = [];

			if ($title !== null) {
				$image_messages[] = [
					'text' => $title,
					'color' => (!$good) ? ['R' => 255, 'G' => 0, 'B' => 0] : ['R' => 34, 'G' => 51, 'B' => 68]
				];
			}

			foreach ($messages as $message) {
				$image_messages[] = [
					'text' => $message['message'],
					'color' => $message['type'] == 'error'
						? ['R' => 255, 'G' => 55, 'B' => 55]
						: ['R' => 155, 'G' => 155, 'B' => 55]
				];
			}

			// Draw an image with the messages.
			$image_font_size = 8;

			// Calculate the size of the text.
			$width = 0;
			$height = 0;

			foreach ($image_messages as &$message) {
				$size = imageTextSize($image_font_size, 0, $message['text']);
				$message['height'] = $size['height'] - $size['baseline'];

				// Calculate the total size of the image.
				$width = max($width, $size['width']);
				$height += $size['height'] + 1;
			}
			unset($message);

			// Add padding.
			$width += 2;
			$height += 2;

			// Create the image.
			$canvas = imagecreate($width, $height);
			imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));

			// Draw messages.
			$y = 1;
			foreach ($image_messages as $message) {
				$y += $message['height'];
				imageText($canvas, $image_font_size, 0, 1, $y,
					imagecolorallocate($canvas, $message['color']['R'], $message['color']['G'], $message['color']['B']),
					$message['text']
				);
			}

			imageOut($canvas);
			imagedestroy($canvas);

			break;

		default:
			if (!is_array($ZBX_MESSAGES_PREPARED)) {
				$ZBX_MESSAGES_PREPARED = [];
			}

			// Prepare messages for inclusion within the layout engine.
			$ZBX_MESSAGES_PREPARED[] = [
				'is_good' => $good,
				'messages' => $messages,
				'title' => $title,
				'show_close_box' => true,
				'show_details' => !$good
			];

			break;
	}
}

/**
 * Get prepared HTML messages generated by the current request and, optionally, passed by the previous request.
 *
 * @param array $options['with_auth_warning']      Include unsuccessful authentication warning message.
 * @param array $options['with_session_messages']  Include messages passed by the previous request.
 * @param array $options['with_current_messages']  Include messages generated by the current request.
 *
 * @return string|null  One or several HTML message boxes.
 */
function get_prepared_messages(array $options = []): ?string {
	global $ZBX_MESSAGES, $ZBX_MESSAGES_PREPARED;

	if (!is_array($ZBX_MESSAGES_PREPARED)) {
		$ZBX_MESSAGES_PREPARED = [];
	}

	$options += [
		'with_auth_warning' => false,
		'with_session_messages' => false,
		'with_current_messages' => false
	];

	// Process messages of the current request.

	if ($options['with_current_messages']) {
		show_messages();

		$messages_current = $ZBX_MESSAGES_PREPARED;
		$restore_messages = [];
		$restore_messages_prepared = [];
	}
	else {
		$messages_current = [];
		$restore_messages = $ZBX_MESSAGES;
		$restore_messages_prepared = $ZBX_MESSAGES_PREPARED;
	}

	$ZBX_MESSAGES = [];
	$ZBX_MESSAGES_PREPARED = [];

	// Process authentication warning if user had unsuccessful authentication attempts.

	if ($options['with_auth_warning'] && ($failed_attempts = CProfile::get('web.login.attempt.failed', 0))) {
		$attempt_ip = CProfile::get('web.login.attempt.ip', '');
		$attempt_date = CProfile::get('web.login.attempt.clock', 0);

		error(_n('%4$s failed login attempt logged. Last failed attempt was from %1$s on %2$s at %3$s.',
			'%4$s failed login attempts logged. Last failed attempt was from %1$s on %2$s at %3$s.',
			$attempt_ip,
			zbx_date2str(DATE_FORMAT, $attempt_date),
			zbx_date2str(TIME_FORMAT, $attempt_date),
			$failed_attempts
		));

		show_messages();

		CProfile::update('web.login.attempt.failed', 0, PROFILE_TYPE_INT);
	}

	$messages_authentication = $ZBX_MESSAGES_PREPARED;
	$ZBX_MESSAGES = [];
	$ZBX_MESSAGES_PREPARED = [];

	// Process messages passed by the previous request.

	if ($options['with_session_messages']
			&& (CSession::keyExists('messageOk') || CSession::keyExists('messageError'))) {
		if (CSession::keyExists('messages')) {
			$ZBX_MESSAGES = CSession::getValue('messages');
		}

		if (CSession::keyExists('messageOk')) {
			show_messages(true, CSession::getValue('messageOk'), null);
		}
		else {
			show_messages(false, null, CSession::getValue('messageError'));
		}

		CSession::unsetValue(['messages', 'messageOk', 'messageError']);
	}

	$messages_session = $ZBX_MESSAGES_PREPARED;

	// Create message boxes for all requested messages types in the correct order.

	$html = '';
	foreach (array_merge($messages_authentication, $messages_session, $messages_current) as $box) {
		$html .= makeMessageBox($box['is_good'], $box['messages'], $box['title'], $box['show_close_box'],
			$box['show_details']
		)->toString();
	}

	$ZBX_MESSAGES = $restore_messages;
	$ZBX_MESSAGES_PREPARED = $restore_messages_prepared;

	return ($html === '') ? null : $html;
}

function show_message($msg) {
	show_messages(true, $msg, '');
}

function show_error_message($msg) {
	show_messages(false, '', $msg);
}

function info($msgs) {
	global $ZBX_MESSAGES;

	if (!isset($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = [];
	}

	zbx_value2array($msgs);

	foreach ($msgs as $msg) {
		$ZBX_MESSAGES[] = ['type' => 'info', 'message' => $msg];
	}
}

/*
 * Add an error to global message array.
 *
 * @param string | array $msg	Error message text.
 * @param string		 $src	The source of error message.
 */
function error($msgs, $src = '') {
	global $ZBX_MESSAGES;

	if (!isset($ZBX_MESSAGES)) {
		$ZBX_MESSAGES = [];
	}

	$msgs = zbx_toArray($msgs);

	foreach ($msgs as $msg) {
		$ZBX_MESSAGES[] = [
			'type' => 'error',
			'message' => $msg,
			'src' => $src
		];
	}
}

/**
 * Add multiple errors under single header.
 *
 * @param array  $data
 * @param string $data['header']  common header for all error messages
 * @param array  $data['msgs']    array of error messages
 */
function error_group($data) {
	foreach (zbx_toArray($data['msgs']) as $msg) {
		error($data['header'] . ' ' . $msg);
	}
}

function clear_messages($count = null) {
	global $ZBX_MESSAGES;

	if ($count != null) {
		$result = [];

		while ($count-- > 0) {
			array_unshift($result, array_pop($ZBX_MESSAGES));
		}
	}
	else {
		$result = $ZBX_MESSAGES;
		$ZBX_MESSAGES = [];
	}

	return $result ? filter_messages($result) : $result;
}

function fatal_error($msg) {
	require_once dirname(__FILE__).'/page_header.php';
	show_error_message($msg);
	require_once dirname(__FILE__).'/page_footer.php';
}

function parse_period($str) {
	$out = null;
	$time_periods_parser = new CTimePeriodsParser();

	if ($time_periods_parser->parse($str) != CParser::PARSE_SUCCESS) {
		return null;
	}

	foreach ($time_periods_parser->getPeriods() as $period) {
		if (!preg_match('/^([1-7])-([1-7]),([0-9]{1,2}):([0-9]{1,2})-([0-9]{1,2}):([0-9]{1,2})$/', $period, $matches)) {
			return null;
		}

		for ($i = $matches[1]; $i <= $matches[2]; $i++) {
			if (!isset($out[$i])) {
				$out[$i] = [];
			}
			array_push($out[$i], [
				'start_h' => $matches[3],
				'start_m' => $matches[4],
				'end_h' => $matches[5],
				'end_m' => $matches[6]
			]);
		}
	}

	return $out;
}

function get_status() {
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	$status = [
		'is_running' => false,
		'has_status' => false
	];

	$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
	$status['is_running'] = $server->isRunning(get_cookie(ZBX_SESSION_NAME));

	if ($status['is_running'] === false) {
		return $status;
	}

	$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, 15, ZBX_SOCKET_BYTES_LIMIT);
	$server_status = $server->getStatus(get_cookie(ZBX_SESSION_NAME));
	$status['has_status'] = (bool) $server_status;

	if ($server_status === false) {
		error($server->getError());
		return $status;
	}

	$status += [
		'triggers_count_disabled' => 0,
		'triggers_count_off' => 0,
		'triggers_count_on' => 0,
		'items_count_monitored' => 0,
		'items_count_disabled' => 0,
		'items_count_not_supported' => 0,
		'hosts_count_monitored' => 0,
		'hosts_count_not_monitored' => 0,
		'hosts_count_template' => 0,
		'users_count' => 0,
		'users_online' => 0
	];

	// hosts
	foreach ($server_status['template stats'] as $stats) {
		$status['hosts_count_template'] += $stats['count'];
	}

	foreach ($server_status['host stats'] as $stats) {
		if ($stats['attributes']['proxyid'] == 0) {
			switch ($stats['attributes']['status']) {
				case HOST_STATUS_MONITORED:
					$status['hosts_count_monitored'] += $stats['count'];
					break;

				case HOST_STATUS_NOT_MONITORED:
					$status['hosts_count_not_monitored'] += $stats['count'];
					break;
			}
		}
	}
	$status['hosts_count'] = $status['hosts_count_monitored'] + $status['hosts_count_not_monitored'];

	// items
	foreach ($server_status['item stats'] as $stats) {
		if ($stats['attributes']['proxyid'] == 0) {
			switch ($stats['attributes']['status']) {
				case ITEM_STATUS_ACTIVE:
					if (array_key_exists('state', $stats['attributes'])) {
						switch ($stats['attributes']['state']) {
							case ITEM_STATE_NORMAL:
								$status['items_count_monitored'] += $stats['count'];
								break;

							case ITEM_STATE_NOTSUPPORTED:
								$status['items_count_not_supported'] += $stats['count'];
								break;
						}
					}
					break;

				case ITEM_STATUS_DISABLED:
					$status['items_count_disabled'] += $stats['count'];
					break;
			}
		}
	}
	$status['items_count'] = $status['items_count_monitored'] + $status['items_count_disabled']
			+ $status['items_count_not_supported'];

	// triggers
	foreach ($server_status['trigger stats'] as $stats) {
		switch ($stats['attributes']['status']) {
			case TRIGGER_STATUS_ENABLED:
				if (array_key_exists('value', $stats['attributes'])) {
					switch ($stats['attributes']['value']) {
						case TRIGGER_VALUE_FALSE:
							$status['triggers_count_off'] += $stats['count'];
							break;

						case TRIGGER_VALUE_TRUE:
							$status['triggers_count_on'] += $stats['count'];
							break;
					}
				}
				break;

			case TRIGGER_STATUS_DISABLED:
				$status['triggers_count_disabled'] += $stats['count'];
				break;
		}
	}
	$status['triggers_count_enabled'] = $status['triggers_count_off'] + $status['triggers_count_on'];
	$status['triggers_count'] = $status['triggers_count_enabled'] + $status['triggers_count_disabled'];

	// users
	foreach ($server_status['user stats'] as $stats) {
		switch ($stats['attributes']['status']) {
			case ZBX_SESSION_ACTIVE:
				$status['users_online'] += $stats['count'];
				break;

			case ZBX_SESSION_PASSIVE:
				$status['users_count'] += $stats['count'];
				break;
		}
	}
	$status['users_count'] += $status['users_online'];

	// performance
	if (array_key_exists('required performance', $server_status)) {
		$status['vps_total'] = 0;

		foreach ($server_status['required performance'] as $stats) {
			if ($stats['attributes']['proxyid'] == 0) {
				$status['vps_total'] += $stats['count'];
			}
		}
	}

	return $status;
}

/**
 * Set image header.
 *
 * @param integer $format    One of IMAGE_FORMAT_* constants. If not set global $IMAGE_FORMAT_DEFAULT will be used.
 */
function set_image_header($format = null) {
	global $IMAGE_FORMAT_DEFAULT;

	switch ($format !== null ? $format : $IMAGE_FORMAT_DEFAULT) {
		case IMAGE_FORMAT_JPEG:
			header('Content-type: image/jpeg');
			break;

		case IMAGE_FORMAT_GIF:
			header('Content-type: image/gif');
			break;

		case IMAGE_FORMAT_TEXT:
			header('Content-type: text/html');
			break;

		default:
			header('Content-type: image/png');
	}

	header('Expires: Mon, 17 Aug 1998 12:51:50 GMT');
}

function imageOut(&$image, $format = null) {
	global $page, $IMAGE_FORMAT_DEFAULT;

	if (is_null($format)) {
		$format = $IMAGE_FORMAT_DEFAULT;
	}

	ob_start();

	if (IMAGE_FORMAT_JPEG == $format) {
		imagejpeg($image);
	}
	else {
		imagepng($image);
	}

	$imageSource = ob_get_contents();
	ob_end_clean();

	if ($page['type'] != PAGE_TYPE_IMAGE) {
		$imageId = md5(strlen($imageSource));
		CSession::setValue('image_id', [$imageId => $imageSource]);
	}

	switch ($page['type']) {
		case PAGE_TYPE_IMAGE:
			echo $imageSource;
			break;
		case PAGE_TYPE_JSON:
			echo json_encode(['result' => $imageId]);
			break;
		case PAGE_TYPE_TEXT:
		default:
			echo $imageId;
	}
}

/**
 * Check if we have error messages to display.
 *
 * @global array $ZBX_MESSAGES
 *
 * @return bool
 */
function hasErrorMesssages() {
	global $ZBX_MESSAGES;

	if (isset($ZBX_MESSAGES)) {
		foreach ($ZBX_MESSAGES as $message) {
			if ($message['type'] === 'error') {
				return true;
			}
		}
	}

	return false;
}

/**
 * Clears table rows selection's cookies.
 *
 * @param string $parentid  parent ID, is used as sessionStorage suffix
 * @param array  $keepids   checked rows ids
 */
function uncheckTableRows($parentid = null, $keepids = []) {
	$key = implode('_', array_filter(['cb', basename($_SERVER['SCRIPT_NAME'], '.php'), $parentid]));

	if ($keepids) {
		// If $keepids will not have same key as value, it will create mess, when new checkbox will be checked.
		$keepids = array_combine($keepids, $keepids);

		insert_js('sessionStorage.setItem("'.$key.'", JSON.stringify('.json_encode($keepids).'))');
	}
	else {
		insert_js('sessionStorage.removeItem("'.$key.'")');
	}
}

/**
 * Trim each element of the script path. For example, " a / b / c d " => "a/b/c d"
 *
 * @param string $name
 *
 * @return string
 */
function trimPath($name) {
	$path = splitPath($name);
	$path = array_map('trim', $path);
	$path = str_replace(['\\', '/'], ['\\\\', '\\/'], $path);
	return implode('/', $path);
}

/**
 * Splitting string using slashes with escape backslash support and non-pair backslash cleanup.
 *
 * @param string $path
 *
 * @return array
 */
function splitPath($path) {
	$path_items = [];
	$path_item = '';

	for ($i = 0; isset($path[$i]); $i++) {
		switch ($path[$i]) {
			case '/':
				$path_items[] = $path_item;
				$path_item = '';
				break;

			case '\\':
				if (isset($path[++$i])) {
					$path_item .= $path[$i];
				}
				break;

			default:
				$path_item .= $path[$i];
		}
	}

	$path_items[] = $path_item;

	return $path_items;
}

/**
 * Allocate color for an image.
 *
 * @param resource $image
 * @param string   $color  a hexadecimal color identifier like "1F2C33"
 * @param int      $alpha
 *
 * @return int|false
 */
function get_color($image, $color, $alpha = 0) {
	$red = hexdec('0x'.substr($color, 0, 2));
	$green = hexdec('0x'.substr($color, 2, 2));
	$blue = hexdec('0x'.substr($color, 4, 2));

	if (function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor')
			&& @imagecreatetruecolor(1, 1)) {
		return imagecolorexactalpha($image, $red, $green, $blue, $alpha);
	}

	return imagecolorallocate($image, $red, $green, $blue);
}

/**
 * Get graphic theme based on user configuration.
 *
 * @return array
 */
function getUserGraphTheme() {
	$themes = DB::find('graph_theme', [
		'theme' => getUserTheme(CWebUser::$data)
	]);

	if ($themes) {
		return $themes[0];
	}

	return [
		'theme' => 'blue-theme',
		'textcolor' => '1F2C33',
		'highlightcolor' => 'E33734',
		'backgroundcolor' => 'FFFFFF',
		'graphcolor' => 'FFFFFF',
		'gridcolor' => 'CCD5D9',
		'maingridcolor' => 'ACBBC2',
		'gridbordercolor' => 'ACBBC2',
		'nonworktimecolor' => 'EBEBEB',
		'leftpercentilecolor' => '429E47',
		'righttpercentilecolor' => 'E33734',
		'colorpalette' => '1A7C11,F63100,2774A4,A54F10,FC6EA3,6C59DC,AC8C14,611F27,F230E0,5CCD18,BB2A02,5A2B57,'.
			'89ABF8,7EC25C,274482,2B5429,8048B4,FD5434,790E1F,87AC4D,E89DF4'
	];
}

/**
 * Custom error handler for PHP errors.
 *
 * @param int     $errno Level of the error raised.
 * @param string  $errstr Error message.
 * @param string  $errfile Filename that the error was raised in.
 * @param int     $errline Line number the error was raised in.
 *
 * @return bool  False, to continue with the default error handler.
 */
function zbx_err_handler($errno, $errstr, $errfile, $errline) {
	// Necessary to suppress errors when calling with error control operator like @function_name().
	if (error_reporting() === 0) {
		return true;
	}

	// Don't show the call to this handler function.
	error($errstr.' ['.CProfiler::getInstance()->formatCallStack().']', 'php');

	return false;
}

/**
 * Creates an array with all possible variations of time units.
 * For example: '14d' => ['1209600', '1209600s', '20160m', '336h', '14d', '2w']
 *
 * @param string|array $values
 *
 * @return array
 */
function getTimeUnitFilters($values) {
	if (is_array($values)) {
		$res = [];

		foreach ($values as $value) {
			$res = array_merge($res, getTimeUnitFilters($value));
		}

		return array_unique($res, SORT_STRING);
	}

	$simple_interval_parser = new CSimpleIntervalParser();

	if ($simple_interval_parser->parse($values) != CParser::PARSE_SUCCESS) {
		return [$values];
	}

	$sec = timeUnitToSeconds($values);

	$res = [$sec, $sec.'s'];

	if ($sec % SEC_PER_MIN == 0) {
		$res[] = floor($sec / SEC_PER_MIN).'m';
	}

	if ($sec % SEC_PER_HOUR == 0) {
		$res[] = floor($sec / SEC_PER_HOUR).'h';
	}

	if ($sec % SEC_PER_DAY == 0) {
		$res[] = floor($sec / SEC_PER_DAY).'d';
	}

	if ($sec % SEC_PER_WEEK == 0) {
		$res[] = floor($sec / SEC_PER_WEEK).'w';
	}

	return $res;
}

/**
 * Creates SQL filter to search all possible variations of time units.
 *
 * @param string       $field_name
 * @param string|array $values
 *
 * @return string
 */
function makeUpdateIntervalFilter($field_name, $values) {
	$filters = [];

	foreach (getTimeUnitFilters($values) as $filter) {
		$filter = str_replace("!", "!!", $filter);
		$filter = str_replace("%", "!%", $filter);
		$filter = str_replace("_", "!_", $filter);

		$filters[] = $field_name.' LIKE '.zbx_dbstr($filter).' ESCAPE '.zbx_dbstr('!');
		$filters[] = $field_name.' LIKE '.zbx_dbstr($filter.';%').' ESCAPE '.zbx_dbstr('!');
	}

	$res = $filters ? implode(' OR ', $filters) : '';

	if (count($filters) > 1) {
		$res = '('.$res.')';
	}

	return $res;
}

/**
 * Update profile with new time selector range.
 *
 * @param array       $options
 * @param string      $options['profileIdx']
 * @param int         $options['profileIdx2']
 * @param string|null $options['from']
 * @param string|null $options['to']
 */
function updateTimeSelectorPeriod(array $options) {
	if ($options['from'] !== null && $options['to'] !== null) {
		CProfile::update($options['profileIdx'].'.from', $options['from'], PROFILE_TYPE_STR, $options['profileIdx2']);
		CProfile::update($options['profileIdx'].'.to', $options['to'], PROFILE_TYPE_STR, $options['profileIdx2']);
	}
}

/**
 * Get profile stored 'from' and 'to'. If profileIdx is null then default values will be returned. If one of fields
 * not exist in $options array 'from' and 'to' value will be read from user profile. Calculates from_ts, to_ts.
 *
 * @param array $options  Array with period fields data: profileIdx, profileIdx2, from, to.
 *
 * @return array
 */
function getTimeSelectorPeriod(array $options) {
	$profileIdx = array_key_exists('profileIdx', $options) ? $options['profileIdx'] : null;
	$profileIdx2 = array_key_exists('profileIdx2', $options) ? $options['profileIdx2'] : null;

	if ($profileIdx === null) {
		$options['from'] = ZBX_PERIOD_DEFAULT_FROM;
		$options['to'] = ZBX_PERIOD_DEFAULT_TO;
	}
	elseif (!array_key_exists('from', $options) || !array_key_exists('to', $options)
			|| $options['from'] === null || $options['to'] === null) {
		$options['from'] = CProfile::get($profileIdx.'.from', ZBX_PERIOD_DEFAULT_FROM, $profileIdx2);
		$options['to'] = CProfile::get($profileIdx.'.to', ZBX_PERIOD_DEFAULT_TO, $profileIdx2);
	}

	$range_time_parser = new CRangeTimeParser();

	$range_time_parser->parse($options['from']);
	$options['from_ts'] = $range_time_parser->getDateTime(true)->getTimestamp();
	$range_time_parser->parse($options['to']);
	$options['to_ts'] = $range_time_parser->getDateTime(false)->getTimestamp();

	return $options;
}

/**
 * Convert relative date range string to translated string. Function does not check is passed date range correct.
 *
 * @param string $from     Start date of date range.
 * @param string $to       End date of date range.
 *
 * @return string
 */
function relativeDateToText($from, $to) {
	$key = $from.':'.$to;
	$ranges = [
		'now-1d/d:now-1d/d' => _('Yesterday'),
		'now-2d/d:now-2d/d' => _('Day before yesterday'),
		'now-1w/d:now-1w/d' => _('This day last week'),
		'now-1w/w:now-1w/w' => _('Previous week'),
		'now-1M/M:now-1M/M' => _('Previous month'),
		'now-1y/y:now-1y/y' => _('Previous year'),
		'now/d:now/d' => _('Today'),
		'now/d:now' => _('Today so far'),
		'now/w:now/w' => _('This week'),
		'now/w:now' => _('This week so far'),
		'now/M:now/M' => _('This month'),
		'now/M:now' => _('This month so far'),
		'now/y:now/y' => _('This year'),
		'now/y:now' => _('This year so far')
	];

	if (array_key_exists($key, $ranges)) {
		return $ranges[$key];
	}

	if ($to === 'now') {
		$relative_time_parser = new CRelativeTimeParser();

		if ($relative_time_parser->parse($from) == CParser::PARSE_SUCCESS) {
			$tokens = $relative_time_parser->getTokens();

			if (count($tokens) == 1 && $tokens[0]['type'] == CRelativeTimeParser::ZBX_TOKEN_OFFSET
					&& $tokens[0]['sign'] === '-') {
				$suffix = $tokens[0]['suffix'];
				$value = (int) $tokens[0]['value'];

				switch ($suffix) {
					case 's':
						if ($value < 60 || $value % 60 != 0) {
							return _n('Last %1$d second', 'Last %1$d seconds', $value);
						}
						$value /= 60;
						// break; is not missing here.

					case 'm':
						if ($value < 60 || $value % 60 != 0) {
							return _n('Last %1$d minute', 'Last %1$d minutes', $value);
						}
						$value /= 60;
						// break; is not missing here.

					case 'h':
						if ($value < 24 || $value % 24 != 0) {
							return _n('Last %1$d hour', 'Last %1$d hours', $value);
						}
						$value /= 24;
						// break; is not missing here.

					case 'd':
						return _n('Last %1$d day', 'Last %1$d days', $value);

					case 'M':
						return _n('Last %1$d month', 'Last %1$d months', $value);

					case 'y':
						return _n('Last %1$d year', 'Last %1$d years', $value);
				}
			}
		}
	}

	return $from.' – '.$to;
}
