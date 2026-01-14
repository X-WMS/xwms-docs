export { XwmsClient } from "./client.js";
export type { XwmsClientConfig, XwmsResponse, RequestOptions } from "./client.js";

export {
  authenticateAndSyncUser,
  syncAuthenticatedUserFromXwms,
  mapUserAttributes,
  defaultFieldMap,
  extractUserData,
} from "./userSync.js";
export type {
  UserSyncAdapter,
  UserSyncOptions,
  AuthenticateAndSyncParams,
  SyncUserParams,
  FieldMap,
  FieldTransforms,
} from "./userSync.js";

export {
  createNodeStorage,
  downloadRemoteImage,
  extensionFromContentType,
  isRemoteUrl,
  syncUserImage,
} from "./image.js";
export type { ImageSyncOptions, FileStorage } from "./image.js";

export {
  createXwmsExpressRouter,
  createXwmsHandlers,
  createRequireAuth,
} from "./express.js";
export type { XwmsExpressOptions } from "./express.js";
