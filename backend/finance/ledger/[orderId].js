import { withCors } from '../../middleware.js';
import { requireAuth } from '../../auth-middleware.js';
import { financeLedgerController } from '../../modules/finance/ops/controller.js';

export default withCors(requireAuth(financeLedgerController));
