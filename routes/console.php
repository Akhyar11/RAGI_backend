<?php

use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\InstallModuleCommand;
use App\Console\Commands\InstallDummyModuleCommand;
use App\Console\Commands\InstallProdModuleCommand;

use Illuminate\Support\Facades\Schedule;

$modules = ['sikeu', 'simpeg', 'sippm', 'spmb', 'iam', 'all'];

foreach ($modules as $mod) {
    // 1. php artisan install:{modul} (e.g. php artisan install:sikeu)
    Artisan::command("install:{$mod}", function () use ($mod) {
        $this->call(InstallModuleCommand::class, ['modul' => $mod]);
    })->purpose("Instalasi sekali jalan skema DB & master data modul {$mod}");

    // 2. php artisan install-dummy:{modul} (e.g. php artisan install-dummy:sikeu)
    Artisan::command("install-dummy:{$mod}", function () use ($mod) {
        $this->call(InstallDummyModuleCommand::class, ['modul' => $mod]);
    })->purpose("Instalasi sekali jalan DB, master data, dan sampel data DUMMY untuk modul {$mod}");

    // 3. php artisan install-prod:{modul} (e.g. php artisan install-prod:sikeu)
    Artisan::command("install-prod:{$mod}", function () use ($mod) {
        $this->call(InstallProdModuleCommand::class, ['modul' => $mod]);
    })->purpose("Instalasi sekali jalan DB & master data murni PRODUCTION untuk modul {$mod}");
}

// Flexible space-separated syntax: php artisan module:install {modul=all}
Artisan::command('module:install {modul=all}', function ($modul = 'all') {
    $this->call(InstallModuleCommand::class, ['modul' => $modul]);
})->purpose('Instalasi sekali jalan skema DB & master data modul');

Artisan::command('module:install-dummy {modul=all}', function ($modul = 'all') {
    $this->call(InstallDummyModuleCommand::class, ['modul' => $modul]);
})->purpose('Instalasi sekali jalan DB, master data, dan sampel data DUMMY');

Artisan::command('module:install-prod {modul=all}', function ($modul = 'all') {
    $this->call(InstallProdModuleCommand::class, ['modul' => $modul]);
})->purpose('Instalasi sekali jalan DB & master data murni PRODUCTION');

Schedule::command('sippm:sync-publikasi')->weekly()->onSuccess(function () {
    \Illuminate\Support\Facades\Log::info('Weekly SINTA & Scopus publication sync completed successfully.');
});
