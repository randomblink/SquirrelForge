<?php

declare(strict_types=1);

namespace SquirrelForge\Learning;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Top-level coordinator for the Learning Layer, per
 * 30_LEARNING/LEARNING-MANAGER.md, mirroring how AutomationManager,
 * KnowledgeManager, and the other Sqlite*Manager coordinators route to
 * their own layer's real specialists.
 *
 * Six of the seven components the spec lists as dependencies are real:
 * submit_feedback routes to Feedback Collector, record_experience to
 * Experience Store, evaluate_experience to Evaluation Engine,
 * detect_recurring_patterns/detect_trend/detect_anomalies to Pattern
 * Detector, review_proposal to Learning Governance, and
 * create_adaptation_plan/execute_adaptation/validate_adaptation to
 * Adaptation Manager. Learning Monitor has no implementation, so there
 * is no operation routing to it -- this class doesn't invent one.
 *
 * This class never re-implements any specialist's logic, only forwards
 * payloads and passes back real results unmodified, upholding the
 * spec's rule that specialist decisions and records remain owned by
 * their canonical components. Like its sibling coordinators, it owns no
 * database -- the spec forbids owning "persistence infrastructure" --
 * only an optional EventBusInterface announcement per operation.
 */
final class LearningManager
{
    private const REQUIRED_FIELDS = [
        'submit_feedback' => ['source_type', 'content'],
        'record_experience' => ['source', 'event_category'],
        'evaluate_experience' => ['experience_id'],
        'detect_recurring_patterns' => [],
        'detect_trend' => ['source', 'event_category'],
        'detect_anomalies' => [],
        'review_proposal' => ['proposal_id'],
        'create_adaptation_plan' => ['proposal_id', 'plan'],
        'execute_adaptation' => ['plan_id', 'context'],
        'validate_adaptation' => ['plan_id', 'result'],
    ];

    public function __construct(
        private readonly ?SqliteFeedbackCollector $feedbackCollector = null,
        private readonly ?SqliteExperienceStore $experienceStore = null,
        private readonly ?SqliteEvaluationEngine $evaluationEngine = null,
        private readonly ?PatternDetector $patternDetector = null,
        private readonly ?SqliteLearningGovernance $governance = null,
        private readonly ?SqliteAdaptationManager $adaptationManager = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever specialist handles this operation
     * @return array{operation: string, target_component: string, outcome: string, error: ?string, result: mixed}
     */
    public function coordinate(string $operation, array $payload, array $options = []): array
    {
        if (!isset(self::REQUIRED_FIELDS[$operation])) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Unknown learning operation "%s".', $operation), null);
        }

        $missingField = $this->firstMissingField($operation, $payload);

        if ($missingField !== null) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Missing required field "%s" for operation "%s".', $missingField, $operation), null);
        }

        return match ($operation) {
            'submit_feedback' => $this->coordinateSubmitFeedback($payload, $options),
            'record_experience' => $this->coordinateRecordExperience($payload, $options),
            'evaluate_experience' => $this->coordinateEvaluateExperience($payload, $options),
            'detect_recurring_patterns' => $this->coordinateDetectRecurringPatterns($payload, $options),
            'detect_trend' => $this->coordinateDetectTrend($payload),
            'detect_anomalies' => $this->coordinateDetectAnomalies($payload, $options),
            'review_proposal' => $this->coordinateReviewProposal($payload, $options),
            'create_adaptation_plan' => $this->coordinateCreateAdaptationPlan($payload),
            'execute_adaptation' => $this->coordinateExecuteAdaptation($payload, $options),
            'validate_adaptation' => $this->coordinateValidateAdaptation($payload, $options),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function firstMissingField(string $operation, array $payload): ?string
    {
        foreach (self::REQUIRED_FIELDS[$operation] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $field;
            }
        }

        return null;
    }

    private function coordinateSubmitFeedback(array $payload, array $options): array
    {
        if ($this->feedbackCollector === null) {
            return $this->finish('submit_feedback', 'feedback_collector', 'rejected', 'Feedback Collector is not configured.', null);
        }

        $result = $this->feedbackCollector->submit($payload['source_type'], $payload['content'], $options);

        return $this->finish('submit_feedback', 'feedback_collector', $result['outcome'], $result['error'], $result);
    }

