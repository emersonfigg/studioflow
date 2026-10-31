<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class MembershipIdentityCode extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function build(): static
    {
        return $this->subject('Confirme sua contratação no StudioFlow')
            ->text('public-memberships.identity-email');
    }
}
