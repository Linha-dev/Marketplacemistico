import { withCors } from '../middleware.js';
import sellerPublicProfileController from '../modules/sellers/public-profile/controller.js';

export default withCors(sellerPublicProfileController);
