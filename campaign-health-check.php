<?php
// campaign-health-check.php
// Run: cd /Users/husseinabuhajjaj/Sites/dropshipping && php campaign-health-check.php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    new Symfony\Component\Console\Input\ArrayInput(['command' => 'app:health-check-campaigns']),
    new Symfony\Component\Console\Output\ConsoleOutput,
);

exit($status);
