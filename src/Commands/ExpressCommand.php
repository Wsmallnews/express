<?php

namespace Wsmallnews\Express\Commands;

use Illuminate\Console\Command;

class ExpressCommand extends Command
{
    public $signature = 'express';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
