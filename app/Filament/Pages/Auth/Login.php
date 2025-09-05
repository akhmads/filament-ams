<?php

namespace App\Filament\Pages\Auth;

class Login extends \Filament\Auth\Pages\Login
{
    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            'email' => 'admin@gmail.com',
            'password' => 'q1w2e3r4',
            'remember' => true,
        ]);
    }
}
