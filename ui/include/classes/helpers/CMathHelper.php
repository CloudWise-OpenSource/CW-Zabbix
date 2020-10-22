<?php declare(strict_types = 1);
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
 * A math helper class.
 */
class CMathHelper {

	/**
	* Calculate sum of values (overflow safe).
	*
	* @param array $values
	*
	* @return float
	*/
	public static function safeSum(array $values): float {
		sort($values, SORT_NUMERIC);

		$result = $values[0];

		$head = 1;
		$tail = count($values) - 1;

		while ($head <= $tail) {
			$result += $values[$result > 0 ? $head++ : $tail--];
		}

		return $result;
	}

	/**
	* Calculate multiplication of values (overflow safe).
	*
	* @param array $values
	*
	* @return float
	*/
	public static function safeMul(array $values): float {
		usort($values, function(float $a, float $b): int {
			return abs($a) <=> abs($b);
		});

		$result = $values[0];

		$head = 1;
		$tail = count($values) - 1;

		while ($head <= $tail) {
			$result *= $values[abs($result) > 1 ? $head++ : $tail--];
		}

		return $result;
	}

	/**
	 * Calculate average of values (overflow safe).
	 *
	 * @param array $values  A non-empty array of values.
	 *
	 * @return float
	 */
	public static function safeAvg(array $values): float {
		sort($values, SORT_NUMERIC);

		$result = 0;

		$count = 1;
		$head = 0;
		$tail = count($values) - 1;

		while ($head <= $tail) {
			$value = $values[$result > 0 ? $head++ : $tail--];

			// Expression optimized to avoid overflow.
			$result += $value / $count - $result / $count;

			$count++;
		}

		return $result;
	}
}
