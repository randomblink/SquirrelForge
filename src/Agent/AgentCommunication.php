<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use SquirrelForge\Communication\SqliteAgentCommunicator;
use SquirrelForge\Coordination\SqliteMessageBus;

/**
 * Defines the participation rules for how specialist agents exchange
 * information: which agent roles may send and receive which message
 * types, what content each message type must carry to preserve
 * handoff integrity, and role-level priority guidance, per
 * 16_AGENTS/AGENT-COMMUNICATION.md -- the third real component in
 * 16_AGENTS's governance/coordination gap.
 *
 * "It does not implement message transport, routing, queueing,
 * validation, retries, or security" (Purpose) is upheld by owning no
 * database at all -- "communication history, delivery, and
 * acknowledgment are the receiving infrastructure's responsibility,
 * not this document's" (Communication Principles) is a real
 * architectural choice, not an oversight: unlike every other
 * component built for this gap, this class is a pure, stateless
 * validator matching `ProjectSettings`'/`Defaults`' own shape.
 *
 * This spec, `17_COORDINATION/MESSAGE-BUS.md`, and
 * `36_COMMUNICATION/AGENT-COMMUNICATOR.md` each define a genuinely
 * different, real message-type vocabulary for their own layer
 * (Request/Response/Notification/Command/Status/Event/Alert here;
 * Task Assignment/Status Update/... on the Message Bus; goal/
 * task_delegation/... on the Agent Communicator) -- `MESSAGE-BUS.md`'s
 * own text says a pipeline message "may map to one of those general
 * categories for participation-rule purposes while still using its
 * more specific type here," but never defines that mapping itself.
 * Rather than fabricate a three-way translation table this spec never
 * actually gives, `send()` requires the caller to supply the
 * already-correct lower-level type for whichever real delivery layer
 * it selects (`pipeline.message_bus_type` for `SqliteMessageBus`,
 * `agent.agent_communicator_type` for `SqliteAgentCommunicator`) --
 * this class validates and hands off, it does not invent the mapping.
 *
 * "A message type with no defined required content or permitted
 * sender/recipient pair must not be sent as if it were already a
 * defined participation rule" (Inputs) is read literally as a
 * fail-closed requirement: the spec's own Message Types table always
 * defines required content (translated here into concrete required
 * payload keys, since prose alone cannot be automatically checked),
 * but it never populates an actual sender/recipient permission matrix
 * -- only the table *shape* ("Permitted Senders"/"Permitted
 * Recipients" columns exist, no rows). So `permitted_senders`/
 * `permitted_recipients` must be supplied by the caller (representing
 * whatever real participation policy is configured elsewhere); an
 * absent or empty declaration is refused rather than assumed
 * unrestricted, the same fail-closed stance `SqliteAgentDelegation`
 * already takes toward its own `authorized_delegation_types`.
 *
 * Priority maps onto `SqliteMessageBus`'s own real, different Priority
 * vocabulary (`Normal` here -> `Medium` there) when handing off to a
 * pipeline delivery, the same explicit "different owners, different
 * vocabularies" translation `SqliteMessageBus` itself already applies
 * for `36_COMMUNICATION`'s priority set.
 */
final class AgentCommunication
{
    private const MESSAGE_TYPES = ['Request', 'Response', 'Notification', 'Command', 'Status', 'Event', 'Alert'];

    private const PRIORITIES = ['Low', 'Normal', 'High', 'Critical'];

    /** This spec's own Required Content column, translated into concrete, checkable payload keys. */
    private const REQUIRED_CONTENT = [
        'Request' => ['requesting_role', 'requested_action', 'reason'],
        'Response' => ['correlation_ref', 'result'],
        'Notification' => ['change', 'relevance'],
        'Command' => ['instruction', 'scope', 'permission_ref'],
        'Status' => ['current_state', 'change_since_last'],
        'Event' => ['occurrence', 'affected_refs'],
        'Alert' => ['condition', 'severity', 'required_response'],
    ];

    /** Maps this spec's own Priority vocabulary onto SqliteMessageBus's real, different one. */
    private const PRIORITY_TO_MESSAGE_BUS = ['Low' => 'Low', 'Normal' => 'Medium', 'High' => 'High', 'Critical' => 'Critical'];

    public function __construct(
        private readonly ?SqliteMessageBus $messageBus = null,
        private readonly ?SqliteAgentCommunicator $agentCommunicator = null
    ) {
    }

