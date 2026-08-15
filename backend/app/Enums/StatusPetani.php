<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Status keanggotaan petani. */
enum StatusPetani: string
{
    use ResolvesFromInput;

    case MEMBER = 'member';
    case NON_MEMBER = 'non_member';

    public function label(): string
    {
        return match ($this) {
            self::MEMBER => 'Member',
            self::NON_MEMBER => 'Non-Member',
        };
    }
}
