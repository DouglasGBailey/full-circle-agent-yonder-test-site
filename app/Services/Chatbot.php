<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

/**
 * Fixed-rules hotel chatbot. There are no Artificial Intelligence calls: every
 * reply comes word for word from the Lucid test-bot flows in resources/flows/.
 *
 *   - Visitors who are not signed in walk resources/flows/unregistered.flow.json
 *   - Signed-in guests walk resources/flows/registered.flow.json
 *
 * Node kinds:
 *   start    - the entry; its single edge leads to the welcome menu
 *   menu     - sends its text, and its outgoing edge labels become buttons
 *   input    - sends its text and waits for a typed value (validated here)
 *   decision - not shown; decide() picks the "Yes" or "No" edge
 *   end      - sends its text; the next message starts over
 *
 * The conversation state is a small array the controller keeps in the session:
 *   ['flow' => 'registered', 'node' => 'room_review', 'ctx' => ['room' => 'Standard']]
 */
class Chatbot
{
    /** Typed test values that drive the decisions (documented in the README). */
    public const FULLY_BOOKED_FROM = '12-20';   // 20 Dec to 31 Dec is fully booked

    private array $flows = [];

    public function __construct(private ?string $flowDir = null)
    {
        $this->flowDir ??= resource_path('flows');
    }

    /**
     * Handle one message from the visitor.
     *
     * @return array{messages: list<array{text: string, buttons: list<string>}>, state: array}
     */
    public function handle(string $input, ?User $user, array $state): array
    {
        $flowId = $user ? 'registered' : 'unregistered';
        $flow = $this->flow($flowId);
        $text = trim(preg_replace('/\s+/u', ' ', $input) ?? '');
        $lower = mb_strtolower($text);

        // Signing in or out mid-conversation switches route: start that route fresh.
        if (($state['flow'] ?? null) !== $flowId) {
            $state = ['flow' => $flowId, 'node' => null, 'ctx' => []];
        }
        $state['ctx'] ??= [];
        $ctx = ['first_name' => $user ? $this->firstName($user) : ''] + $state['ctx'];

        // Restart keywords always go back to the welcome menu.
        if (in_array($lower, $flow['meta']['restart_keywords'], true)) {
            return $this->goTo($flow, $this->entryTarget($flow), $state, $ctx, []);
        }

        $node = $state['node'] ? ($flow['nodes'][$state['node']] ?? null) : null;

        // Not started yet, or the last screen was an end screen: say we did not
        // understand and show the welcome menu, so the visitor is never stuck.
        if (! $node || $node['kind'] === 'end') {
            return $this->goTo($flow, $this->entryTarget($flow), $state, $ctx, [$flow['meta']['fallback_text']]);
        }

        if ($node['kind'] === 'menu') {
            foreach ($flow['out'][$node['id']] ?? [] as $edge) {
                if ($edge['label'] !== '' && mb_strtolower($edge['label']) === $lower) {
                    if ($node['id'] === 'room_choice') {
                        $ctx['room'] = $edge['label'];
                    }

                    return $this->goTo($flow, $edge['to'], $state, $ctx, []);
                }
            }

            // Not one of the buttons: fallback text, then the same screen again.
            return $this->goTo($flow, $node['id'], $state, $ctx, [$flow['meta']['fallback_text']]);
        }

        if ($node['kind'] === 'input') {
            $edges = $flow['out'][$node['id']] ?? [];
            if (! $edges || ! $this->validInput($node['id'], $text)) {
                // Invalid value: ask the same question again, in the flow's own words.
                return $this->goTo($flow, $node['id'], $state, $ctx, []);
            }
            $ctx['input_'.$node['id']] = $text;

            return $this->goTo($flow, $edges[0]['to'], $state, $ctx, []);
        }

        return $this->goTo($flow, $this->entryTarget($flow), $state, $ctx, [$flow['meta']['fallback_text']]);
    }

