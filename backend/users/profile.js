import { withCors } from '../middleware.js';
import { requireAuth } from '../auth-middleware.js';
import usersProfileController from '../modules/users/profile/controller.js';

export default withCors(requireAuth(usersProfileController));
