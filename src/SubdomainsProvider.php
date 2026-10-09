<?php

declare(strict_types=1);

namespace Subdomains;

use Illuminate\Console\Scheduling\Schedule;
use Pterodactyl\Events\Server\OperationCompleted;
use Pterodactyl\Extensions\ExtensionProvider;
use Pterodactyl\Services\Extensions\ExtensionSettingDefinition;
use Pterodactyl\Services\Extensions\ExtensionSettingsDefinition;
use Subdomains\Console\ReconcileCommand;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;

class SubdomainsProvider extends ExtensionProvider
{
    public function boot(): void
    {
        $this->registerApiRoutes();
        $this->loadExtensionMigrations();

        $this->registerPermissions('Subdomains.', [
            'read' => 'See the server\'s subdomains.',
            'manage' => 'Create and delete subdomains.',
        ]);

        $this->registerSettings(new ExtensionSettingsDefinition($this->settings(), [
            ExtensionSettingDefinition::make('cloudflare_token', 'cloudflare_token', '', ['nullable', 'string', 'max:255'])
                ->label('Cloudflare API token')
                ->help('A token with Zone: Read and DNS: Edit permissions for your domains.')
                ->secret(),
            ExtensionSettingDefinition::make('default_limit', 'default_limit', 1, ['required', 'integer', 'min:0', 'max:100'])
                ->label('Subdomains per server')
                ->help('Can be changed for single servers on their admin page.')
                ->field('number'),
            ExtensionSettingDefinition::make('blacklist', 'blacklist', ['www', 'mail', 'panel', 'admin', 'api', 'ftp', 'smtp', 'ns1', 'ns2'], [])
                ->label('Reserved names')
                ->help('Names users cannot take.')
                ->list(['regex:/^[a-z0-9-]{1,63}$/'], 100),
        ]));

        // Server rows are already gone when this runs, so records are found by UUID.
        $this->listenToServerOperations(function (OperationCompleted $event): void {
            if ($event->operation === 'delete') {
                $manager = $this->app->make(Manager::class);
                Record::query()->with('domain')->where('server_uuid', $event->serverUuid)->each(fn (Record $record) => $manager->delete($record));
            }
        });

        $this->registerCommands([ReconcileCommand::class]);
        $this->registerSchedule(static function (Schedule $schedule): void {
            $schedule->command('subdomains:reconcile')->hourly()->withoutOverlapping()->runInBackground();
        });
    }
}
