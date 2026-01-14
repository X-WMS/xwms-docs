import { XwmsClient, XwmsResponse } from "./client.js";
import { ImageSyncOptions, syncUserImage } from "./image.js";

export type FieldMap = Record<string, string | ((userData: any, options?: any) => any)>;
export type FieldTransforms = Record<
  string,
  (value: any, userData: any, options?: any) => any
>;

export type UserSyncAdapter<User, Connection> = {
  findConnectionBySub: (sub: string) => Promise<Connection | null>;
  findUserByEmail?: (email: string) => Promise<User | null>;
  createUser: (attributes: Record<string, any>) => Promise<User>;
  updateUser: (user: User, attributes: Record<string, any>) => Promise<User>;
  connectUser: (user: User, sub: string) => Promise<Connection>;
  getSubForUser?: (user: User) => Promise<string | null>;
};

export type UserSyncOptions<User> = {
  fieldMap?: FieldMap;
  fieldTransforms?: FieldTransforms;
  skipNulls?: boolean;
  imageSync?: ImageSyncOptions;
  user?: User | null;
};

export function extractUserData(response: XwmsResponse): Record<string, any> {
  const data = response?.data ?? {};
  if (data && typeof data === "object" && data.user && typeof data.user === "object") {
    return data.user;
  }
  return typeof data === "object" ? data : {};
}

export function defaultFieldMap(): FieldMap {
  return {
    name: "name",
    email: "email",
    picture: "picture",
  };
}

export async function mapUserAttributes<User>(
  userData: Record<string, any>,
  options: UserSyncOptions<User> = {}
): Promise<Record<string, any>> {
  const map = options.fieldMap ?? defaultFieldMap();
  const transforms = options.fieldTransforms ?? {};
  const skipNulls = options.skipNulls ?? true;

  const attributes: Record<string, any> = {};
  for (const [local, source] of Object.entries(map)) {
    let value: any = null;

    if (typeof source === "function") {
      value = source(userData, options);
    } else if (typeof source === "string" && source.length > 0) {
      value = userData[source];
    } else {
      value = userData[local];
    }

    if (transforms[local]) {
      value = transforms[local](value, userData, options);
    }

    if (local === "picture") {
      value = await syncUserImage(
        typeof value === "string" ? value : null,
        (options.user as any)?.picture ?? null,
        options.imageSync
      );
      if (skipNulls && value == null) continue;
      attributes[local] = value;
      continue;
    }

    if (skipNulls && value == null) continue;
    attributes[local] = value;
  }

  if (!attributes.name) {
    const fallback =
      userData.name ||
      `${userData.given_name || ""} ${userData.family_name || ""}`.trim();
    if (fallback) attributes.name = fallback;
  }

  return attributes;
}

export type AuthenticateAndSyncParams<User, Connection> = {
  client: XwmsClient;
  token: string;
  adapter: UserSyncAdapter<User, Connection>;
  authPayload?: Record<string, any>;
  linkByEmail?: boolean;
  createUser?: boolean;
  updateExisting?: boolean;
  options?: UserSyncOptions<User>;
  onLogin?: (user: User, connection: Connection, response: XwmsResponse) => Promise<void> | void;
};

export async function authenticateAndSyncUser<User, Connection>(
  params: AuthenticateAndSyncParams<User, Connection>
): Promise<{
  status: "success" | "error";
  message: string;
  action?: string | null;
  user?: User;
  connection?: Connection;
  response?: XwmsResponse;
  error?: string;
}> {
  try {
    const response = await params.client.verifyToken(params.token, params.authPayload || {});
    if (response?.status !== "success") {
      return { status: "error", message: "Authentication failed.", response };
    }

    const userData = extractUserData(response);
    const sub = userData.sub ? String(userData.sub) : null;
    if (!sub) {
      return { status: "error", message: "Missing sub in XWMS response.", response };
    }

    const adapter = params.adapter;
    let connection = await adapter.findConnectionBySub(sub);
    let user = (connection as any)?.user ?? null;
    let action: string | null = null;

    const linkByEmail = params.linkByEmail ?? true;
    const createUser = params.createUser ?? true;
    const updateExisting = params.updateExisting ?? true;

    if (user) {
      action = "existing_connection";
      if (updateExisting) {
        const attributes = await mapUserAttributes(userData, {
          ...(params.options || {}),
          user,
        });
        user = await adapter.updateUser(user, attributes);
        action = "updated_existing_user";
      }
    } else {
      if (linkByEmail && userData.email && adapter.findUserByEmail) {
        user = await adapter.findUserByEmail(userData.email);
        if (user) {
          const attributes = await mapUserAttributes(userData, {
            ...(params.options || {}),
            user,
          });
          user = await adapter.updateUser(user, attributes);
          action = "linked_by_email";
        }
      }

      if (!user && createUser) {
        const attributes = await mapUserAttributes(userData, params.options || {});
        user = await adapter.createUser(attributes);
        action = "created_user";
      }
    }

    if (!user) {
      return { status: "error", message: "No local user could be resolved or created.", response };
    }

    connection = await adapter.connectUser(user, sub);
    if (params.onLogin) {
      await params.onLogin(user, connection, response);
    }

    return {
      status: "success",
      message: "User authenticated and synchronized.",
      action,
      user,
      connection,
      response,
    };
  } catch (err: any) {
    return {
      status: "error",
      message: "Authentication failed due to a server error.",
      error: err?.message || String(err),
    };
  }
}

export type SyncUserParams<User, Connection> = {
  client: XwmsClient;
  adapter: UserSyncAdapter<User, Connection>;
  user: User;
  options?: UserSyncOptions<User>;
  userInfoPayload?: Record<string, any>;
};

export async function syncAuthenticatedUserFromXwms<User, Connection>(
  params: SyncUserParams<User, Connection>
): Promise<{
  status: "success" | "error";
  message: string;
  user?: User;
  response?: XwmsResponse;
  changes?: Record<string, any>;
  error?: string;
}> {
  try {
    const sub = params.adapter.getSubForUser
      ? await params.adapter.getSubForUser(params.user)
      : null;
    if (!sub) {
      return { status: "error", message: "No XWMS connection found for this user." };
    }

    const response = await params.client.getUserInfo(sub, params.userInfoPayload || {});
    if (response?.status !== "success") {
      return { status: "error", message: "Failed to fetch user data from XWMS.", response };
    }

    const userData = extractUserData(response);
    const attributes = await mapUserAttributes(userData, {
      ...(params.options || {}),
      user: params.user,
    });
    const updated = await params.adapter.updateUser(params.user, attributes);

    return {
      status: "success",
      message: "User updated from XWMS.",
      user: updated,
      response,
      changes: attributes,
    };
  } catch (err: any) {
    return {
      status: "error",
      message: "User sync failed due to a server error.",
      error: err?.message || String(err),
    };
  }
}
