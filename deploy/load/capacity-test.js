import http from 'k6/http';
import { check } from 'k6';

const baseUrl = required('SQUIRRELFORGE_CAPACITY_BASE_URL').replace(/\/$/, '');
const identityRef = required('SQUIRRELFORGE_CAPACITY_IDENTITY_REF');
const permissionRef = required('SQUIRRELFORGE_CAPACITY_PERMISSION_REF');
const apiKey = required('SQUIRRELFORGE_CAPACITY_API_KEY');
const virtualUsers = positiveInteger('SQUIRRELFORGE_CAPACITY_VUS', 1);
const duration = __ENV.SQUIRRELFORGE_CAPACITY_DURATION || '1m';
const summaryPath = __ENV.SQUIRRELFORGE_CAPACITY_SUMMARY_PATH || 'capacity-summary.json';

export const options = {
  scenarios: {
    authenticated_workflow: {
      executor: 'constant-vus',
      vus: virtualUsers,
      duration,
      gracefulStop: '30s',
    },
  },
  discardResponseBodies: false,
  noConnectionReuse: false,
};

export function setup() {
  const readiness = http.get(`${baseUrl}/v1/health/providers`, {
    tags: { operation: 'readiness' },
    timeout: '10s',
  });
  const ready = check(readiness, {
    'provider readiness is true': (response) => {
      if (response.status !== 200) return false;
      try {
        return response.json('ready') === true;
      } catch (_) {
        return false;
      }
    },
  });

  if (!ready) {
    throw new Error('Provider readiness failed before the capacity profile.');
  }
}

export default function () {
  const correlationId = `capacity_${__VU}_${__ITER}_${Date.now()}`;
  const commonHeaders = {
    'Content-Type': 'application/json',
    'X-Correlation-ID': correlationId,
  };
  const session = http.post(
    `${baseUrl}/v1/authentication/sessions`,
    JSON.stringify({ identity_ref: identityRef, api_key: apiKey }),
    {
      headers: { ...commonHeaders, 'X-Source-Ref': 'source:capacity-test' },
      tags: { operation: 'authentication' },
      timeout: '10s',
    },
  );

  let accessToken = null;
  try {
    accessToken = session.json('access_token');
  } catch (_) {
    accessToken = null;
  }
  if (!check(session, {
    'session created': (response) => response.status === 201 && typeof accessToken === 'string',
  })) {
    return;
  }

  const engineHeaders = {
    ...commonHeaders,
    Authorization: `Bearer ${accessToken}`,
    'X-SquirrelForge-Identity-Ref': identityRef,
    'X-SquirrelForge-Permission-Ref': permissionRef,
    'Idempotency-Key': correlationId,
  };
  const submission = http.post(
    `${baseUrl}/v1/engine/requests`,
    JSON.stringify({
      request: { goal: 'Run an approved production capacity test workflow.' },
      project_ref: 'project:capacity-test',
    }),
    {
      headers: engineHeaders,
      tags: { operation: 'submission' },
      timeout: '15s',
    },
  );

  let executionRef = null;
  try {
    executionRef = submission.json('execution_ref');
  } catch (_) {
    executionRef = null;
  }
  if (!check(submission, {
    'request accepted': (response) => response.status === 202 && typeof executionRef === 'string',
  })) {
    return;
  }

  const result = http.get(
    `${baseUrl}/v1/engine/executions/${encodeURIComponent(executionRef)}/result`,
    {
      headers: engineHeaders,
      tags: { operation: 'result' },
      timeout: '15s',
    },
  );
  check(result, {
    'validated result returned': (response) => {
      if (response.status !== 200) return false;
      try {
        const decision = response.json('validation_decision');
        return response.json('execution_ref') === executionRef
          && (decision === 'ACCEPTED' || decision === 'ACCEPTED_WITH_LIMITATIONS');
      } catch (_) {
        return false;
      }
    },
  });
}

export function handleSummary(data) {
  return { [summaryPath]: JSON.stringify(data, null, 2) };
}

function required(name) {
  const value = __ENV[name];
  if (typeof value !== 'string' || value.trim() === '') {
    throw new Error(`${name} is required.`);
  }
  return value;
}

function positiveInteger(name, defaultValue) {
  const raw = __ENV[name] || String(defaultValue);
  const value = Number(raw);
  if (!Number.isInteger(value) || value < 1) {
    throw new Error(`${name} must be a positive integer.`);
  }
  return value;
}
