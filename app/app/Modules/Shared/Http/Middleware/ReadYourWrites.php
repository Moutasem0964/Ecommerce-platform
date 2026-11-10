<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Services\ReplicaHealth;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReadYourWrites
{
    private array $targets = [];

    public function __construct(private readonly ReplicaHealth $replica)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $userId = auth('api')->id();
        $key = $userId === null ? null : "read-your-writes:user:{$userId}";

        $forcePrimary = ($key !== null && Cache::has($key)) || $this->replica->shouldReadFromPrimary();
        if ($forcePrimary) {
            DB::connection()->useWriteConnectionWhenReading();
        }

        DB::listen(function (QueryExecuted $query) {
            $this->targets[$query->readWriteType === 'read' ? 'replica' : 'primary'] = true;
        });

        $response = $next($request);
        $response->headers->set('X-Read-From', $this->describeTargets());
        $response->headers->set('X-Replica', $this->replica->describe());

        if ($key !== null && ! $request->isMethodSafe() && $response->isSuccessful()) {
            Cache::put($key, true, config('read_your_writes.window_seconds'));
        }

        return $response;
    }

    private function describeTargets(): string
    {
        if ($this->targets === []) {
            return 'none';
        }

        $targets = array_keys($this->targets);
        sort($targets);

        return implode('+', $targets);
    }
}
