<?php
// Functional evaluation of the SkyGuard chat assistant: sends each test query
// through the real ChatController as a given user and stores the full trace
// (tool calls + parameters, tool results, final reply, timing) as JSON.
// Usage (from project root): php tools/llm-eval/run.php <reps> [caseId...]
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ChatController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$cases = json_decode(file_get_contents(__DIR__ . '/cases.json'), true);
$reps = (int) ($argv[1] ?? 1);
$only = array_slice($argv, 2);
@mkdir(__DIR__ . '/results');

foreach ($cases as $c) {
    if ($only && !in_array($c['id'], $only, true)) continue;
    $user = User::findOrFail($c['user_id']);
    for ($r = 1; $r <= $reps; $r++) {
        Auth::setUser($user);
        $req = Request::create('/chat', 'POST', ['message' => $c['query']]);
        $req->setUserResolver(fn () => $user);
        app()->instance('request', $req);

        $t0 = microtime(true);
        $resp = app(ChatController::class)->respond($req);
        $secs = round(microtime(true) - $t0, 1);
        $data = json_decode(json_encode($resp->getData(true)), true);

        $calls = [];
        $results = [];
        foreach ($data['history'] ?? [] as $m) {
            if (!is_array($m['content'] ?? null)) continue;
            foreach ($m['content'] as $b) {
                if (($b['type'] ?? '') === 'tool_use') $calls[] = ['name' => $b['name'], 'input' => $b['input'] ?? []];
                if (($b['type'] ?? '') === 'tool_result') $results[] = $b['content'];
            }
        }

        $out = [
            'id' => $c['id'], 'rep' => $r, 'user' => $user->email, 'query' => $c['query'],
            'ran_at' => now()->toDateTimeString(), 'seconds' => $secs, 'status' => $resp->getStatusCode(),
            'tool_calls' => $calls, 'tool_results' => $results,
            'reply' => $data['reply'] ?? ($data['error'] ?? null),
        ];
        file_put_contents(__DIR__ . "/results/{$c['id']}_{$r}.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "{$c['id']} rep{$r} {$secs}s tools=" . implode(',', array_map(fn ($x) => $x['name'] . json_encode($x['input']), $calls)) . "\n";
    }
}
