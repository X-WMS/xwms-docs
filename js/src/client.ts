export type XwmsResponse = {
  status?: string;
  message?: string;
  data?: any;
  [key: string]: any;
};

export type XwmsClientConfig = {
  apiUri: string;
  clientId: string;
  clientSecret: string;
  domain?: string;
  redirectUri?: string;
  timeoutMs?: number;
  fetch?: typeof fetch;
};

export type RequestOptions = {
  headers?: Record<string, string>;
  noHeaders?: boolean;
};

export class XwmsClient {
  private apiUri: string;
  private clientId: string;
  private clientSecret: string;
  private domain?: string;
  private redirectUri?: string;
  private timeoutMs: number;
  private fetcher: typeof fetch;

  constructor(config: XwmsClientConfig) {
    if (!config?.apiUri) throw new Error("Missing apiUri");
    if (!config?.clientId) throw new Error("Missing clientId");
    if (!config?.clientSecret) throw new Error("Missing clientSecret");
    this.apiUri = config.apiUri;
    this.clientId = config.clientId;
    this.clientSecret = config.clientSecret;
    this.domain = config.domain;
    this.redirectUri = config.redirectUri;
    this.timeoutMs = config.timeoutMs ?? 10000;
    this.fetcher = config.fetch ?? fetch;
  }

  static fromEnv(env: Record<string, string | undefined>): XwmsClient {
    const apiUri = env.XWMS_API_URI || "https://xwms.nl/api/";
    const clientId = env.XWMS_CLIENT_ID || "";
    const clientSecret = env.XWMS_CLIENT_SECRET || "";
    const domain = env.XWMS_DOMAIN || "";
    const redirectUri = env.XWMS_REDIRECT_URI || "";
    return new XwmsClient({
      apiUri,
      clientId,
      clientSecret,
      domain,
      redirectUri,
    });
  }

  private buildHeaders(options?: RequestOptions): Record<string, string> {
    if (options?.noHeaders) return options.headers ?? {};
    return {
      "X-Client-Id": this.clientId,
      "X-Client-Secret": this.clientSecret,
      "X-Client-Domain": this.domain || "",
      Accept: "application/json",
      ...(options?.headers ?? {}),
    };
  }

  private async fetchWithTimeout(url: string, init: RequestInit): Promise<Response> {
    if (!this.timeoutMs) return this.fetcher(url, init);
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);
    try {
      return await this.fetcher(url, { ...init, signal: controller.signal });
    } finally {
      clearTimeout(timer);
    }
  }

  async post(endpoint: string, payload: Record<string, any>, options?: RequestOptions): Promise<XwmsResponse> {
    const url = new URL(endpoint, this.apiUri).toString();
    const body = { ...(payload || {}) };
    if (!body.redirect_url && this.redirectUri) {
      body.redirect_url = this.redirectUri;
    }

    const response = await this.fetchWithTimeout(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...this.buildHeaders(options),
      },
      body: JSON.stringify(body),
    });

    const text = await response.text();
    let json: XwmsResponse;
    try {
      json = JSON.parse(text);
    } catch (err) {
      throw new Error(`Invalid JSON response from ${endpoint}`);
    }
    if (!response.ok) {
      throw new Error(
        `API request to ${endpoint} failed: ${json?.message || response.statusText}`
      );
    }
    return json;
  }

  async get(endpoint: string, query?: Record<string, any>, options?: RequestOptions): Promise<XwmsResponse> {
    const url = new URL(endpoint, this.apiUri);
    Object.entries(query || {}).forEach(([key, value]) => {
      if (value === undefined || value === null) return;
      url.searchParams.set(key, String(value));
    });

    const response = await this.fetchWithTimeout(url.toString(), {
      method: "GET",
      headers: this.buildHeaders(options),
    });

    const text = await response.text();
    let json: XwmsResponse;
    try {
      json = JSON.parse(text);
    } catch (err) {
      throw new Error(`Invalid JSON response from ${endpoint}`);
    }
    if (!response.ok) {
      throw new Error(
        `API request to ${endpoint} failed: ${json?.message || response.statusText}`
      );
    }
    return json;
  }

  async authenticateUser(payload: Record<string, any> = {}): Promise<{ redirectUrl: string; response: XwmsResponse }> {
    const response = await this.post("sign-token", payload);
    const redirectUrl =
      response?.data?.url || response?.redirect_url || response?.data?.redirect_url || "";
    if (!redirectUrl) {
      throw new Error(`Could not get redirect URL from XWMS response.`);
    }
    return { redirectUrl, response };
  }

  async verifyToken(token: string, payload: Record<string, any> = {}): Promise<XwmsResponse> {
    if (!token) throw new Error("Missing token");
    return this.post("sign-token-verify", { token, ...payload });
  }

  async info(): Promise<XwmsResponse> {
    return this.get("info");
  }

  async getUserAddress(sub: string | number, payload: Record<string, any> = {}): Promise<XwmsResponse> {
    return this.post("get/user/address", { sub: Number(sub), ...payload });
  }

  async getUserInfo(sub: string | number, payload: Record<string, any> = {}): Promise<XwmsResponse> {
    return this.post("get/user/info", { sub: Number(sub), ...payload });
  }
}
