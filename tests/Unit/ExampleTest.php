<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The chatbot's flow files are data the Lucid design was drawn from. Check
 * their shape without booting Laravel: one start node, unique ids, every edge
 * joins two real nodes, and every decision has a Yes and a No branch.
 */
class ExampleTest extends TestCase
{
    public static function flows(): array
    {
        return [['unregistered'], ['registered']];
    }

    #[DataProvider('flows')]
    public function test_flow_file_is_well_formed(string $flowId): void
    {
        $path = dirname(__DIR__, 2)."/resources/flows/{$flowId}.flow.json";
        $flow = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);

        $ids = array_column($flow['nodes'], 'id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'node ids are unique');

        $starts = array_filter($flow['nodes'], fn ($n) => $n['kind'] === 'start');
        $this->assertCount(1, $starts, 'exactly one start node');

        foreach ($flow['edges'] as $e) {
            $this->assertContains($e['from'], $ids, "edge from {$e['from']} exists");
            $this->assertContains($e['to'], $ids, "edge to {$e['to']} exists");
        }

        foreach ($flow['nodes'] as $n) {
            if ($n['kind'] !== 'decision') {
                continue;
            }
            $labels = array_map(
                fn ($e) => strtolower($e['label']),
                array_filter($flow['edges'], fn ($e) => $e['from'] === $n['id'])
            );
            sort($labels);
            $this->assertSame(['no', 'yes'], $labels, "decision {$n['id']} has Yes and No");
        }

        $this->assertNotEmpty($flow['meta']['restart_keywords']);
        $this->assertNotSame('', $flow['meta']['fallback_text']);
    }
}
