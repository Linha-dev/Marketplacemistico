import { withCors } from '../middleware.js';
import { requireInternalRole } from '../auth-middleware.js';
import { observabilityAlertsController } from '../modules/observability/ops/controller.js';

export default withCors(requireInternalRole(observabilityAlertsController));