    /**
     * Communication Process steps 1-6.
     *
     * @param array{
     *     sender_role?: ?string,
     *     recipient_role?: ?string,
     *     message_type?: ?string,
     *     content?: array<string, mixed>,
     *     priority?: ?string,
     *     governance_required?: bool,
     *     permitted_senders?: array<int, string>,
     *     permitted_recipients?: array<int, string>,
     *     delivery?: ?string,
     *     pipeline?: array{task_id?: ?string, message_bus_type?: ?string},
     *     agent?: array{source_agent_id?: ?string, destination_agent_id?: ?string, agent_communicator_type?: ?string}
     * } $request
     * @return array{
     *     outcome: string,
     *     message_type: ?string,
     *     priority: ?string,
     *     governance_required: bool,
     *     delivery_result: ?array<string, mixed>,
     *     error: ?string
     * }
     */
    public function send(array $request): array
    {
        $senderRole = $request['sender_role'] ?? null;
        $recipientRole = $request['recipient_role'] ?? null;
        $messageType = $request['message_type'] ?? null;

        if (!$this->present($senderRole) || !$this->present($recipientRole)) {
            return $this->outcome('invalid', $messageType, null, false, null, 'A message requires a non-empty sender_role and recipient_role.');
        }

        if (!is_string($messageType) || !in_array($messageType, self::MESSAGE_TYPES, true)) {
            return $this->outcome('invalid', $messageType, null, false, null, sprintf('"%s" is not one of this spec\'s named Message Types.', (string) ($messageType ?? '')));
        }

        $permittedSenders = $request['permitted_senders'] ?? [];
        $permittedRecipients = $request['permitted_recipients'] ?? [];

        if ($permittedSenders === [] || $permittedRecipients === []) {
            return $this->outcome('rejected', $messageType, null, false, null, 'No participation rule is defined for this message type; permitted_senders and permitted_recipients must be declared, not assumed.');
        }

        if (!in_array($senderRole, $permittedSenders, true)) {
            return $this->outcome('rejected', $messageType, null, false, null, sprintf('"%s" is not a permitted sender for "%s" messages.', $senderRole, $messageType));
        }

        if (!in_array($recipientRole, $permittedRecipients, true)) {
            return $this->outcome('rejected', $messageType, null, false, null, sprintf('"%s" is not a permitted recipient for "%s" messages.', $recipientRole, $messageType));
        }

        $missingContent = array_diff(self::REQUIRED_CONTENT[$messageType], array_keys($request['content'] ?? []));

        if ($missingContent !== []) {
            return $this->outcome('rejected', $messageType, null, false, null, sprintf('Message is missing required content for "%s": %s.', $messageType, implode(', ', $missingContent)));
        }

        $priority = $request['priority'] ?? null;

        if (!is_string($priority) || !in_array($priority, self::PRIORITIES, true)) {
            return $this->outcome('invalid', $messageType, null, false, null, 'A message requires one of this spec\'s named priorities (Low/Normal/High/Critical).');
        }

        $governanceRequired = ($request['governance_required'] ?? false) === true;

        $deliveryResult = $this->deliver($request, $senderRole, $recipientRole, $messageType, $priority);

        return $this->outcome('sent', $messageType, $priority, $governanceRequired, $deliveryResult, null);
    }

    /**
     * @param array<string, mixed> $request
     * @return ?array<string, mixed>
     */
    private function deliver(array $request, string $senderRole, string $recipientRole, string $messageType, string $priority): ?array
    {
        $delivery = $request['delivery'] ?? null;

        if ($delivery === 'pipeline' && $this->messageBus !== null) {
            $pipeline = $request['pipeline'] ?? [];

            return $this->messageBus->send([
                'sender' => $senderRole,
                'recipient' => $recipientRole,
                'message_type' => $pipeline['message_bus_type'] ?? null,
                'task_id' => $pipeline['task_id'] ?? null,
                'priority' => self::PRIORITY_TO_MESSAGE_BUS[$priority],
                'payload' => $request['content'] ?? [],
            ]);
        }

        if ($delivery === 'agent' && $this->agentCommunicator !== null) {
            $agent = $request['agent'] ?? [];

            return $this->agentCommunicator->send(
                $agent['source_agent_id'] ?? '',
                $agent['destination_agent_id'] ?? '',
                $agent['agent_communicator_type'] ?? '',
                $request['content'] ?? []
            );
        }

        return null;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{
     *     outcome: string,
     *     message_type: ?string,
     *     priority: ?string,
     *     governance_required: bool,
     *     delivery_result: ?array<string, mixed>,
     *     error: ?string
     * }
     */
    private function outcome(string $outcome, ?string $messageType, ?string $priority, bool $governanceRequired, ?array $deliveryResult, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'message_type' => $messageType,
            'priority' => $priority,
            'governance_required' => $governanceRequired,
            'delivery_result' => $deliveryResult,
            'error' => $error,
        ];
    }
}
