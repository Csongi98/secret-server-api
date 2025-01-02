<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;
use App\Models\Secret;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('DeleteExpiredSecrets', function (){
    $expiredSecrets = Secret::where('expires_at', '<', now())->get();

    if ($expiredSecrets->isEmpty()){
        $this->info('No expired secrets found');
        return 0;
    }

    $deleteCount = $expiredSecrets->count();
    Secret::where('expires_at','<',now())->delete();

    $this->info('Deleted {$deletedCount} expired secrets');
    return 0;
})->daily();