    private function coordinateRecordExperience(array $payload, array $options): array
    {
        if ($this->experienceStore === null) {
            return $this->finish('record_experience', 'experience_store', 'rejected', 'Experience Store is not configured.', null);
        }

        $result = $this->experienceStore->record($payload['source'], $payload['event_category'], $options);

        return $this->finish('record_experience', 'experience_store', $result['found'] ? 'recorded' : 'rejected', $result['error'], $result);
    }

    private function coordinateEvaluateExperience(array $payload, array $options): array
    {
        if ($this->evaluationEngine === null) {
            return $this->finish('evaluate_experience', 'evaluation_engine', 'rejected', 'Evaluation Engine is not configured.', null);
        }

        $result = $this->evaluationEngine->evaluate($payload['experience_id'], $options);

        return $this->finish('evaluate_experience', 'evaluation_engine', $result['qualification'] ?? 'rejected', $result['error'], $result);
    }

    private function coordinateDetectRecurringPatterns(array $payload, array $options): array
    {
        if ($this->patternDetector === null) {
            return $this->finish('detect_recurring_patterns', 'pattern_detector', 'rejected', 'Experience Store is not configured for Pattern Detector.', null);
        }

        $result = $this->patternDetector->findRecurringPatterns($payload, $options['min_occurrences'] ?? 3);

        return $this->finish('detect_recurring_patterns', 'pattern_detector', $result['outcome'], $result['error'], $result);
    }

    private function coordinateDetectTrend(array $payload): array
    {
        if ($this->patternDetector === null) {
            return $this->finish('detect_trend', 'pattern_detector', 'rejected', 'Experience Store is not configured for Pattern Detector.', null);
        }

        $result = $this->patternDetector->findTrend($payload['source'], $payload['event_category']);

        return $this->finish('detect_trend', 'pattern_detector', $result['outcome'], $result['error'], $result);
    }

    private function coordinateDetectAnomalies(array $payload, array $options): array
    {
        if ($this->patternDetector === null) {
            return $this->finish('detect_anomalies', 'pattern_detector', 'rejected', 'Experience Store is not configured for Pattern Detector.', null);
        }

        $result = $this->patternDetector->findAnomalies($payload, $options['z_score_threshold'] ?? 2.0);

        return $this->finish('detect_anomalies', 'pattern_detector', $result['outcome'], $result['error'], $result);
    }

    private function coordinateReviewProposal(array $payload, array $options): array
    {
        if ($this->governance === null) {
            return $this->finish('review_proposal', 'learning_governance', 'rejected', 'Learning Governance is not configured.', null);
        }

        $result = $this->governance->review($payload['proposal_id'], $options);

        return $this->finish('review_proposal', 'learning_governance', $result['outcome'], null, $result);
    }

    private function coordinateCreateAdaptationPlan(array $payload): array
    {
        if ($this->adaptationManager === null) {
            return $this->finish('create_adaptation_plan', 'adaptation_manager', 'rejected', 'Adaptation Manager is not configured.', null);
        }

        $result = $this->adaptationManager->createPlan($payload['proposal_id'], $payload['plan']);

        return $this->finish('create_adaptation_plan', 'adaptation_manager', $result['found'] ? 'planned' : 'rejected', $result['error'], $result);
    }

    private function coordinateExecuteAdaptation(array $payload, array $options): array
    {
        if ($this->adaptationManager === null) {
            return $this->finish('execute_adaptation', 'adaptation_manager', 'rejected', 'Adaptation Manager is not configured.', null);
        }

        $result = $this->adaptationManager->execute($payload['plan_id'], $payload['context'], $options);

        return $this->finish('execute_adaptation', 'adaptation_manager', $result['found'] ? $result['status'] : 'rejected', $result['error'], $result);
    }

    private function coordinateValidateAdaptation(array $payload, array $options): array
    {
        if ($this->adaptationManager === null) {
            return $this->finish('validate_adaptation', 'adaptation_manager', 'rejected', 'Adaptation Manager is not configured.', null);
        }

        $result = $this->adaptationManager->requestValidation($payload['plan_id'], $payload['result'], $options);

        return $this->finish('validate_adaptation', 'adaptation_manager', $result['found'] ? $result['status'] : 'rejected', $result['error'], $result);
    }

    private function finish(string $operation, string $targetComponent, string $outcome, ?string $error, mixed $result): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'learning.finished',
            new DateTimeImmutable(),
            self::class,
            ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome]
        ));

        return ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome, 'error' => $error, 'result' => $result];
    }
}
