import fs from "node:fs";
import https from "node:https";
import crypto from "node:crypto";

const certificate = fs.readFileSync(process.env.MOCK_TLS_CERT);
const privateKey = fs.readFileSync(process.env.MOCK_TLS_KEY);
const expectedIdentity = process.env.MOCK_IDENTITY_REF;
const expectedApiKey = process.env.MOCK_API_KEY;
const expectedProviderToken = process.env.MOCK_PROVIDER_TOKEN;

function json(response, status, body) {
  response.writeHead(status, {
    "content-type": "application/json",
    "cache-control": "no-store",
  });
  response.end(JSON.stringify(body));
}

function readJson(request) {
  return new Promise((resolve, reject) => {
    let body = "";
    request.on("data", (chunk) => {
      body += chunk;
      if (body.length > 1_000_000) request.destroy();
    });
    request.on("end", () => {
      try {
        resolve(body === "" ? {} : JSON.parse(body));
      } catch (error) {
        reject(error);
      }
    });
    request.on("error", reject);
  });
}

const server = https.createServer({ cert: certificate, key: privateKey }, async (request, response) => {
  if (request.headers.authorization !== `Bearer ${expectedProviderToken}`) {
    json(response, 401, { error: "unauthorized" });
    return;
  }

  if (request.method === "GET" && request.url === "/v1/provider/health") {
    json(response, 200, { healthy: true });
    return;
  }

  let payload;
  try {
    payload = await readJson(request);
  } catch {
    json(response, 400, { error: "invalid_json" });
    return;
  }

  if (request.method === "POST" && request.url === "/v1/provider/secrets/verify") {
    const verified = payload.identity_ref === expectedIdentity
      && payload.api_key === expectedApiKey;
    json(response, 200, {
      verified,
      secret_ref: verified ? "secret_ci_smoke" : null,
      verification_ref: verified ? `verification_${crypto.randomUUID()}` : null,
      rationale: verified ? "The CI credential matched." : "The credential could not be verified.",
    });
    return;
  }

  if (request.method === "POST" && request.url === "/v1/provider/security-events") {
    json(response, 201, { security_event_ref: `security_event_${crypto.randomUUID()}` });
    return;
  }

  json(response, 404, { error: "unknown_route" });
});

server.listen(8090, "0.0.0.0");
