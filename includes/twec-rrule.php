<?php
/**
 * Minimal RRULE expansion for PlanIt recurring events (subset of RFC 5545).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expands instances from DTSTART, RRULE, and optional EXDATE list.
 */
class TWEC_RRule_Expand {

	/**
	 * Default maximum generated instances (performance cap).
	 */
	const MAX_INSTANCES = 500;

	/**
	 * @param string $raw Newline or comma separated Y-m-d.
	 * @return array<string, true>
	 */
	public static function parse_exdates( $raw ) {
		$out = array();
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return $out;
		}
		foreach ( preg_split( '/\r\n|\r|\n|,/', $raw ) as $line ) {
			$d = trim( $line );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
				$out[ $d ] = true;
			}
		}
		return $out;
	}

	/**
	 * @param string $line RRULE line.
	 * @return array<string, string>
	 */
	public static function parse_rrule( $line ) {
		$line = trim( (string) $line );
		if ( 0 === strpos( $line, 'RRULE:' ) ) {
			$line = substr( $line, 6 );
		}
		$rule = array();
		foreach ( explode( ';', $line ) as $p ) {
			if ( false === strpos( $p, '=' ) ) {
				continue;
			}
			list( $k, $v )                    = explode( '=', $p, 2 );
			$rule[ strtoupper( trim( $k ) ) ] = trim( $v );
		}
		return $rule;
	}

	/**
	 * @param string      $base_start    Start datetime.
	 * @param string      $base_end      End datetime.
	 * @param string      $rrule         RRULE.
	 * @param string      $exdates       Raw exclusions.
	 * @param string|null $range_start   Optional range.
	 * @param string|null $range_end     Optional range.
	 * @param int|null    $max_instances Cap.
	 * @return array<int, array{start:string,end:string}>
	 */
	public static function expand( $base_start, $base_end, $rrule, $exdates = '', $range_start = null, $range_end = null, $max_instances = null ) {
		$max  = $max_instances ? min( (int) $max_instances, self::MAX_INSTANCES ) : self::MAX_INSTANCES;
		$rule = self::parse_rrule( $rrule );
		if ( empty( $rule['FREQ'] ) ) {
			return array();
		}
		$freq = strtoupper( (string) $rule['FREQ'] );
		$iv   = isset( $rule['INTERVAL'] ) ? max( 1, (int) $rule['INTERVAL'] ) : 1;

		$dt0 = new DateTime( $base_start );
		$dur = max( 0, (int) ( strtotime( $base_end ) - strtotime( $base_start ) ) );
		$ex  = self::parse_exdates( (string) $exdates );

		$until = null;
		if ( ! empty( $rule['UNTIL'] ) ) {
			$until = self::parse_until( (string) $rule['UNTIL'] );
		}
		$count = isset( $rule['COUNT'] ) ? min( (int) $rule['COUNT'], $max ) : null;

		$rs = $range_start ? new DateTime( $range_start ) : null;
		$re = $range_end ? new DateTime( $range_end ) : null;

		if ( 'WEEKLY' === $freq && ! empty( $rule['BYDAY'] ) && self::weekly_byday_has_only_plain_tokens( (string) $rule['BYDAY'] ) ) {
			return self::expand_series_weekly_byday( $dt0, $rule, $ex, $until, $re, $rs, $count, $max, $dur, $iv );
		}

		if ( 'YEARLY' === $freq && ! empty( $rule['BYDAY'] ) && ! empty( $rule['BYMONTH'] ) ) {
			return self::expand_yearly_bymonth_byday( $dt0, $rule, $ex, $until, $re, $rs, $count, $max, $dur, $iv );
		}

		$out   = array();
		$added = 0;
		$k     = 0;

		while ( $added < $max && ( ! $count || $k < $count * 3 ) && $k < 2000 ) {
			$cur = null;
			if ( 'MONTHLY' === $freq && ! empty( $rule['BYDAY'] ) ) {
				$cur = self::instance_monthly_byday( $dt0, $rule, $iv, $k );
			} elseif ( 'WEEKLY' === $freq && ! empty( $rule['BYDAY'] ) ) {
				$cur = self::instance_weekly_byday( $dt0, $rule, $iv, $k );
			} else {
				$cur = self::instance_simple( $dt0, $freq, $iv, $k );
			}
			++$k;
			if ( ! $cur ) {
				break;
			}
			if ( $until && $cur > $until ) {
				break;
			}
			if ( $re && $cur > $re ) {
				break;
			}
			if ( $rs && $cur < $rs ) {
				continue;
			}
			$day_key = $cur->format( 'Y-m-d' );
			if ( isset( $ex[ $day_key ] ) ) {
				if ( $count && $added >= $count ) {
					break;
				}
				continue;
			}
			$end = clone $cur;
			$end->modify( '+' . $dur . ' seconds' );
			$out[] = array(
				'start' => $cur->format( 'Y-m-d H:i:s' ),
				'end'   => $end->format( 'Y-m-d H:i:s' ),
			);
			++$added;
			if ( $count && $added >= $count ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Parse BYMONTH comma list (1-12).
	 *
	 * @param string $raw Raw BYMONTH value.
	 * @return int[]
	 */
	private static function parse_bymonth_list( $raw ) {
		$raw = strtoupper( trim( (string) $raw ) );
		$out = array();
		foreach ( array_map( 'trim', explode( ',', $raw ) ) as $p ) {
			if ( '' === $p ) {
				continue;
			}
			if ( preg_match( '/^\d{1,2}$/', $p ) ) {
				$m = (int) $p;
				if ( $m >= 1 && $m <= 12 ) {
					$out[] = $m;
				}
			}
		}
		$out = array_unique( $out, SORT_REGULAR );
		sort( $out, SORT_NUMERIC );
		return array_values( $out );
	}

	/**
	 * FREQ=YEARLY with BYMONTH and positional BYDAY (e.g. first Friday in October).
	 *
	 * @param DateTime            $dt0   DTSTART.
	 * @param array<string,mixed> $rule  Parsed RRULE.
	 * @param array<string,true>  $ex    EXDATE map.
	 * @param DateTime|null       $until UNTIL.
	 * @param DateTime|null       $re    Range end.
	 * @param DateTime|null       $rs    Range start.
	 * @param int|null            $count COUNT.
	 * @param int                 $max   Max instances.
	 * @param int                 $dur   Duration seconds.
	 * @param int                 $interval Year INTERVAL.
	 * @return array<int, array{start:string,end:string}>
	 */
	private static function expand_yearly_bymonth_byday( DateTime $dt0, array $rule, array $ex, $until, $re, $rs, $count, $max, $dur, $interval ) {
		$months = self::parse_bymonth_list( (string) $rule['BYMONTH'] );
		if ( empty( $months ) ) {
			return array();
		}

		$byday   = strtoupper( trim( (string) $rule['BYDAY'] ) );
		$map     = array(
			'SU' => 0,
			'MO' => 1,
			'TU' => 2,
			'WE' => 3,
			'TH' => 4,
			'FR' => 5,
			'SA' => 6,
		);
		$ord     = 1;
		$weekday = 'MO';
		if ( preg_match( '/^(-?\d+)([A-Z]{2})$/', $byday, $m ) ) {
			$ord     = (int) $m[1];
			$weekday = $m[2];
		} else {
			return array();
		}
		$wd = isset( $map[ $weekday ] ) ? (int) $map[ $weekday ] : 1;

		$base_y = (int) $dt0->format( 'Y' );
		$h      = (int) $dt0->format( 'H' );
		$mi     = (int) $dt0->format( 'i' );
		$s      = (int) $dt0->format( 's' );

		$out   = array();
		$added = 0;

		for ( $yi = 0; $yi < 400 && $added < $max; ++$yi ) {
			$y = $base_y + ( $yi * $interval );
			foreach ( $months as $mo ) {
				$cand = self::nth_weekday_in_month( $y, $mo, $wd, $ord );
				if ( ! $cand ) {
					continue;
				}
				$cur = clone $cand;
				$cur->setTime( $h, $mi, $s );
				if ( $cur < $dt0 ) {
					continue;
				}
				if ( $rs && $cur < $rs ) {
					continue;
				}
				if ( $re && $cur > $re ) {
					return $out;
				}
				if ( $until && $cur > $until ) {
					return $out;
				}
				$day_key = $cur->format( 'Y-m-d' );
				if ( isset( $ex[ $day_key ] ) ) {
					continue;
				}
				$end = clone $cur;
				$end->modify( '+' . $dur . ' seconds' );
				$out[] = array(
					'start' => $cur->format( 'Y-m-d H:i:s' ),
					'end'   => $end->format( 'Y-m-d H:i:s' ),
				);
				++$added;
				if ( $count && $added >= $count ) {
					return $out;
				}
			}
		}

		return $out;
	}

	/**
	 * True if BYDAY is a comma list of 2-letter weekday codes (multi-token WEEKLY path).
	 *
	 * @param string $byday BYDAY value.
	 * @return bool
	 */
	private static function weekly_byday_has_only_plain_tokens( $byday ) {
		$byday = strtoupper( trim( (string) $byday ) );
		foreach ( array_map( 'trim', explode( ',', $byday ) ) as $p ) {
			if ( '' === $p ) {
				continue;
			}
			if ( ! preg_match( '/^[A-Z]{2}$/', $p ) ) {
				return false;
			}
		}
		return (bool) $byday;
	}

	/**
	 * 0=Sun..6=Sat (BYDAY) to days after Monday 00:00 of the same ISO week.
	 *
	 * @param int $w Weekday 0-6.
	 * @return int
	 */
	private static function wday_to_offset_from_monday( $w ) {
		$w = (int) $w;
		return 0 === $w ? 6 : ( $w - 1 );
	}

	/**
	 * @param string $byday Comma BYDAY.
	 * @return int[] Sorted unique weekday numbers (SU=0..SA=6), by Monday-first order within the week.
	 */
	private static function parse_weekly_wday_list( $byday ) {
		$byday = strtoupper( trim( (string) $byday ) );
		$map   = array(
			'SU' => 0,
			'MO' => 1,
			'TU' => 2,
			'WE' => 3,
			'TH' => 4,
			'FR' => 5,
			'SA' => 6,
		);
		$wds   = array();
		foreach ( array_map( 'trim', explode( ',', $byday ) ) as $p ) {
			if ( '' !== $p && isset( $map[ $p ] ) ) {
				$wds[] = (int) $map[ $p ];
			}
		}
		$wds = array_unique( $wds, SORT_REGULAR );
		usort(
			$wds,
			static function ( $a, $b ) {
				$oa = ( 0 === (int) $a ) ? 6 : ( (int) $a - 1 );
				$ob = ( 0 === (int) $b ) ? 6 : ( (int) $b - 1 );
				return $oa <=> $ob;
			}
		);
		return array_values( $wds );
	}

	/**
	 * Monday 00:00:00 of the ISO week that contains $ref (same as PHP "Monday this week" from ref).
	 *
	 * @param DateTime $ref Reference instant.
	 * @return DateTime
	 */
	private static function monday_of_same_week( DateTime $ref ) {
		$c = clone $ref;
		$n = (int) $c->format( 'N' );
		$c->modify( '-' . ( $n - 1 ) . ' days' );
		$c->setTime( 0, 0, 0 );
		return $c;
	}

	/**
	 * Expand FREQ=WEEKLY with one or more plain BYDAY tokens (e.g. TU or TU,TH) in chronological order.
	 *
	 * @param DateTime            $dt0   DTSTART.
	 * @param array<string,mixed> $rule  Parsed RRULE.
	 * @param array               $ex    EXDATE map.
	 * @param DateTime|null       $until UNTIL.
	 * @param DateTime|null       $re    Range end.
	 * @param DateTime|null       $rs    Range start.
	 * @param int|null            $count COUNT.
	 * @param int                 $max   Max instances.
	 * @param int                 $dur   Duration seconds.
	 * @param int                 $interval INTERVAL.
	 * @return array<int, array{start:string,end:string}>
	 */
	private static function expand_series_weekly_byday( DateTime $dt0, array $rule, array $ex, $until, $re, $rs, $count, $max, $dur, $interval ) {
		$wds = self::parse_weekly_wday_list( (string) $rule['BYDAY'] );
		if ( empty( $wds ) ) {
			return array();
		}
		$mon0   = self::monday_of_same_week( clone $dt0 );
		$out    = array();
		$added  = 0;
		$week_i = 0;
		$h      = (int) $dt0->format( 'H' );
		$mi     = (int) $dt0->format( 'i' );
		$s      = (int) $dt0->format( 's' );

		while ( $week_i < 2000 && $added < $max ) {
			$m = clone $mon0;
			$m->modify( '+' . ( $week_i * $interval * 7 ) . ' days' );
			foreach ( $wds as $w ) {
				$off = self::wday_to_offset_from_monday( $w );
				$cur = clone $m;
				$cur->modify( '+' . $off . ' days' );
				$cur->setTime( $h, $mi, $s );
				if ( $cur < $dt0 ) {
					continue;
				}
				if ( $rs && $cur < $rs ) {
					continue;
				}
				if ( $re && $cur > $re ) {
					return $out;
				}
				if ( $until && $cur > $until ) {
					return $out;
				}
				$day_key = $cur->format( 'Y-m-d' );
				if ( isset( $ex[ $day_key ] ) ) {
					continue;
				}
				$end = clone $cur;
				$end->modify( '+' . $dur . ' seconds' );
				$out[] = array(
					'start' => $cur->format( 'Y-m-d H:i:s' ),
					'end'   => $end->format( 'Y-m-d H:i:s' ),
				);
				++$added;
				if ( $count && $added >= $count ) {
					return $out;
				}
				if ( $added >= $max ) {
					return $out;
				}
			}
			++$week_i;
		}
		return $out;
	}

	/**
	 * @param string $until UNTIL.
	 * @return DateTime|null
	 */
	private static function parse_until( $until ) {
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})T/', $until, $m ) ) {
			return new DateTime( $m[1] . '-' . $m[2] . '-' . $m[3] . ' 23:59:59' );
		}
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $until, $m ) ) {
			return new DateTime( $m[1] . '-' . $m[2] . '-' . $m[3] . ' 23:59:59' );
		}
		return null;
	}

	/**
	 * @param DateTime $dt0 Base.
	 * @param string   $freq FREQ.
	 * @param int      $interval INTERVAL.
	 * @param int      $k      Zero-based index.
	 * @return DateTime|null
	 */
	private static function instance_simple( DateTime $dt0, $freq, $interval, $k ) {
		$c = clone $dt0;
		switch ( $freq ) {
			case 'DAILY':
				$c->modify( '+' . ( $k * $interval ) . ' days' );
				return $c;
			case 'WEEKLY':
				$c->modify( '+' . ( $k * $interval ) . ' weeks' );
				return $c;
			case 'MONTHLY':
				$c->modify( '+' . ( $k * $interval ) . ' months' );
				return $c;
			case 'YEARLY':
				$c->modify( '+' . ( $k * $interval ) . ' years' );
				return $c;
		}
		return null;
	}

	/**
	 * @param DateTime $dt0 Base.
	 * @param array    $rule Rule with BYDAY (single token: -1FR, 2TU, MO).
	 * @param int      $interval Month interval.
	 * @param int      $k Index.
	 * @return DateTime|null
	 */
	private static function instance_monthly_byday( DateTime $dt0, array $rule, $interval, $k ) {
		$byday   = strtoupper( trim( (string) $rule['BYDAY'] ) );
		$map     = array(
			'SU' => 0,
			'MO' => 1,
			'TU' => 2,
			'WE' => 3,
			'TH' => 4,
			'FR' => 5,
			'SA' => 6,
		);
		$ord     = 1;
		$weekday = 'MO';
		if ( preg_match( '/^(-?\d+)([A-Z]{2})$/', $byday, $m ) ) {
			$ord     = (int) $m[1];
			$weekday = $m[2];
		} elseif ( preg_match( '/^([A-Z]{2})$/', $byday, $m ) ) {
			$weekday = $m[1];
		} else {
			return self::instance_simple( $dt0, 'MONTHLY', $interval, $k );
		}
		$wd = isset( $map[ $weekday ] ) ? (int) $map[ $weekday ] : 1;

		$anchor = clone $dt0;
		$anchor->modify( 'first day of this month' );
		$anchor->setTime( (int) $dt0->format( 'H' ), (int) $dt0->format( 'i' ), (int) $dt0->format( 's' ) );
		$anchor->modify( '+' . ( $k * $interval ) . ' month' );
		$y   = (int) $anchor->format( 'Y' );
		$m   = (int) $anchor->format( 'm' );
		$res = self::nth_weekday_in_month( $y, $m, $wd, $ord );
		if ( ! $res ) {
			return null;
		}
		$res->setTime( (int) $dt0->format( 'H' ), (int) $dt0->format( 'i' ), (int) $dt0->format( 's' ) );
		return $res;
	}

	/**
	 * @param int $y Year.
	 * @param int $m Month.
	 * @param int $wday 0-6.
	 * @param int $ord  Nth, -1 last, etc.
	 * @return DateTime|null
	 */
	private static function nth_weekday_in_month( $y, $m, $wday, $ord ) {
		$first = new DateTime( sprintf( '%04d-%02d-01 12:00:00', $y, $m ) );
		$last  = new DateTime( sprintf( '%04d-%02d-01 12:00:00', $y, $m ) );
		$last->modify( 'last day of this month' );
		if ( $ord < 0 ) {
			$cur = clone $last;
			while ( (int) $cur->format( 'w' ) !== $wday ) {
				$cur->modify( '-1 day' );
			}
			for ( $i = -1; $i > $ord; --$i ) {
				$cur->modify( '-7 days' );
			}
			return ( (int) $cur->format( 'n' ) === $m ) ? $cur : null;
		}
		$cur = clone $first;
		while ( (int) $cur->format( 'w' ) !== $wday ) {
			$cur->modify( '+1 day' );
		}
		if ( $ord > 1 ) {
			$cur->modify( '+' . ( ( $ord - 1 ) * 7 ) . ' days' );
		}
		return ( (int) $cur->format( 'n' ) === $m ) ? $cur : null;
	}

	/**
	 * @param DateTime $dt0 Base.
	 * @param array    $rule Rule.
	 * @param int      $interval Week interval.
	 * @param int      $k Index.
	 * @return DateTime|null
	 */
	private static function instance_weekly_byday( DateTime $dt0, array $rule, $interval, $k ) {
		$byday   = strtoupper( trim( (string) $rule['BYDAY'] ) );
		$map     = array(
			'SU' => 0,
			'MO' => 1,
			'TU' => 2,
			'WE' => 3,
			'TH' => 4,
			'FR' => 5,
			'SA' => 6,
		);
		$parts   = array_map( 'trim', explode( ',', $byday ) );
		$token   = isset( $parts[0] ) ? $parts[0] : 'MO';
		$wday    = isset( $map[ $token ] ) ? (int) $map[ $token ] : 1;
		$start_w = (int) $dt0->format( 'w' );
		$delta   = ( $wday - $start_w + 7 ) % 7;
		$c       = clone $dt0;
		$c->modify( '+' . $delta . ' days' );
		$c->modify( '+' . ( $k * 7 * $interval ) . ' days' );
		return $c;
	}
}
