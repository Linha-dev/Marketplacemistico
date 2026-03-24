import { withCors } from '../../middleware.js';
import { requireInternalRole } from '../../auth-middleware.js';
import { requireFinanceOpsSecret } from '../../finance/ops-auth.js';
import { manualPayoutActionController } from '../../modules/finance/ops/controller.js';

async function handler(req, res) {
  const auth = requireFinanceOpsSecret(req, res);
  if (!auth.ok) {
    return undefined;
  }

  return manualPayoutActionController(req, res);
}

export default withCors(requireInternalRole(handler));
