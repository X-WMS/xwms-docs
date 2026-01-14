import fs from "fs";
import path from "path";
import {
  XwmsClient,
  authenticateAndSyncUser,
  syncAuthenticatedUserFromXwms,
} from "./index.js";
import type { ImageSyncOptions } from "./image.js";
import type { UserSyncAdapter } from "./userSync.js";

type ReqLike = any;
type ResLike = any;

export type XwmsExpressOptions<User = any, Connection = any> = {
  client: XwmsClient;
  adapter: UserSyncAdapter<User, Connection>;
  createRouter?: () => any;
  router?: any;
  routes?: {
    login?: string;
    callback?: string;
    me?: string;
    sync?: string;
    avatar?: string;
    logout?: string;
  };
  accountUrl?: string;
  redirectAfterLogin?: string;
  redirectAfterLogout?: string;
  imageSync?: ImageSyncOptions;
  getUserById?: (id: number | string) => Promise<User | null>;
  getSession?: (req: ReqLike) => any;
  onLogin?: (req: ReqLike, res: ResLike, result: any) => Promise<void> | void;
};

export function createXwmsHandlers<User = any, Connection = any>(
  options: XwmsExpressOptions<User, Connection>
) {
  const routes = {
    login: "/login",
    callback: "/xwms/callback",
    me: "/me",
    sync: "/sync",
    avatar: "/avatar",
    logout: "/logout",
    ...(options.routes || {}),
  };

  const getSession = options.getSession || ((req: ReqLike) => req.session);

  async function setSessionUser(req: ReqLike, user: any) {
    const session = getSession(req);
    if (!session) return;
    session.user = user;
    if (typeof session.save === "function") {
      await new Promise<void>((resolve) => session.save(() => resolve()));
    }
  }

  function clearSession(req: ReqLike) {
    const session = getSession(req);
    if (!session) return;
    if (typeof session.destroy === "function") {
      session.destroy(() => {});
      return;
    }
    session.user = null;
  }

  async function getSessionUser(req: ReqLike) {
    const session = getSession(req);
    if (!session?.user) return null;
    if (options.getUserById) {
      const fresh = await options.getUserById(session.user.id);
      return fresh || session.user;
    }
    return session.user;
  }

  async function login(req: ReqLike, res: ResLike) {
    const { redirectUrl } = await options.client.authenticateUser({});
    return res.redirect(redirectUrl);
  }

  async function callback(req: ReqLike, res: ResLike) {
    const token = req.query?.token || req.body?.token || req.query?.access_token;
    if (!token) return res.status(400).send("Missing token");

    const result = await authenticateAndSyncUser({
      client: options.client,
      token: String(token),
      adapter: options.adapter,
      linkByEmail: true,
      createUser: true,
      updateExisting: true,
      options: { imageSync: options.imageSync },
    });

    if (result.status !== "success" || !result.user || !result.connection) {
      return res.status(401).send("Authentication failed");
    }

    await setSessionUser(req, {
      id: result.user.id,
      name: result.user.name,
      email: result.user.email,
      picture: result.user.picture,
      sub: result.connection.sub,
    });

    if (options.onLogin) {
      await options.onLogin(req, res, result);
    }

    return res.redirect(options.redirectAfterLogin || "/dashboard");
  }

  async function me(req: ReqLike, res: ResLike) {
    const user = await getSessionUser(req);
    return res.json({
      user: user
        ? {
            id: user.id,
            name: user.name,
            email: user.email,
            picture: user.picture,
            sub: user.sub,
          }
        : null,
      accountUrl:
        options.accountUrl || "https://xwms.nl/account/general",
    });
  }

  async function sync(req: ReqLike, res: ResLike) {
    const user = await getSessionUser(req);
    if (!user) return res.status(401).json({ error: "not_authenticated" });

    const result = await syncAuthenticatedUserFromXwms({
      client: options.client,
      adapter: options.adapter,
      user,
      options: { imageSync: options.imageSync },
    });

    if (result.status !== "success" || !result.user) {
      return res.status(400).json({ error: "sync_failed", result });
    }

    await setSessionUser(req, {
      id: result.user.id,
      name: result.user.name,
      email: result.user.email,
      picture: result.user.picture,
      sub: user.sub,
    });

    return res.json({ ok: true, user: getSession(req).user });
  }

  async function avatar(req: ReqLike, res: ResLike) {
    const user = await getSessionUser(req);
    if (!user?.picture) return res.status(404).end();

    if (String(user.picture).startsWith("http")) {
      return res.redirect(user.picture);
    }

    const filePath = path.resolve(user.picture);
    if (!fs.existsSync(filePath)) return res.status(404).end();
    return res.sendFile(filePath);
  }

  async function logout(req: ReqLike, res: ResLike) {
    clearSession(req);
    return res.json({ ok: true, redirect: options.redirectAfterLogout || "/" });
  }

  return {
    routes,
    login,
    callback,
    me,
    sync,
    avatar,
    logout,
  };
}

export function createXwmsExpressRouter<User = any, Connection = any>(
  options: XwmsExpressOptions<User, Connection>
) {
  const router = options.router || options.createRouter?.();
  if (!router) {
    throw new Error("createRouter or router is required.");
  }

  const handlers = createXwmsHandlers(options);
  router.get(handlers.routes.login, handlers.login);
  router.get(handlers.routes.callback, handlers.callback);
  router.get(handlers.routes.me, handlers.me);
  router.post(handlers.routes.sync, handlers.sync);
  router.get(handlers.routes.avatar, handlers.avatar);
  router.post(handlers.routes.logout, handlers.logout);

  return router;
}

export function createRequireAuth(options?: { getSession?: (req: ReqLike) => any }) {
  const getSession = options?.getSession || ((req: ReqLike) => req.session);
  return function requireAuth(req: ReqLike, res: ResLike, next: () => void) {
    const session = getSession(req);
    if (!session?.user) {
      return res.status(401).json({ error: "auth_required" });
    }
    return next();
  };
}
