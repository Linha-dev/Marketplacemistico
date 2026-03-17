DROP INDEX IF EXISTS idx_manual_payout_actions_payout_id;
DROP TABLE IF EXISTS manual_payout_actions CASCADE;

ALTER TABLE manual_payouts
  DROP COLUMN IF EXISTS proof_url,
  DROP COLUMN IF EXISTS review_reason,
  DROP COLUMN IF EXISTS approved_at,
  DROP COLUMN IF EXISTS rejected_at;

ALTER TABLE manual_payouts
  DROP CONSTRAINT IF EXISTS chk_manual_payouts_status_allowed;
ALTER TABLE manual_payouts
  ADD CONSTRAINT chk_manual_payouts_status_allowed
  CHECK (status IN ('pending', 'processing', 'paid', 'failed', 'rejected', 'cancelled')) NOT VALID;
