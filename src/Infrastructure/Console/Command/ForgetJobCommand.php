<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\ForgetJobAction;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

final class ForgetJobCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:forget
        {uuid : The UUID of the job to delete}';

    /** @var string */
    protected $description = 'Delete all monitoring records for a specific job UUID.';

    public function handle(ForgetJobAction $action): int
    {
        $uuid = (string) $this->argument('uuid');
        $deleted = $action(new JobIdentifier($uuid));

        if ($deleted === 0) {
            $this->warn(sprintf('No monitoring records found for UUID "%s".', $uuid));

            return self::FAILURE;
        }

        $this->info(sprintf('Deleted %d record(s) for job "%s".', $deleted, $uuid));

        return self::SUCCESS;
    }
}
