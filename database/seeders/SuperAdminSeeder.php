<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if user already exists
        $existingUser = DB::table('users')
            ->where('email', 'rohanmishra.design@gmail.com')
            ->first();

        if ($existingUser) {
            $this->command->info('SuperAdmin user already exists.');
            return;
        }

        // Get or create admin role
        $adminRole = DB::table('roles')
            ->where('name', 'Administrator')
            ->first();

        if (!$adminRole) {
            $adminRoleId = Str::uuid()->toString();
            DB::table('roles')->insert([
                'id' => $adminRoleId,
                'name' => 'Administrator',
                'description' => 'Super Administrator with full system access',
                'permission_type' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $adminRoleId = $adminRole->id;
        }

        // Get or create default group
        $defaultGroup = DB::table('groups')
            ->where('name', 'Default')
            ->first();

        if (!$defaultGroup) {
            $defaultGroupId = Str::uuid()->toString();
            DB::table('groups')->insert([
                'id' => $defaultGroupId,
                'name' => 'Default',
                'description' => 'Default user group',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $defaultGroupId = $defaultGroup->id;
        }

        // Create SuperAdmin user
        $userId = Str::uuid()->toString();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Rohan Mishra',
            'email' => 'rohanmishra.design@gmail.com',
            'password' => Hash::make('admin@123'),
            'role_id' => $adminRoleId,
            'status' => 1,
            'view_permission' => 'global',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign user to default group
        DB::table('user_groups')->insert([
            'user_id' => $userId,
            'group_id' => $defaultGroupId,
        ]);

        $this->command->info('SuperAdmin user created successfully!');
        $this->command->info('Email: rohanmishra.design@gmail.com');
        $this->command->info('Password: admin@123');
    }
}
