CREATE TABLE status_checks (
  t  TIMESTAMPTZ PRIMARY KEY DEFAULT NOW(),
  s  SMALLINT    NOT NULL
  -- s bit layout:
  -- bits 0-1 → api (0=ok, 1=slow, 2=down)
  -- bits 2-3 → db  (0=ok, 1=slow, 2=down)
);
