<?php

namespace Omnify\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Omnify\Core\Models\Admin;

class AdminCreateCommand extends Command
{
    protected $signature = 'admin:create
                            {--name= : 管理者名}
                            {--email= : メールアドレス}
                            {--password= : パスワード}';

    protected $description = '管理者アカウントを作成する（God Mode）';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('管理者名');
        $email = $this->option('email') ?? $this->ask('メールアドレス');
        $password = $this->option('password') ?? $this->secret('パスワード');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:admins,email'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->info("管理者アカウントを作成しました: {$admin->name} ({$admin->email})");

        return self::SUCCESS;
    }
}
