<?php

namespace App\Support;

use App\Models\Guardian;

final class GuardianIdentityKey
{
    public static function for(Guardian $guardian): string
    {
        $idCard = IdCardNumber::normalize($guardian->id_card_number);
        if ($idCard !== null && $idCard !== '') {
            return 'id:'.$idCard;
        }

        $phone = preg_replace('/\D+/', '', (string) $guardian->phone) ?? '';
        if ($phone !== '') {
            return 'phone:'.$phone;
        }

        return 'guardian:'.(int) $guardian->id;
    }
}
