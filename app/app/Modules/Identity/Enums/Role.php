<?php

namespace App\Modules\Identity\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
