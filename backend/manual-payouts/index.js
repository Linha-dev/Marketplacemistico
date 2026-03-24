import { withCors } from '../middleware.js';
import { requireInternalRole } from '../auth-middleware.js';
import { requireFinanceOpsSecret } from '../finance/ops-auth.js';
import { manualPayoutsListController } from '../modules/finance/ops/controller.js';

async function handler(req, res) {
  const auth = requireFinanceOpsSecret(req, res);
  if (!auth.ok) {
    return undefined;
  }

  return manualPayoutsListController(req, res);
}

export default withCors(requireInternalRole(handler));
