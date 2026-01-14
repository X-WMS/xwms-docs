import crypto from "crypto";
import fs from "fs";
import path from "path";

export type ImageSyncOptions = {
  enabled?: boolean;
  directory?: string;
  deleteOld?: boolean;
  timeoutMs?: number;
  maxBytes?: number;
  baseUrl?: string;
  storage?: FileStorage;
};

export type FileStorage = {
  save: (relativePath: string, data: Uint8Array) => Promise<void>;
  remove: (relativePath: string) => Promise<void>;
  exists: (relativePath: string) => Promise<boolean>;
};

export function isRemoteUrl(value: string): boolean {
  return /^https?:\/\//i.test(value) || value.startsWith("//");
}

export function extensionFromContentType(contentType: string): string {
  const normalized = String(contentType || "").split(";")[0].trim().toLowerCase();
  if (normalized === "image/png") return "png";
  if (normalized === "image/gif") return "gif";
  if (normalized === "image/webp") return "webp";
  if (normalized === "image/svg+xml") return "svg";
  if (normalized === "image/jpeg" || normalized === "image/jpg") return "jpg";
  return "jpg";
}

export function createNodeStorage(rootDir: string): FileStorage {
  const base = rootDir;
  return {
    async save(relativePath, data) {
      const full = path.resolve(base, relativePath);
      await fs.promises.mkdir(path.dirname(full), { recursive: true });
      await fs.promises.writeFile(full, data);
    },
    async remove(relativePath) {
      const full = path.resolve(base, relativePath);
      if (fs.existsSync(full)) {
        await fs.promises.unlink(full);
      }
    },
    async exists(relativePath) {
      const full = path.resolve(base, relativePath);
      return fs.existsSync(full);
    },
  };
}

export async function downloadRemoteImage(url: string, options: ImageSyncOptions = {}): Promise<string | null> {
  const normalized = url.startsWith("//") ? `https:${url}` : url;
  const storage = options.storage;
  if (!storage) return null;

  const controller = new AbortController();
  const timeout = options.timeoutMs ?? 10000;
  const timer = setTimeout(() => controller.abort(), timeout);

  try {
    const response = await fetch(normalized, { signal: controller.signal });
    if (!response.ok) return null;
    const contentType = response.headers.get("content-type") || "";
    const ext = extensionFromContentType(contentType);
    const buffer = new Uint8Array(await response.arrayBuffer());
    if (options.maxBytes && buffer.byteLength > options.maxBytes) {
      return null;
    }
    const filename = `xwms_${crypto.randomUUID()}.${ext}`;
    const directory = options.directory ? options.directory.replace(/\\/g, "/").replace(/\/+$/g, "") : "";
    const relativePath = directory ? `${directory}/${filename}` : filename;
    await storage.save(relativePath, buffer);
    return relativePath;
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}

export async function syncUserImage(
  value: string | null | undefined,
  currentPath: string | null | undefined,
  options: ImageSyncOptions = {}
): Promise<string | null> {
  if (!options.enabled) return value ?? null;
  const storage = options.storage;
  if (!value || !isRemoteUrl(value)) {
    if (options.deleteOld && currentPath && storage) {
      await storage.remove(currentPath);
    }
    return value ?? null;
  }
  const stored = await downloadRemoteImage(value, options);
  if (!stored) return currentPath ?? null;
  if (options.deleteOld && currentPath && storage && currentPath !== stored) {
    await storage.remove(currentPath);
  }
  return stored;
}
