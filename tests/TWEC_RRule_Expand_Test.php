<?php
/**
 * Tests for {@see TWEC_RRule_Expand} (matrix T1–T8 + T5b).
 *
 * @package PlanIt_Event_Manager
 */

use PHPUnit\Framework\TestCase;

class TWEC_RRule_Expand_Test extends TestCase {

	/**
	 * T1: DAILY COUNT=3
	 */
	public function test_t1_daily_count_3() {
		$start = '2025-01-01 10:00:00';
		$end   = '2025-01-01 11:00:00';
		$rr    = 'FREQ=DAILY;INTERVAL=1;COUNT=3';
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr );
		$this->assertCount( 3, $out );
		$this->assertSame( '2025-01-01 10:00:00', $out[0]['start'] );
		$this->assertSame( '2025-01-02 10:00:00', $out[1]['start'] );
		$this->assertSame( '2025-01-03 10:00:00', $out[2]['start'] );
	}

	/**
	 * T2: DAILY + UNTIL (date form)
	 */
	public function test_t2_daily_until() {
		$start = '2025-11-28 10:00:00';
		$end   = '2025-11-28 11:00:00';
		$rr    = 'FREQ=DAILY;INTERVAL=1;UNTIL=20251201';
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr );
		$days  = array_map(
			static function ( $i ) {
				return substr( $i['start'], 0, 10 );
			},
			$out
		);
		$this->assertSame( array( '2025-11-28', '2025-11-29', '2025-11-30', '2025-12-01' ), $days );
	}

	/**
	 * T3: EXDATE excludes a day in a 3-day window (use UNTIL; COUNT+EXDATE counts non-excluded in expand()).
	 */
	public function test_t3_exdate_middle_day() {
		$start = '2025-01-01 10:00:00';
		$end   = '2025-01-01 11:00:00';
		$rr    = 'FREQ=DAILY;INTERVAL=1;UNTIL=20250103';
		$ex    = "2025-01-02\n";
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr, $ex );
		$this->assertCount( 2, $out );
		$this->assertSame( '2025-01-01 10:00:00', $out[0]['start'] );
		$this->assertSame( '2025-01-03 10:00:00', $out[1]['start'] );
	}

	/**
	 * T4: last Friday of month
	 */
	public function test_t4_monthly_byday_last_friday() {
		$start = '2025-01-15 10:00:00';
		$end   = '2025-01-15 11:00:00';
		$rr    = 'FREQ=MONTHLY;BYDAY=-1FR;INTERVAL=1;COUNT=1';
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr );
		$this->assertCount( 1, $out );
		$this->assertStringStartsWith( '2025-01-31', $out[0]['start'] );
	}

	/**
	 * T5: WEEKLY + single BYDAY=TU
	 */
	public function test_t5_weekly_byday_tuesday() {
		$start = '2025-01-01 10:00:00';
		$end   = '2025-01-01 11:00:00';
		$rr    = 'FREQ=WEEKLY;BYDAY=TU;INTERVAL=1;COUNT=2';
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr );
		$this->assertCount( 2, $out );
		$this->assertStringStartsWith( '2025-01-07', $out[0]['start'] );
		$this->assertStringStartsWith( '2025-01-14', $out[1]['start'] );
	}

	/**
	 * T5b: WEEKLY + two BYDAY tokens, chronological, COUNT cap
	 */
	public function test_t5b_weekly_byday_tu_and_th() {
		$start = '2025-01-01 10:00:00';
		$end   = '2025-01-01 11:00:00';
		$rr    = 'FREQ=WEEKLY;BYDAY=TU,TH;INTERVAL=1;COUNT=4';
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr );
		$this->assertCount( 4, $out );
		$this->assertStringStartsWith( '2025-01-02', $out[0]['start'] );
		$this->assertStringStartsWith( '2025-01-07', $out[1]['start'] );
		$this->assertStringStartsWith( '2025-01-09', $out[2]['start'] );
		$this->assertStringStartsWith( '2025-01-14', $out[3]['start'] );
	}

	/**
	 * T6: max instances cap
	 */
	public function test_t6_max_instances_cap() {
		$start = '2025-01-01 10:00:00';
		$end   = '2025-01-01 11:00:00';
		$rr    = 'FREQ=DAILY;INTERVAL=1;COUNT=20';
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr, '', null, null, 5 );
		$this->assertCount( 5, $out );
	}

	/**
	 * T7: YEARLY + BYMONTH + positional BYDAY (first Friday in October).
	 */
	public function test_t7_yearly_bymonth_first_friday() {
		$start = '2024-10-01 10:00:00';
		$end   = '2024-10-01 11:00:00';
		$rr    = 'FREQ=YEARLY;BYMONTH=10;BYDAY=1FR;INTERVAL=1;COUNT=2';
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr );
		$this->assertCount( 2, $out );
		$this->assertSame( '2024-10-04 10:00:00', $out[0]['start'] );
		$this->assertSame( '2025-10-03 10:00:00', $out[1]['start'] );
	}

	/**
	 * T8: YEARLY INTERVAL=2 with BYMONTH + BYDAY (every other year).
	 */
	public function test_t8_yearly_interval_skips_year() {
		$start = '2024-10-01 10:00:00';
		$end   = '2024-10-01 11:00:00';
		$rr    = 'FREQ=YEARLY;BYMONTH=10;BYDAY=1FR;INTERVAL=2;COUNT=2';
		$out   = TWEC_RRule_Expand::expand( $start, $end, $rr );
		$this->assertCount( 2, $out );
		$this->assertSame( '2024-10-04 10:00:00', $out[0]['start'] );
		$this->assertSame( '2026-10-02 10:00:00', $out[1]['start'] );
	}

	/**
	 * parse_exdates: comma and newline
	 */
	public function test_parse_exdates() {
		$ex = TWEC_RRule_Expand::parse_exdates( "2025-01-01,2025-01-04\n" );
		$this->assertArrayHasKey( '2025-01-01', $ex );
		$this->assertArrayHasKey( '2025-01-04', $ex );
	}
}
