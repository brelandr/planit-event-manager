#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
fail=0
req(){ local f="$1" pat="$2" msg="$3"; grep -q "$pat" "$f" || { echo "FAIL: $msg ($f)"; fail=1; }; }
req includes/class-twec-recurring.php "recurrence/preview" "RRULE preview"
req includes/class-twec-recurring.php "remember_rrule_notice" "RRULE validation"
req includes/class-twec-woo-series-pass.php "META_SERIES_TERM" "Woo series pass"
req includes/class-twec-premium-pillars.php "/rsvp/checkin" "RSVP check-in"
req includes/class-twec-premium-pillars.php "/rsvp-scan" "RSVP scan alias"
req includes/class-twec-premium-pillars.php "issue_attendee_token" "RSVP tokens"
for f in includes/class-twec-recurring.php includes/class-twec-woo-series-pass.php includes/class-twec-premium-pillars.php; do
  php -l "$f" >/dev/null
done
[[ "$fail" -eq 0 ]] || exit 1
echo "smoke-free-roadmap-parity OK"
