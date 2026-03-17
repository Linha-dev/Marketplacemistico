ALTER TABLE manual_payouts
  DROP CONSTRAINT IF EXISTS chk_manual_payouts_status_allowed;
ALTER TABLE manual_payouts
  ADD CONSTRAINT chk_manual_payouts_status_allowed
  CHECK (status IN ('pending', 'approved', 'processing', 'paid', 'failed', 'rejected', 'cancelled')) NOT VALID;

ALTER TABLE manual_payouts
  ADD COLUMN IF NOT EXISTS proof_url TEXT,
  ADD COLUMN IF NOT EXISTS review_reason TEXT,
  ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP,
  ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP;

CREATE TABLE IF NOT EXISTS manual_payout_actions (
    id SERIAL PRIMARY KEY,
    manual_payout_id INTEGER NOT NULL REFERENCES manual_payouts(id) ON DELETE CASCADE,
    action VARCHAR(30) NOT NULL,
    previous_status VARCHAR(30),
    new_status VARCHAR(30) NOT NULL,
    reason TEXT,
    proof_url TEXT,
    external_reference VARCHAR(255),
    acted_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_manual_payout_actions_payout_id
  ON manual_payout_actions(manual_payout_id, created_at DESC);