    /** Move to a node, following decisions, and render what the bot says there. */
    private function goTo(array $flow, string $nodeId, array $state, array $ctx, array $before): array
    {
        $messages = array_map(fn ($t) => ['text' => $t, 'buttons' => []], $before);

        for ($hops = 0; $hops < 10; $hops++) {
            $node = $flow['nodes'][$nodeId] ?? throw new RuntimeException("Unknown node {$nodeId}");
            if ($node['kind'] !== 'decision') {
                break;
            }
            $answer = $this->decide($node['id'], $ctx) ? 'yes' : 'no';
            $next = null;
            foreach ($flow['out'][$node['id']] ?? [] as $edge) {
                if (mb_strtolower($edge['label']) === $answer) {
                    $next = $edge['to'];
                }
            }
            $nodeId = $next ?? throw new RuntimeException("Decision {$node['id']} has no {$answer} branch");
        }

        $buttons = [];
        if ($node['kind'] === 'menu') {
            foreach ($flow['out'][$node['id']] ?? [] as $edge) {
                if ($edge['label'] !== '' && ! in_array($edge['label'], $buttons, true)) {
                    $buttons[] = $edge['label'];
                }
            }
        }
        $messages[] = ['text' => $this->fill($node['text'], $ctx), 'buttons' => $buttons];

        // Keep only what later steps need (never the first name; it comes from the account).
        unset($ctx['first_name']);
        $state['node'] = $node['id'];
        $state['ctx'] = $ctx;

        return ['messages' => $messages, 'state' => $state];
    }

    /** The backend checks drawn as decision diamonds in the Lucid flow. */
    private function decide(string $nodeId, array $ctx): bool
    {
        return match ($nodeId) {
            // "Is this email address already registered?"
            'email_check' => User::where('email', mb_strtolower($ctx['input_create_email'] ?? ''))->exists(),
            // "Is a room free for those dates?"
            'room_availability' => $this->roomFree($ctx['input_room_dates'] ?? ''),
            default => throw new RuntimeException("No rule for decision {$nodeId}"),
        };
    }

    private function validInput(string $nodeId, string $text): bool
    {
        if ($text === '' || mb_strlen($text) > 120) {
            return false;
        }

        return match ($nodeId) {
            'create_email' => filter_var($text, FILTER_VALIDATE_EMAIL) !== false,
            'create_name' => (bool) preg_match("/^\p{L}[\p{L}' -]{0,39}$/u", $text),
            'room_dates' => $this->parseDates($text) !== null,
            'table_details' => (bool) preg_match('/\d/', $text),
            default => true,
        };
    }

    /**
     * Read "12 Dec, 2 nights" (also "12 December 2 nights", "Dec 12, 3 nights").
     *
     * @return array{day: int, month: int, nights: int}|null
     */
    public function parseDates(string $text): ?array
    {
        $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
        $t = mb_strtolower($text);
        if (! preg_match('/(\d{1,2})\s*([a-z]{3})[a-z]*|([a-z]{3})[a-z]*\s*(\d{1,2})/', $t, $m)) {
            return null;
        }
        $day = (int) ($m[1] !== '' ? $m[1] : $m[4]);
        $mon = $months[$m[2] !== '' ? $m[2] : ($m[3] ?? '')] ?? null;
        if (! $mon || $day < 1 || $day > 31 || ! checkdate($mon, $day, 2028)) {
            return null;
        }
        $nights = preg_match('/(\d{1,2})\s*night/', $t, $n) ? (int) $n[1] : 1;
        if ($nights < 1 || $nights > 30) {
            return null;
        }

        return ['day' => $day, 'month' => $mon, 'nights' => $nights];
    }

    /** Test rule: 20 to 31 December is fully booked; every other date has rooms. */
    private function roomFree(string $text): bool
    {
        $d = $this->parseDates($text);

        return $d !== null && sprintf('%02d-%02d', $d['month'], $d['day']) < self::FULLY_BOOKED_FROM;
    }

    private function fill(string $text, array $ctx): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => $ctx[$m[1]] ?? $m[0], $text);
    }

    private function firstName(User $user): string
    {
        return explode(' ', trim($user->name))[0] ?: 'guest';
    }

    private function entryTarget(array $flow): string
    {
        return $flow['out'][$flow['entry']][0]['to'];
    }

    /** Load a flow file once and index it by node id and outgoing edges. */
    public function flow(string $flowId): array
    {
        if (isset($this->flows[$flowId])) {
            return $this->flows[$flowId];
        }
        $raw = json_decode((string) file_get_contents("{$this->flowDir}/{$flowId}.flow.json"), true, 32, JSON_THROW_ON_ERROR);
        $nodes = [];
        $entry = null;
        foreach ($raw['nodes'] as $n) {
            $nodes[$n['id']] = $n;
            if ($n['kind'] === 'start') {
                $entry = $n['id'];
            }
        }
        $out = [];
        foreach ($raw['edges'] as $e) {
            $out[$e['from']][] = $e;
        }
        if (! $entry) {
            throw new RuntimeException("Flow {$flowId} has no start node");
        }

        return $this->flows[$flowId] = ['meta' => $raw['meta'], 'nodes' => $nodes, 'out' => $out, 'entry' => $entry];
    }
}
