<?php

declare(strict_types=1);

use SquirrelForge\Engine\SqliteEngineRuntime;
use SquirrelForge\Contracts\ProviderHealthInterface;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Core\Kernel;
use SquirrelForge\Engine\TaskRouter;
use SquirrelForge\Engine\WorkflowSelector;
use SquirrelForge\Integration\Http\AgentApiServer;
use SquirrelForge\Integration\Http\AuthenticationApiServer;
use SquirrelForge\Integration\Http\CredentialAdministrationApiServer;
use SquirrelForge\Integration\Http\DashboardApiServer;
use SquirrelForge\Integration\Http\EngineApiServer;
use SquirrelForge\Integration\Http\MemoryApiServer;
use SquirrelForge\Integration\Http\NativeHttpTransport;
use SquirrelForge\Integration\Http\ProviderReadinessApiServer;
use SquirrelForge\Integration\Http\WorkflowApiServer;
use SquirrelForge\Memory\EpisodicMemory;
use SquirrelForge\Memory\FileMemoryStore;
use SquirrelForge\Memory\MemoryManager;
use SquirrelForge\Memory\MemoryRetrieval;
use SquirrelForge\Memory\ProjectMemory;
use SquirrelForge\Memory\SemanticMemory;
use SquirrelForge\Memory\SqliteMemoryIndex;
use SquirrelForge\Memory\SqliteMemoryRetention;
use SquirrelForge\Memory\WorkingMemory;
use SquirrelForge\RuntimeConfig\CredentialProviderLoader;
use SquirrelForge\RuntimeConfig\RuntimeEnvironmentPolicy;
use SquirrelForge\RuntimeConfig\ProviderStartupValidator;
use SquirrelForge\RuntimeConfig\ResilientHttpTransport;
use SquirrelForge\RuntimeConfig\SqliteProviderTelemetry;
use SquirrelForge\Security\ApiKeySessionIssuer;
use SquirrelForge\Security\AuthenticationThrottlePolicy;
use SquirrelForge\Security\SqliteAuthenticationManager;
use SquirrelForge\Security\SqliteAuthorizationManager;
use SquirrelForge\Security\SqliteIdentityManager;
use SquirrelForge\Security\SqliteSecurityEventSink;

require dirname(__DIR__) . '/vendor/autoload.php';

$databasePath = getenv('SQUIRRELFORGE_ENGINE_DB');

if ($databasePath === false || trim($databasePath) === '') {
    $databasePath = dirname(__DIR__) . '/var/engine.sqlite';
}

$runtime = new SqliteEngineRuntime($databasePath);
$identities = new SqliteIdentityManager($databasePath);
$environment = RuntimeEnvironmentPolicy::fromEnvironment();
$providerTelemetry = new SqliteProviderTelemetry($databasePath);
$providerTransport = new ResilientHttpTransport(
    new NativeHttpTransport(),
    max(1, (int) (getenv('SQUIRRELFORGE_PROVIDER_MAX_ATTEMPTS') ?: 3)),
    max(1, (int) (getenv('SQUIRRELFORGE_PROVIDER_CIRCUIT_FAILURES') ?: 3)),
    max(1, (int) (getenv('SQUIRRELFORGE_PROVIDER_CIRCUIT_COOLDOWN_SECONDS') ?: 30)),
    $providerTelemetry
);
$providers = (new CredentialProviderLoader($environment, $providerTransport))->load($databasePath);
$securityEvents = $providers->securityEvents;
$authentication = new SqliteAuthenticationManager($databasePath, $identities, $securityEvents);
$authorization = new SqliteAuthorizationManager($databasePath);
$secrets = $providers->secrets;
$mfa = $providers->mfa;
(new ProviderStartupValidator($environment))->validate($secrets, $mfa, $securityEvents);
$throttle = AuthenticationThrottlePolicy::fromEnvironment();
$sessionIssuer = new ApiKeySessionIssuer(
    $databasePath,
    $identities,
    $secrets,
    $authentication,
    $throttle->maximumFailures,
    $throttle->failureWindowSeconds,
    $throttle->lockoutSeconds,
    $mfa,
    $securityEvents
);
$bootstrapIdentity = getenv('SQUIRRELFORGE_BOOTSTRAP_IDENTITY_REF');
$bootstrapPermission = getenv('SQUIRRELFORGE_BOOTSTRAP_PERMISSION_REF');
$bootstrapApiKey = getenv('SQUIRRELFORGE_BOOTSTRAP_API_KEY');

