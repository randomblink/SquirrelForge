<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DeploymentPackagingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testProductionEnvironmentTemplateContainsNamesButNoSecretValue(): void
    {
        $environment = $this->contents('deploy/production.env.example');

        $this->assertStringContainsString('SQUIRRELFORGE_ENVIRONMENT=production', $environment);
        $this->assertStringContainsString('SQUIRRELFORGE_CREDENTIAL_PROVIDER=http-json', $environment);
        $this->assertMatchesRegularExpression(
            '/^SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN=$/m',
            $environment
        );
        $this->assertStringNotContainsString('BOOTSTRAP_API_KEY', $environment);
    }

    public function testContainerRunsPreflightAsNonRootAndUsesReadinessHealthCheck(): void
    {
        $dockerfile = $this->contents('deploy/Dockerfile');
        $entrypoint = $this->contents('deploy/entrypoint.sh');

        $this->assertStringContainsString('USER www-data', $dockerfile);
        $this->assertStringContainsString('apk upgrade --no-cache', $dockerfile);
        $this->assertStringContainsString('/v1/health/providers', $dockerfile);
        $this->assertStringContainsString('php /app/bin/runtime-preflight.php', $entrypoint);
        $this->assertStringContainsString('exec php -S 0.0.0.0:8080', $entrypoint);
    }

    public function testCredentialProviderContainerRunsPreflightAsNonRootAndUsesAuthenticatedHealthCheck(): void
    {
        $dockerfile = $this->contents('deploy/Dockerfile.credential-provider');
        $entrypoint = $this->contents('deploy/entrypoint-credential-provider.sh');

        $this->assertStringContainsString('USER www-data', $dockerfile);
        $this->assertStringContainsString('apk upgrade --no-cache', $dockerfile);
        $this->assertStringContainsString('/v1/provider/health', $dockerfile);
        $this->assertStringContainsString('Authorization: Bearer', $dockerfile);
        $this->assertStringContainsString(
            'SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN',
            $dockerfile
        );
        $this->assertStringContainsString(
            'ENTRYPOINT ["/app/deploy/entrypoint-credential-provider.sh"]',
            $dockerfile
        );
        $this->assertStringContainsString('php /app/bin/credential-provider-preflight.php', $entrypoint);
        $this->assertStringContainsString('exec php -S 0.0.0.0:8080', $entrypoint);
        $this->assertStringContainsString('/app/public/credential-provider.php', $entrypoint);
    }

    public function testCredentialProviderGateBuildsSelfContainedTopologyAndBlocksOnSmokeFailure(): void
    {
        $workflow = $this->contents('.github/workflows/flock-deployment-gate.yml');
        $provisioning = $this->contents('bin/provision-smoke-mfa.php');

        foreach ([
            'credential-provider-gate:',
            'docker build --file deploy/Dockerfile.credential-provider',
            'squirrelforge-credential-provider-ci-network',
            'Authorization: Bearer $PROVIDER_TOKEN',
            'http://squirrelforge-ci-credential-provider:8080/v1/provider/health',
            '/app/bin/provision-smoke-mfa.php',
            '/app/bin/credential-provider-smoke-test.php',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        $this->assertStringContainsString(
            "getenv('SQUIRRELFORGE_ALLOW_SMOKE_PROVISIONING') !== '1'",
            $provisioning
        );
        $this->assertStringContainsString('MfaSecretStore', $provisioning);
        $this->assertStringNotContainsString('SqliteIdentityManager', $provisioning);
    }

    public function testCredentialProviderSmokeTestCoversRegisterVerifyRotateMfaAndRevoke(): void
    {
        $smoke = $this->contents('bin/credential-provider-smoke-test.php');

        foreach ([
            '/v1/provider/health',
            '/v1/provider/secrets/register',
            '/v1/provider/secrets/verify',
            '/v1/provider/secrets/rotate',
            '/v1/provider/mfa/verify',
            '/v1/provider/security-events',
            '/v1/provider/secrets/revoke',
            'TotpVerifier',
        ] as $required) {
            $this->assertStringContainsString($required, $smoke);
        }

        $this->assertStringNotContainsString('fwrite(STDOUT, $apiKey', $smoke);
        $this->assertStringNotContainsString('fwrite(STDOUT, $mfaSecret', $smoke);
        $this->assertStringNotContainsString('fwrite(STDOUT, $providerToken', $smoke);
    }

    public function testCredentialProviderPublicationUsesVerifiedDigestWithSupplyChainEvidence(): void
    {
        $workflow = $this->contents('.github/workflows/flock-deployment-gate.yml');

        foreach ([
            'publish-credential-provider:',
            'needs: credential-provider-gate',
            'ghcr.io/${REPOSITORY,,}/credential-provider',
            'sbom-path: squirrelforge-credential-provider.cdx.json',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        $this->assertSame(
            4,
            substr_count($workflow, 'aquasecurity/trivy-action@ed142fd0673e97e23eac54620cfb913e5ce36c25')
        );
        $this->assertSame(
            2,
            substr_count($workflow, 'sigstore/cosign-installer@6f9f17788090df1f26f669e9d70d6ae9567deba6')
        );
        $this->assertSame(
            4,
            substr_count($workflow, 'actions/attest@59d89421af93a897026c735860bf21b6eb4f7b26')
        );

        // The original protected-rollout job (main Engine API image only)
        // must still never reference the credential-provider image or its
        // distinctly-named vars/secrets -- the two releases stay fully
        // independent even though both now have a staged-rollout job.
        $rolloutStart = (int) strpos($workflow, 'protected-rollout:');
        $rolloutEnd = (int) strpos($workflow, 'credential-provider-gate:');
        $protectedRolloutSection = substr($workflow, $rolloutStart, $rolloutEnd - $rolloutStart);
        $this->assertStringNotContainsString('credential-provider', $protectedRolloutSection);
    }

    public function testCredentialProviderProtectedRolloutPromotesIndependentlyOfMainImage(): void
    {
        $workflow = $this->contents('.github/workflows/flock-deployment-gate.yml');

        foreach ([
            'protected-rollout-credential-provider:',
            'needs: publish-credential-provider',
            'environment: production',
            'squirrelforge-credential-provider-production-release',
            'cancel-in-progress: false',
            'runs-on: [self-hosted, linux, squirrelforge-production]',
            'secrets.SQUIRRELFORGE_KUBECONFIG_BASE64',
            'bin/verify-release-image.sh',
            'bin/rollout-release.sh',
            'bin/observe-release.sh',
            'bin/rollback-release.sh',
            'SQUIRRELFORGE_ADMISSION_RECEIPT_PATH: credential-provider-admission-receipt.json',
            'SQUIRRELFORGE_ROLLOUT_RECEIPT_PATH: credential-provider-rollout-receipt.json',
            'SQUIRRELFORGE_OBSERVE_RECEIPT_PATH: credential-provider-observation-receipt.json',
            'SQUIRRELFORGE_ROLLBACK_RECEIPT_PATH: credential-provider-rollback-receipt.json',
            'credential-provider-admission-receipt.json',
            'credential-provider-rollout-receipt.json',
            'credential-provider-observation-receipt.json',
            'credential-provider-rollback-receipt.json',
            'squirrelforge-credential-provider-production-rollout-',
            "if: steps.observation.outcome == 'failure'",
            'retention-days: 90',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        // Every rollout/observe var it feeds bin/rollout-release.sh and
        // bin/observe-release.sh with must come from the distinctly
        // prefixed vars/secrets, never the main image's own unprefixed
        // SQUIRRELFORGE_ROLLOUT_*/SQUIRRELFORGE_OBSERVE_* GitHub names --
        // otherwise the two releases could silently collide.
        $sectionStart = (int) strpos($workflow, 'protected-rollout-credential-provider:');
        $this->assertGreaterThan(0, $sectionStart);
        $section = substr($workflow, $sectionStart);

        foreach ([
            'vars.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_NAMESPACE',
            'vars.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_STABLE_DEPLOYMENT',
            'vars.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_CANARY_DEPLOYMENT',
            'vars.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_CONTAINER',
            'secrets.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_CANARY_READINESS_URL',
            'secrets.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_STABLE_READINESS_URL',
            'secrets.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_CANARY_ERROR_RATE_URL',
            'secrets.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_STABLE_ERROR_RATE_URL',
            'vars.SQUIRRELFORGE_CREDENTIAL_PROVIDER_ROLLOUT_MAXIMUM_ERROR_RATE_PERCENT',
            'vars.SQUIRRELFORGE_CREDENTIAL_PROVIDER_OBSERVE_SAMPLE_COUNT',
            'vars.SQUIRRELFORGE_CREDENTIAL_PROVIDER_OBSERVE_INTERVAL_SECONDS',
        ] as $required) {
            $this->assertStringContainsString($required, $section);
        }

        foreach ([
            'vars.SQUIRRELFORGE_ROLLOUT_NAMESPACE',
            'vars.SQUIRRELFORGE_ROLLOUT_STABLE_DEPLOYMENT',
            'vars.SQUIRRELFORGE_ROLLOUT_CANARY_DEPLOYMENT',
            'vars.SQUIRRELFORGE_ROLLOUT_CONTAINER',
            'vars.SQUIRRELFORGE_ROLLOUT_MAXIMUM_ERROR_RATE_PERCENT',
            'vars.SQUIRRELFORGE_OBSERVE_SAMPLE_COUNT',
            'vars.SQUIRRELFORGE_OBSERVE_INTERVAL_SECONDS',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $section);
        }
    }

    public function testSmokeTestCoversReadinessAuthenticationSubmissionAndResult(): void
    {
        $smoke = $this->contents('bin/production-smoke-test.php');

        foreach ([
            '/v1/health/providers',
            '/v1/authentication/sessions',
            '/v1/engine/requests',
            '/result',
            'validation_decision',
        ] as $required) {
            $this->assertStringContainsString($required, $smoke);
        }

        $this->assertStringNotContainsString("fwrite(STDOUT, \$apiKey", $smoke);
        $this->assertStringNotContainsString("fwrite(STDOUT, \$sessionBody", $smoke);
    }

    public function testDeploymentCommandsAreRegisteredWithComposer(): void
    {
        $composer = json_decode(
            $this->contents('composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('php bin/runtime-preflight.php', $composer['scripts']['runtime:preflight']);
        $this->assertSame('php bin/credential-provider-smoke-test.php', $composer['scripts']['smoke:credential-provider']);
        $this->assertSame('php bin/production-smoke-test.php', $composer['scripts']['smoke:production']);
        $this->assertSame('sh bin/verify-release-image.sh', $composer['scripts']['admission:verify']);
        $this->assertSame('php bin/backup-engine.php', $composer['scripts']['backup:engine']);
        $this->assertSame('php bin/evaluate-capacity-result.php', $composer['scripts']['capacity:evaluate']);
        $this->assertSame('k6 run deploy/load/capacity-test.js', $composer['scripts']['capacity:run']);
        $this->assertSame('sh bin/check-production-health.sh', $composer['scripts']['health:production']);
        $this->assertSame('php bin/evaluate-launch-readiness.php', $composer['scripts']['launch:evaluate']);
        $this->assertSame('sh bin/rollout-release.sh', $composer['scripts']['rollout:release']);
        $this->assertSame('sh bin/observe-release.sh', $composer['scripts']['observe:release']);
        $this->assertSame('sh bin/rollback-release.sh', $composer['scripts']['rollback:release']);
        $this->assertSame('php bin/restore-engine-backup.php', $composer['scripts']['restore:engine']);
        $this->assertSame('php bin/verify-engine-backup.php', $composer['scripts']['verify:backup']);
    }

    public function testDeploymentGateBuildsTlsTopologyAndBlocksOnSmokeFailure(): void
    {
        $workflow = $this->contents('.github/workflows/flock-deployment-gate.yml');
        $mockProvider = $this->contents('deploy/mock-provider/server.mjs');
        $provisioning = $this->contents('bin/provision-smoke-identity.php');

        foreach ([
            'docker build --file deploy/Dockerfile',
            'docker build --file deploy/mock-provider/Dockerfile',
            'openssl req -x509',
            '--network-alias mock-provider',
            'SQUIRRELFORGE_ENVIRONMENT=production',
            'https://mock-provider:8090/v1/provider/health',
            '--cacert /certs/provider.crt',
            'Authorization: Bearer $PROVIDER_TOKEN',
            '/v1/health/providers',
            '/app/bin/production-smoke-test.php',
            'trap cleanup EXIT',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        $this->assertStringContainsString('https.createServer', $mockProvider);
        $this->assertStringContainsString('/v1/provider/secrets/verify', $mockProvider);
        $this->assertStringContainsString('/v1/provider/security-events', $mockProvider);
        $this->assertStringContainsString(
            "getenv('SQUIRRELFORGE_ALLOW_SMOKE_PROVISIONING') !== '1'",
            $provisioning
        );
        $this->assertStringNotContainsString('MOCK_API_KEY:', $workflow);
        $this->assertStringNotContainsString('MOCK_PROVIDER_TOKEN:', $workflow);
    }

    public function testReleasePublicationUsesOnlyVerifiedDigestWithSupplyChainEvidence(): void
    {
        $workflow = $this->contents('.github/workflows/flock-deployment-gate.yml');

        foreach ([
            'format: cyclonedx',
            'severity: CRITICAL,HIGH',
            'docker save squirrelforge-ci',
            "docker image inspect --format='{{.Id}}' squirrelforge-ci",
            'needs: deployment-gate',
            'docker push "$image_ref:$RELEASE_TAG"',
            'cosign sign --yes "$IMAGE_REF@$DIGEST"',
            'Attach build provenance',
            'sbom-path: squirrelforge.cdx.json',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        $this->assertMatchesRegularExpression(
            '/aquasecurity\\/trivy-action@[a-f0-9]{40}/',
            $workflow
        );
        $this->assertMatchesRegularExpression(
            '/sigstore\\/cosign-installer@[a-f0-9]{40}/',
            $workflow
        );
        $this->assertMatchesRegularExpression(
            '/actions\\/attest@[a-f0-9]{40}/',
            $workflow
        );
        $this->assertStringNotContainsString('aquasecurity/trivy-action@v', $workflow);
        $this->assertStringNotContainsString('sigstore/cosign-installer@v', $workflow);
        $this->assertStringNotContainsString('actions/attest@v', $workflow);
    }

    public function testProtectedReleaseRequiresApprovalObservationRollbackAndReceipts(): void
    {
        $workflow = $this->contents('.github/workflows/flock-deployment-gate.yml');

        foreach ([
            'protected-rollout:',
            'environment: production',
            'squirrelforge-production-release',
            'cancel-in-progress: false',
            'bin/rollout-release.sh',
            'bin/observe-release.sh',
            'bin/rollback-release.sh',
            'SQUIRRELFORGE_ADMISSION_RECEIPT_PATH: admission-receipt.json',
            'admission-receipt.json',
            "if: steps.observation.outcome == 'failure'",
            'rollout-receipt.json',
            'observation-receipt.json',
            'rollback-receipt.json',
            'retention-days: 90',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
    }

    public function testProductionRunbookRequiresOffsiteVerifiedRestoreDrills(): void
    {
        $runbook = $this->contents('deploy/BACKUP-RECOVERY.md');

        foreach ([
            'recovery point objective (RPO)',
            'recovery time objective (RTO)',
            'separate failure domain',
            'composer backup:engine',
            'composer verify:backup',
            'composer restore:engine',
            'runtime:preflight',
            'production smoke test',
            'end-to-end restore drill',
        ] as $required) {
            $this->assertStringContainsString($required, $runbook);
        }
    }

    public function testObservabilityRunbookDefinesSignalsAlertsAndIncidentBoundaries(): void
    {
        $runbook = $this->contents('deploy/OBSERVABILITY.md');
        $policy = $this->contents('deploy/observability-policy.env.example');

        foreach ([
            'Service-level indicators',
            'Error-budget windows',
            'IMAGE_DRIFT',
            'BACKUP_STALE',
            'Alert routing',
            'Incident procedure',
            'Diagnostic boundaries',
            'deploy/ROLLOUT.md',
            'deploy/BACKUP-RECOVERY.md',
        ] as $required) {
            $this->assertStringContainsString($required, $runbook);
        }

        foreach ([
            'SQUIRRELFORGE_MONITOR_EXPECTED_IMAGE=',
            'SQUIRRELFORGE_MONITOR_READINESS_URL=',
            'SQUIRRELFORGE_MONITOR_ERROR_RATE_URL=',
            'SQUIRRELFORGE_MONITOR_BACKUP_AGE_URL=',
        ] as $required) {
            $this->assertStringContainsString($required, $policy);
        }
    }

    public function testCapacityContractDefinesLoadScalingAndFailureBoundaries(): void
    {
        $runbook = $this->contents('deploy/CAPACITY-RESILIENCE.md');
        $profile = $this->contents('deploy/load/capacity-test.js');
        $policy = $this->contents('deploy/capacity-policy.env.example');

        foreach ([
            'single-process development server',
            'do not horizontally scale',
            'SQLite serializes writers',
            'Baseline:',
            'Target:',
            'Stress:',
            'Soak:',
            'Failure-injection matrix',
            'Persistent-volume exhaustion',
            'Abort immediately',
            'deploy/BACKUP-RECOVERY.md',
        ] as $required) {
            $this->assertStringContainsString($required, $runbook);
        }

        foreach ([
            '/v1/health/providers',
            '/v1/authentication/sessions',
            '/v1/engine/requests',
            '/result',
            'Idempotency-Key',
            'handleSummary',
        ] as $required) {
            $this->assertStringContainsString($required, $profile);
        }

        $this->assertMatchesRegularExpression(
            '/^SQUIRRELFORGE_CAPACITY_API_KEY=$/m',
            $policy
        );
        $this->assertStringNotContainsString('API_KEY=capacity', $policy);
    }

    public function testLaunchGateConsolidatesChecksummedEvidenceAndOwnership(): void
    {
        $runbook = $this->contents('deploy/LAUNCH-READINESS.md');
        $manifest = $this->contents('deploy/launch-evidence.example.json');

        foreach ([
            'admission receipt',
            'rollout receipt',
            'post-deployment observation',
            'production-health receipt',
            'verified backup',
            'restore-drill receipt',
            'capacity receipt',
            'checksum-bound launch decision receipt',
            'READY',
            'NOT_READY',
            'Operator checklist',
        ] as $required) {
            $this->assertStringContainsString($required, $runbook);
        }

        foreach ([
            '"approval_ref"',
            '"candidate_image"',
            '"rollback_image"',
            '"release"',
            '"on_call"',
            '"recovery"',
            '"security"',
            '"admission"',
            '"capacity"',
        ] as $required) {
            $this->assertStringContainsString($required, $manifest);
        }
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
