<?php

declare(strict_types=1);

namespace Subdomains;

use Closure;
use Illuminate\Console\Scheduling\Schedule;
use Pterodactyl\Events\Server\OperationCompleted;
use Pterodactyl\Extensions\ExtensionProvider;
use Pterodactyl\Services\Extensions\ExtensionSettingDefinition;
use Pterodactyl\Services\Extensions\ExtensionSettingsDefinition;
use Subdomains\Console\ReconcileCommand;
use Subdomains\Models\Record;
use Subdomains\Records\Manager;
use Subdomains\Support\Blacklist;

class SubdomainsProvider extends ExtensionProvider
{
    public function boot(): void
    {
        $this->registerApiRoutes();
        $this->loadExtensionMigrations();

        $this->registerPermissions('Subdomains.', [
            'read' => 'See the server\'s subdomains.',
            'create' => 'Create subdomains.',
            'update' => 'Rename subdomains (on domains where users type the name) and change their allocation and note.',
            'delete' => 'Delete subdomains.',
            'manage' => 'Create, change and delete subdomains (the permission from version 1.0).',
        ]);

        $this->registerSettings(new ExtensionSettingsDefinition($this->settings(), [
            ExtensionSettingDefinition::make('default_limit', 'default_limit', 1, ['required', 'integer', 'min:0', 'max:100'])
                ->label('Subdomains per server')
                ->help('Can be changed for single servers on their admin page.')
                ->field('number'),
            ExtensionSettingDefinition::make('blacklist', 'blacklist', ['www', 'mail', 'panel', 'admin', 'api', 'ftp', 'smtp', 'ns1', 'ns2'], [])
                ->label('Reserved names')
                ->help('Exact names users cannot take.')
                ->list(['regex:/^[a-z0-9-]{1,63}$/'], 100),
            ExtensionSettingDefinition::make('blacklist_patterns', 'blacklist_patterns', [], [])
                ->label('Reserved patterns')
                ->help('Regular expressions, matched against the name without the domain and ignoring case. Example: ^(staff|admin) blocks every name starting with staff or admin.')
                ->list(['max:191', self::patternRule()], 50),
            ExtensionSettingDefinition::make('allow_private_targets', 'allow_private_targets', false, ['boolean'])
                ->label('Allow private PowerDNS addresses')
                ->help('Lets a PowerDNS API URL use http or point to a private network, for example a PowerDNS on the same host.')
                ->field('toggle'),
            ExtensionSettingDefinition::make('cloudflare_token', 'cloudflare_token', '', ['nullable', 'string', 'max:255'])
                ->label('Cloudflare API token (fallback)')
                ->help('Used by Cloudflare domains without their own token. Domains get their own token under Admin → Subdomains.')
                ->secret(),
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

    /** @return Closure(string, mixed, Closure(string): mixed): void */
    private static function patternRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (($problem = Blacklist::problem((string) $value)) !== null) {
                $fail($problem);
            }
        };
    }
}