if ($environment->allowsBootstrapProvisioning()
    && is_string($bootstrapIdentity) && trim($bootstrapIdentity) !== ''
    && is_string($bootstrapPermission) && trim($bootstrapPermission) !== ''
    && is_string($bootstrapApiKey) && trim($bootstrapApiKey) !== '') {
    $identities->provision($bootstrapIdentity, 'SERVICE');
    $secrets->registerApiKey($bootstrapIdentity, $bootstrapApiKey);
    $authorization->issueGrant(
        $bootstrapPermission,
        $bootstrapIdentity,
        [
            'engine.submit',
            'engine.inspect',
            'engine.provide_input',
            'engine.cancel',
            'engine.result',
            'security.credentials.rotate',
            'security.credentials.revoke',
            'security.sessions.revoke',
            'observability.dashboard.read',
            'agent.assign',
        ]
    );
}

$server = new EngineApiServer($runtime, $authorization, $authentication);
$authenticationServer = new AuthenticationApiServer($sessionIssuer, $authentication);
$credentialAdministrationServer = new CredentialAdministrationApiServer(
    $secrets,
    $authentication,
    $authentication,
    $authorization,
    $securityEvents
);
$providerReadinessServer = new ProviderReadinessApiServer(
    $providers->secrets instanceof ProviderHealthInterface ? $providers->secrets : null,
    $providerTelemetry
);
$dashboardServer = new DashboardApiServer(
    $providers->secrets instanceof ProviderHealthInterface ? $providers->secrets : null,
    $providerTelemetry,
    $securityEvents instanceof SqliteSecurityEventSink ? $securityEvents : null,
    $runtime,
    $authorization,
    $authentication
);

$memoryVarDirectory = dirname($databasePath) . '/memory';
$episodicStore = new FileMemoryStore($memoryVarDirectory . '/episodic.json');
$episodicStore->boot();
$semanticStore = new FileMemoryStore($memoryVarDirectory . '/semantic.json');
$semanticStore->boot();
$projectStore = new FileMemoryStore($memoryVarDirectory . '/project.json');
$projectStore->boot();
$episodicMemory = new EpisodicMemory($episodicStore);
$semanticMemory = new SemanticMemory($semanticStore);
$projectMemory = new ProjectMemory($projectStore);
$workingMemory = new WorkingMemory($episodicMemory);
$memoryIndex = new SqliteMemoryIndex($databasePath);
$memoryRetrieval = new MemoryRetrieval($memoryIndex, $workingMemory, $episodicMemory, $semanticMemory, $projectMemory);
$memoryRetention = new SqliteMemoryRetention($databasePath, $memoryIndex);
$memoryManager = new MemoryManager($workingMemory, $episodicMemory, $semanticMemory, $projectMemory, $memoryIndex, $memoryRetrieval, $memoryRetention);
$memoryServer = new MemoryApiServer($memoryManager, $memoryRetrieval, $memoryRetention, $authorization, $authentication);

$kernel = new Kernel();
$kernel->boot();
$agentServer = new AgentApiServer(
    new TaskRouter($kernel->app()->container()->make(AgentRegistry::class)),
    $authorization
);
$workflowServer = new WorkflowApiServer(new WorkflowSelector(dirname(__DIR__) . '/02_WORKFLOWS'), $authorization);
$headers = [];

foreach (getallheaders() as $name => $value) {
    if (is_string($name) && is_string($value)) {
        $headers[$name] = $value;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$body = file_get_contents('php://input') ?: null;
$response = match (true) {
    str_starts_with($path, '/v1/authentication/') =>
        $authenticationServer->handle($method, $path, $headers, $body),
    str_starts_with($path, '/v1/security/') =>
        $credentialAdministrationServer->handle($method, $path, $headers, $body),
    $path === '/v1/health/providers' =>
        $providerReadinessServer->handle($method, $path),
    $path === '/v1/admin/dashboard' =>
        $dashboardServer->handle($method, $path, $headers, $body),
    str_starts_with($path, '/v1/memory/') =>
        $memoryServer->handle($method, $path, $headers, $body),
    str_starts_with($path, '/v1/agents/') =>
        $agentServer->handle($method, $path, $headers, $body),
    str_starts_with($path, '/v1/workflows/') =>
        $workflowServer->handle($method, $path, $headers, $body),
    default => $server->handle($method, $path, $headers, $body),
};

http_response_code($response->status);

foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}

echo $response->body;
