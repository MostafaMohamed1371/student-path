<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case Login = 'login';

    // Future: case ResetPassword = 'reset_password';
}
