<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['id' => 1],
            [
                'slug' => 'game-inn',
                'name' => 'Game-INN',
                'is_active' => true,
                'primary_domain' => 'frontdesk.playdrive.test',
            ]
        );

        $domains = [
            ['domain' => 'frontdesk.playdrive.test', 'app_type' => 'frontdesk', 'is_primary' => true],
            ['domain' => 'backoffice.playdrive.test', 'app_type' => 'backoffice', 'is_primary' => false],
            ['domain' => 'kiosk.playdrive.test', 'app_type' => 'kiosk', 'is_primary' => false],
            ['domain' => 'client.playdrive.test', 'app_type' => 'client', 'is_primary' => false],
        ];

        foreach ($domains as $domain) {
            TenantDomain::updateOrCreate(
                ['domain' => $domain['domain']],
                [
                    'tenant_id' => 1,
                    'app_type' => $domain['app_type'],
                    'is_primary' => $domain['is_primary'],
                ]
            );
        }
    }
}
