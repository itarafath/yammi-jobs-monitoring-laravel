<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Job\Repository\PendingJobRepository;

/**
 * Fetches a single pending job's full details via PendingJobRepository.
 *
 * Works with both Horizon (Redis) and database queue drivers.
 */
final class PendingJobDetailAction
{
    public function __construct(
        private readonly PendingJobRepository $repository,
    )
    {
    }

    /**
     * @return array{id: string, name: string, short_name: string, queue: string, connection: string, tags: list<string>, pushed_at: float|null, delayed: bool, delayed_until: string|null, status: string, payload: object|null, pretty_payload: object|null, data: mixed}|null
     */
    public function __invoke(string $jobId): ?array
    {
        if (!$this->repository->isAvailable()) {
            return null;
        }

        try {
            $job = $this->repository->getJobById($jobId);

            if ($job === null) {
                return null;
            }

            $payload = $job->payload ?? null;
            if (is_string($payload)) {
                $payload = json_decode($payload);
            }

            $name = $job->name ?? $payload->displayName ?? 'Unknown';
            $tags = is_array($job->tags) ? $job->tags : ($payload->tags ?? []);
            $pushedAt = $job->pushed_at ?? $payload->pushedAt ?? null;

            // Detect delayed jobs and compute delayed-until time
            $delayed = false;
            $delayedUntil = null;
            $unserialized = null;

            if (isset($payload->data->command)) {
                try {
                    $unserialized = @unserialize($payload->data->command);
                } catch (\Throwable) {
                    // not a serialized PHP object
                }
            }

            if ($unserialized && property_exists($unserialized, 'delay') && $unserialized->delay) {
                $delayed = true;

                if (is_object($unserialized->delay) && property_exists($unserialized->delay, 'date')) {
                    $delayedUntil = $unserialized->delay->date;
                } elseif (is_numeric($unserialized->delay)) {
                    $delayedUntil = \Carbon\Carbon::createFromTimestampUTC((float)$pushedAt)
                        ->addSeconds((int)$unserialized->delay)
                        ->format('Y-m-d H:i:s');
                }
            }

            // Convert to clean display-friendly array (like Horizon's prettyPrintJob)
            $data = $payload->data ?? null;
            if ($unserialized !== null && $unserialized !== false) {
                $data = $this->normalizeUnserialized($unserialized);
            }

            // Build a "pretty" version of the full payload where data.command
            // is replaced with its unserialized/decoded form.
            $prettyPayload = $payload;
            if ($payload !== null && $unserialized !== null && $unserialized !== false) {
                $prettyPayload = clone $payload;
                $prettyPayload->data = clone($payload->data ?? (object)[]);
                $prettyPayload->data->command = $this->normalizeUnserialized($unserialized);
            }

            return [
                'id' => $job->id ?? $jobId,
                'name' => $name,
                'short_name' => $this->shortClass($name),
                'queue' => $job->queue ?? '',
                'connection' => $job->connection ?? '',
                'tags' => is_array($tags) ? $tags : [],
                'pushed_at' => $pushedAt !== null ? (float)$pushedAt : null,
                'delayed' => $delayed,
                'delayed_until' => $delayedUntil,
                'status' => $job->status ?? 'pending',
                'payload' => $payload,
                'pretty_payload' => $prettyPayload,
                'data' => $data,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert a PHP-unserialized job object into a clean key=>value array
     * matching Horizon's prettyPrintJob output.
     *
     * Laravel serialises queue jobs via CallQueuedHandler — the `args`
     * property is often **private** (null-byte prefixed in the serialized
     * string), so we must use reflection with setAccessible(true) to read it.
     *
     * Also handles __PHP_Incomplete_Class when the job class isn't autoloaded.
     *
     * @return array<string, mixed>
     */
    private function normalizeUnserialized(mixed $unserialized): array
    {
        if (!is_object($unserialized)) {
            return is_array($unserialized) ? $unserialized : ['value' => $unserialized];
        }

        $result = [];

        // --- class ---
        $isIncomplete = $unserialized instanceof \__PHP_Incomplete_Class;
        if ($isIncomplete) {
            $result['class'] = $unserialized->__PHP_Incomplete_Class_Name ?? get_class($unserialized);
        } else {
            $result['class'] = get_class($unserialized);
        }

        // --- Extract all properties via reflection (public + protected + private) ---
        $args = [];
        $standardProps = ['delay', 'queue', 'chainQueue', 'chainCatchCallbacks',
            'chained', 'middleware', 'connection', 'chainConnection'];
        $skip = ['job', 'jobId', 'attempts', 'tries', 'timeout', 'backoff',
            'failOnTimeout', 'retryUntil', 'maxTries', 'maxExceptions',
            '__PHP_Incomplete_Class_Name'];

        try {
            $ref = new \ReflectionObject($unserialized);
            foreach ($ref->getProperties() as $prop) {
                $propName = $prop->getName();

                // Skip internal plumbing
                if (in_array($propName, $skip, true)) {
                    continue;
                }

                $prop->setAccessible(true);

                try {
                    $propValue = $prop->getValue($unserialized);
                } catch (\Throwable) {
                    continue;
                }

                // Standard Laravel queue-job props shown as top-level keys
                if (in_array($propName, $standardProps, true)) {
                    if ($propValue !== null) {
                        $result[$propName] = $this->scalarize($propValue);
                    }
                    continue;
                }

                // Everything else (including private `args`) goes into the args list
                $args[$propName] = $this->scalarize($propValue);
            }
        } catch (\Throwable) {
            // reflection failed — try casting to array as last resort
            $cast = (array)$unserialized;
            foreach ($cast as $rawKey => $rawVal) {
                // Strip null-byte visibility prefix (\0ClassName\0 or \0*\0)
                $cleanKey = preg_replace('/^\x00[^\x00]+\x00/', '', (string)$rawKey);
                if ($cleanKey === '' || in_array($cleanKey, $skip, true)) {
                    continue;
                }
                if (in_array($cleanKey, $standardProps, true)) {
                    if ($rawVal !== null) {
                        $result[$cleanKey] = $this->scalarize($rawVal);
                    }
                    continue;
                }
                $args[$cleanKey] = $this->scalarize($rawVal);
            }
        }

        if (!empty($args)) {
            $result['args'] = $args;
        }

        return $result;
    }

    /**
     * Recursively reduce a value to JSON-safe scalars/arrays.
     *
     * @return mixed
     */
    private function scalarize(mixed $value): mixed
    {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn($v) => $this->scalarize($v), $value);
        }

        if (is_object($value)) {
            // Carbon / DateTimeInterface → ISO string
            if ($value instanceof \DateTimeInterface) {
                return $value->format(\DateTimeInterface::ATOM);
            }
            // Illuminate Model → ['class' => '...', 'id' => $model->getKey()]
            if (method_exists($value, 'getKey')) {
                return ['class' => get_class($value), 'id' => $value->getKey()];
            }
            // Generic objects → extract all properties via reflection
            try {
                $arr = [];
                $ref = new \ReflectionObject($value);
                foreach ($ref->getProperties() as $p) {
                    $p->setAccessible(true);
                    try {
                        $arr[$p->getName()] = $this->scalarize($p->getValue($value));
                    } catch (\Throwable) {
                        // skip unreadable
                    }
                }

                return $arr ?: get_class($value);
            } catch (\Throwable) {
                return get_class($value);
            }
        }

        return (string)$value;
    }

    private function shortClass(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts) ?: $class;
    }
}
