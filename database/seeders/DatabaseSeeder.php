<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Curriculum');
        $this->call(ContentSeeder::class);

        $this->command->info('Demo school');
        $this->call(DemoSeeder::class);

        $this->command->info('Scale — three schools at real size');
        $this->call(ScaleSeeder::class);

        $this->command->newLine();
        $this->command->info('Sign in with any of these (password: password)');
        $this->command->table(['Role', 'Email'], [
            ['Student', 'student@classmate9ja.ng'],
            ['Teacher', 'teacher@classmate9ja.ng'],
            ['Parent', 'parent@classmate9ja.ng'],
            ['Admin', 'admin@classmate9ja.ng'],
        ]);
    }
}
